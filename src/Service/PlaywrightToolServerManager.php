<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class PlaywrightToolServerManager
{
    private string $baseUrl;
    private string $projectRoot;
    private bool $autoStartAttempted = false;

    public function __construct(
        private readonly HttpClientInterface $http,
        ?string $baseUrl = null,
        ?string $projectRoot = null,
    ) {
        $this->baseUrl = rtrim($baseUrl ?: ($_ENV['PLAYWRIGHT_SERVER_URL'] ?? 'http://127.0.0.1:3002'), '/');
        $this->projectRoot = $projectRoot ?: __DIR__ . '/../..';
    }

    private const LIFECYCLE_DURATION = 1800; // 30 minutes

    /**
     * Check if the service is running. 
     * If not, attempt auto-start ONLY if it hasn't been started in the last 30 minutes.
     */
    public function checkStatus(bool $autoStart = true): array
    {
        try {
            $res = $this->http->request('GET', $this->baseUrl . '/agent/health', [
                'timeout' => 5, // Increased timeout to avoid false-positives
            ]);

            $data = $res->toArray(false);

            return [
                'running' => true,
                'url' => $this->baseUrl,
                'health' => $data,
                'persistent' => true
            ];
        } catch (\Throwable $e) {
            // Service is offline or non-responsive
            $lockFile = sys_get_temp_dir() . '/syndicati_playwright_start.lock';
            $now = time();
            $lastStart = file_exists($lockFile) ? (int) file_get_contents($lockFile) : 0;
            $cooldownRemaining = self::LIFECYCLE_DURATION - ($now - $lastStart);

            // If it's not running and we want to auto-start
            if ($autoStart && !$this->autoStartAttempted) {
                // Only auto-start if cooldown expired OR if it's the first time in this PHP request
                if ($cooldownRemaining <= 0) {
                    $started = $this->autoStart();
                    if ($started) {
                        return $this->checkStatus(false);
                    }
                } else {
                    error_log("[PlaywrightToolServerManager] Service offline, but auto-start is on cooldown (" . $cooldownRemaining . "s remaining).");
                }
            }

            return [
                'running' => false,
                'url' => $this->baseUrl,
                'error' => 'Service offline: ' . $e->getMessage(),
                'auto_start_attempted' => $this->autoStartAttempted,
                'cooldown_remaining' => max(0, $cooldownRemaining)
            ];
        }
    }

    public function autoStart(): bool
    {
        if ($this->autoStartAttempted) {
            return false;
        }
        $this->autoStartAttempted = true;

        // Record start attempt for 30-min lifecycle
        $lockFile = sys_get_temp_dir() . '/syndicati_playwright_start.lock';
        @file_put_contents($lockFile, time());

        $agentServer = $this->projectRoot . '/playwright/agent-server.js';
        $playwrightDir = $this->projectRoot . '/playwright';

        if (!file_exists($agentServer)) {
            error_log('[PlaywrightToolServerManager] Server script not found: ' . $agentServer);
            return false;
        }

        $node = $this->findNode();
        if (!$node) {
            error_log('[PlaywrightToolServerManager] Node.js executable not found.');
            return false;
        }

        try {
            if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
                // Windows: Use start /B for clean detachment and set title
                // cd /d handles drive changes correctly
                $cmd = sprintf(
                    'cmd /c "cd /d %s && start /B "SyndicatiPlaywright" %s %s"',
                    escapeshellarg($playwrightDir),
                    escapeshellarg($node),
                    escapeshellarg($agentServer)
                );
                pclose(popen($cmd . ' 2>&1', 'r'));
            } else {
                // Unix: Use nohup for background execution
                $cmd = sprintf(
                    'cd %s && nohup %s %s > /dev/null 2>&1 &',
                    escapeshellarg($playwrightDir),
                    escapeshellarg($node),
                    escapeshellarg($agentServer)
                );
                exec($cmd);
            }

            // Wait for server to be ready (up to 10 seconds)
            for ($i = 0; $i < 20; $i++) {
                usleep(500000); // 0.5s
                $status = $this->checkStatus(false);
                if (($status['running'] ?? false) === true) {
                    return true;
                }
            }

            error_log('[PlaywrightToolServerManager] Server failed to respond after 10s wait.');
            return false;
        } catch (\Throwable $e) {
            error_log('[PlaywrightToolServerManager] Exception during auto-start: ' . $e->getMessage());
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
}
