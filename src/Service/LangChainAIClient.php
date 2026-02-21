<?php

namespace App\Service;

use App\Service\SmartRouteMappingService;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LangChainAIClient
{
    private HttpClientInterface $client;
    private string $baseUrl;
    private string $model;
    private SmartRouteMappingService $routeMappingService;

    public function __construct(
        HttpClientInterface $client,
        SmartRouteMappingService $routeMappingService,
        ?string $baseUrl = null,
        ?string $model = null
    ) {
        $this->client = $client;
        $this->routeMappingService = $routeMappingService;
        $this->baseUrl = rtrim($baseUrl ?: ($_ENV['OLLAMA_BASE_URL'] ?? 'http://127.0.0.1:11434'), '/');
        $this->model = $model ?: ($_ENV['OLLAMA_MODEL'] ?? 'phi4-mini:3.8b');
        
        error_log("[SyndicatiAI] Using model: {$this->model}");
    }

    public function chat(array $messages, array $pageContext = []): array
    {
        $systemPrompt = $this->buildSystemPrompt($pageContext);
        
        // Check if this is an agent task
        $lastMessage = end($messages);
        $isAgentTask = $this->detectAgentTask($lastMessage['content'] ?? '');
        
        error_log("[SyndicatiAI] Last message: " . ($lastMessage['content'] ?? 'empty'));
        error_log("[SyndicatiAI] Is agent task: " . ($isAgentTask ? 'YES' : 'NO'));
        
        if ($isAgentTask) {
            error_log("[SyndicatiAI] Executing agent task");
            return $this->executeAgentTask($messages, $pageContext);
        }
        
        $ollamaMessages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages
        );

        $response = $this->client->request('POST', $this->baseUrl . '/api/chat', [
            'json' => [
                'model' => $this->model,
                'messages' => $ollamaMessages,
                'stream' => false,
                'options' => [
                    'temperature' => 0.7,
                    'num_predict' => 500,
                ]
            ],
            'timeout' => 60
        ]);

        $data = $response->toArray();
        
        if (isset($data['message']['content'])) {
            $content = $data['message']['content'];
            $parsed = $this->parseAIResponse($content);
            
            return [
                'message' => [
                    'content' => json_encode([
                        'reply' => $parsed['reply'] ?? $content,
                        'actions' => $parsed['actions'] ?? [],
                        'intent' => $parsed['intent'] ?? null,
                    ])
                ]
            ];
        }
        
        throw new \Exception('Invalid response from Ollama');
    }

    /**
     * Detect if user wants agent to perform a task
     */
    private function detectAgentTask(string $message): bool
    {
        $patterns = [
            '/\b(fill|type|enter|put|add|write|input)\b/i',
            '/\b(click|press|select|choose|pick)\b/i',
            '/\b(form|field|input|button|box|modal)\b/i',
            '/\b(subject|description|message|text|content)\b/i',
            '/\b(reclamation|complaint|ticket|request)\b/i',
            '/\b(help me|can you|please|do this|do that)\b/i',
            '/\b(sign up|signup|register|create account)\b/i',
            '/\b(log in|login|sign in|signin)\b/i',
            '/\b(navigate|go to|open|visit|show me)\b/i',
            '/\b(test|verify|check if|submit|send)\b/i',
            '/\b(agent|autonomous|perform|execute)\b/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message)) {
                error_log("[SyndicatiAI] Matched pattern: $pattern");
                return true;
            }
        }
        
        return false;
    }

    /**
     * Execute agent task - simplified to just trigger browser agent
     */
    private function executeAgentTask(array $messages, array $pageContext): array
    {
        error_log("[SyndicatiAgent] Triggering browser agent for task");
        
        $lastMessage = end($messages);
        $task = $lastMessage['content'] ?? '';
        
        // Return flat structure - not nested in 'message'
        return [
            'agent_mode' => true,
            'task' => $task,
            'reply' => "I'll help you with that. Let me take control of your browser to complete this task.",
            'actions' => []
        ];
    }

    /**
     * Generate summary of agent task execution
     */
    private function generateAgentSummary(array $steps): string
    {
        if (empty($steps)) {
            return "I couldn't execute any actions for this task.";
        }
        
        $successful = array_filter($steps, fn($s) => $s['result']['success'] ?? false);
        $failed = array_filter($steps, fn($s) => !($s['result']['success'] ?? false));
        
        $summary = "Task completed with " . count($successful) . " successful actions";
        if (!empty($failed)) {
            $summary .= " and " . count($failed) . " failures";
        }
        $summary .= ".\n\n";
        
        foreach ($steps as $i => $step) {
            $status = ($step['result']['success'] ?? false) ? "✓" : "✗";
            $tool = $step['action']['tool'] ?? 'unknown';
            $summary .= ($i + 1) . ". {$status} {$tool}: " . ($step['thought'] ?? '') . "\n";
        }
        
        return $summary;
    }

    private function buildSystemPrompt(array $pageContext): string
    {
        $role = $pageContext['userRole'] ?? 'User';
        $isAdmin = $pageContext['isAdmin'] ?? false;
        $currentPage = $pageContext['currentPage'] ?? 'unknown';
        
        $prompt = "You are Horizon AI, a helpful assistant for a real estate platform.\n\n";
        $prompt .= "User Role: {$role}\n";
        $prompt .= "Current Page: {$currentPage}\n\n";
        
        if ($isAdmin) {
            $prompt .= "The user is an ADMIN and can access all features.\n\n";
        }
        
        $prompt .= "Respond naturally and helpfully. Keep responses concise and friendly.";
        
        return $prompt;
    }

    private function parseAIResponse(string $content): array
    {
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json) {
                return $json;
            }
        }
        
        return ['reply' => $content, 'actions' => []];
    }

    public function checkStatus(): array
    {
        try {
            $response = $this->client->request('GET', $this->baseUrl . '/api/tags', [
                'timeout' => 3
            ]);
            
            $data = $response->toArray();
            $models = $data['models'] ?? [];
            
            $modelAvailable = false;
            foreach ($models as $model) {
                if (strpos($model['name'] ?? '', $this->model) !== false) {
                    $modelAvailable = true;
                    break;
                }
            }
            
            return [
                'running' => true,
                'model' => $this->model,
                'model_available' => $modelAvailable,
                'baseUrl' => $this->baseUrl,
            ];
            
        } catch (\Exception $e) {
            return [
                'running' => false,
                'model' => $this->model,
                'model_available' => false,
                'baseUrl' => $this->baseUrl,
                'error' => $e->getMessage(),
            ];
        }
    }
}
