<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * LangGraph Client Service
 * Communicates with the LangGraph Node.js server for advanced AI workflows
 * Auto-starts the server if not running
 */
class LangGraphClient
{
    private HttpClientInterface $client;
    private string $langGraphUrl;
    private ?string $sessionId = null;
    private string $projectRoot;
    private bool $autoStartAttempted = false;

    public function __construct(
        HttpClientInterface $client,
        ?string $langGraphUrl = null,
        ?string $projectRoot = null
    ) {
        $this->client = $client;

        // Allow configuration via constructor, then ENV, then sensible default.
        if ($langGraphUrl) {
            $this->langGraphUrl = rtrim($langGraphUrl, '/');
        } else {
            // Prefer explicit server URL if provided.
            $envUrl = $_ENV['LANGGRAPH_SERVER_URL'] ?? $_ENV['LANGGRAPH_URL'] ?? null;
            $host = $_ENV['LANGGRAPH_HOST'] ?? '127.0.0.1';
            $port = $_ENV['LANGGRAPH_PORT'] ?? '3001';

            if (is_string($envUrl) && $envUrl !== '') {
                $this->langGraphUrl = rtrim($envUrl, '/');
            } else {
                $this->langGraphUrl = sprintf('http://%s:%d', $host, (int) $port);
            }
        }

        // Use realpath to ensure absolute path consistency
        $root = $projectRoot ?: realpath(__DIR__ . '/../..');
        $this->projectRoot = is_string($root) && $root !== '' ? $root : __DIR__ . '/../..';
    }

    /**
     * Auto-start the LangGraph server using Node directly
     */
    public function autoStart(): bool
    {
        if ($this->autoStartAttempted) {
            return false;
        }
        $this->autoStartAttempted = true;

        // Final sanity check: is it already running right now?
        // This prevents parallel processes from trying to start it at the same time.
        $status = $this->checkStatus(false);
        if ($status['running']) {
            return true;
        }

        $serverScript = $this->projectRoot . '/assets/langgraph-server.js';
        $assetsDir = $this->projectRoot . '/assets';

        if (!file_exists($serverScript)) {
            error_log('[LangGraphClient] Server script not found: ' . $serverScript);
            return false;
        }

        $node = $this->findNode();
        if (!$node) {
            error_log('[LangGraphClient] Node.js executable not found – cannot auto-start LangGraph server.');
            return false;
        }

        try {
            if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
                // Windows: Use start /B to run in background without creating a visible window
                // cd /d is crucial for handling different drives
                $cmd = sprintf(
                    'cmd /c "cd /d %s && start /B "SyndicatiLangGraph" %s %s"',
                    escapeshellarg($assetsDir),
                    escapeshellarg($node),
                    escapeshellarg($serverScript)
                );
                pclose(popen($cmd . ' 2>&1', 'r'));
            } else {
                // Unix: Run in background with nohup and redirect output
                $cmd = sprintf(
                    'cd %s && nohup %s %s > /dev/null 2>&1 &',
                    escapeshellarg($assetsDir),
                    escapeshellarg($node),
                    escapeshellarg($serverScript)
                );
                exec($cmd);
            }

            // Wait for server to be ready (up to 10 seconds)
            // Using shorter wait steps to detect readiness faster
            for ($i = 0; $i < 20; $i++) {
                usleep(500000); // 0.5s
                $status = $this->checkStatus(false);
                if (($status['running'] ?? false) === true) {
                    return true;
                }
            }

            error_log('[LangGraphClient] Server failed to respond after 10s wait.');
            return false;
        } catch (\Throwable $e) {
            error_log('[LangGraphClient] Exception during auto-start: ' . $e->getMessage());
            return false;
        }
    }

    private function findNode(): ?string
    {
        $candidates = ['node'];
        foreach ($candidates as $bin) {
            $output = [];
            $code = 0;
            @exec($bin . ' --version 2>&1', $output, $code);
            if ($code === 0) {
                return $bin;
            }
        }
        return null;
    }
    private const LIFECYCLE_DURATION = 1800; // 30 minutes

    /**
     * Check if LangGraph server is running
     * Automatically tries to start if not running
     */
    public function checkStatus(bool $autoStart = true): array
    {
        try {
            $response = $this->client->request('GET', $this->langGraphUrl . '/status', [
                'timeout' => 5, // Increased timeout
            ]);

            $data = $response->toArray();
            $running = $data['running'] ?? false;

            if ($running) {
                return [
                    'running' => true,
                    'langgraph' => $data['langgraph'] ?? true,
                    'model' => $data['model'] ?? 'unknown',
                    'sessions_active' => $data['sessions_active'] ?? 0,
                    'persistent' => true
                ];
            }
        } catch (\Exception $e) {
            // Service might be down
        }

        $lockFile = sys_get_temp_dir() . '/syndicati_langgraph_start.lock';
        $now = time();
        $lastStart = file_exists($lockFile) ? (int) file_get_contents($lockFile) : 0;
        $cooldownRemaining = self::LIFECYCLE_DURATION - ($now - $lastStart);

        // Try to auto-start if enabled and not already attempted in this request
        if ($autoStart && !$this->autoStartAttempted) {
            if ($cooldownRemaining <= 0) {
                $started = $this->autoStart();
                if ($started) {
                    return $this->checkStatus(false);
                }
            } else {
                error_log("[LangGraphClient] Service offline, but auto-start is on cooldown (" . $cooldownRemaining . "s remaining).");
            }
        }

        return [
            'running' => false,
            'langgraph' => false,
            'error' => 'Service offline or failed to start',
            'auto_start_attempted' => $this->autoStartAttempted,
            'cooldown_remaining' => max(0, $cooldownRemaining)
        ];
    }

    /**
     * Initialize a new session
     */
    public function initSession(?string $baseUrl = null, ?string $model = null): ?string
    {
        // Ensure server is running
        $this->checkStatus(true);

        try {
            $response = $this->client->request('POST', $this->langGraphUrl . '/session/init', [
                'json' => [
                    'baseUrl' => $baseUrl,
                    'model' => $model,
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray();
            if ($data['success']) {
                $this->sessionId = $data['session_id'];
                return $this->sessionId;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Process a message through LangGraph workflow
     */
    public function processMessage(string $message, array $pageContext = [], array $config = []): array
    {
        // Ensure we have a session
        if (!$this->sessionId) {
            $this->initSession($config['baseUrl'] ?? null, $config['model'] ?? null);
        }

        try {
            $response = $this->client->request('POST', $this->langGraphUrl . '/chat', [
                'json' => [
                    'session_id' => $this->sessionId,
                    'message' => $message,
                    'page_context' => $pageContext,
                    'config' => $config,
                ],
                'timeout' => 60, // LangGraph workflows may take longer
            ]);

            $data = $response->toArray();

            // Update session ID if returned
            if (isset($data['session_id'])) {
                $this->sessionId = $data['session_id'];
            }

            return [
                'success' => $data['success'] ?? false,
                'reply' => $data['reply'] ?? 'No response',
                'actions' => $data['actions'] ?? [],
                'intent' => $data['intent'] ?? null,
                'steps' => $data['steps'] ?? 0,
                'session_id' => $this->sessionId,
            ];

        } catch (\Exception $e) {
            // Fallback to regular LangChain if LangGraph fails
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'fallback' => true,
            ];
        }
    }

    /**
     * Stream a message for real-time updates
     */
    public function streamMessage(string $message, array $pageContext = [], array $config = []): \Generator
    {
        if (!$this->sessionId) {
            $this->initSession($config['baseUrl'] ?? null, $config['model'] ?? null);
        }

        try {
            $response = $this->client->request('POST', $this->langGraphUrl . '/chat/stream', [
                'json' => [
                    'session_id' => $this->sessionId,
                    'message' => $message,
                    'page_context' => $pageContext,
                    'config' => $config,
                ],
                'timeout' => 60,
            ]);

            // Read SSE stream
            $content = $response->getContent();
            $lines = explode("\n", $content);

            foreach ($lines as $line) {
                if (str_starts_with($line, 'data: ')) {
                    $json = substr($line, 6);
                    $data = json_decode($json, true);
                    if ($data) {
                        yield $data;
                    }
                }
            }

        } catch (\Exception $e) {
            yield ['type' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Clear the current session
     */
    public function clearSession(): bool
    {
        if (!$this->sessionId) {
            return true;
        }

        try {
            $this->client->request('POST', $this->langGraphUrl . '/session/clear', [
                'json' => ['session_id' => $this->sessionId],
                'timeout' => 5,
            ]);

            $this->sessionId = null;
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get current session ID
     */
    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * Set session ID (for restoring sessions)
     */
    public function setSessionId(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }
}
