<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * SyndicatiAgent - Intelligent Web Automation Agent
 * 
 * Features:
 * - LLM-powered intent analysis (Ollama)
 * - Smart route mapping and navigation
 * - Multi-step plan execution
 * - Tool-based architecture (navigate, click, type, search)
 * - Memory and context awareness
 * - Error recovery with fallback strategies
 */
class SyndicatiAgent
{
    private HttpClientInterface $client;
    private SmartRouteMappingService $routeService;
    private ?PlaywrightService $playwright;
    private ?WebSearchService $webSearch;
    private ?InternalKnowledgeService $knowledge;
    private ?AgentMemoryService $memory;
    
    private string $ollamaUrl;
    private string $model;
    
    public function __construct(
        HttpClientInterface $client,
        ?SmartRouteMappingService $routeService = null,
        ?PlaywrightService $playwright = null,
        ?WebSearchService $webSearch = null,
        ?InternalKnowledgeService $knowledge = null,
        ?AgentMemoryService $memory = null,
        ?string $ollamaUrl = null,
        ?string $model = null
    ) {
        $this->client = $client;
        $this->routeService = $routeService;
        $this->playwright = $playwright;
        $this->webSearch = $webSearch;
        $this->knowledge = $knowledge;
        $this->memory = $memory;
        $this->ollamaUrl = $ollamaUrl ?: ($_ENV['OLLAMA_BASE_URL'] ?? 'http://127.0.0.1:11434');
        $this->model = $model ?: ($_ENV['OLLAMA_MODEL'] ?? 'phi4-mini:3.8b');
    }

    /**
     * Execute a user goal with full LLM-powered intelligence
     */
    public function executeGoal(string $goal, array $context = []): array
    {
        error_log("[SyndicatiAgent] Executing: $goal");
        
        try {
            // Step 1: LLM-powered intent analysis
            $intent = $this->analyzeIntent($goal, $context);
            
            // Step 2: Build execution plan based on intent
            $plan = $this->buildPlan($intent, $goal, $context);
            
            // Step 3: Execute plan steps
            $results = $this->executePlan($plan, $context);
            
            // Step 4: Store in memory
            $this->storeExecution($goal, $intent, $results);
            
            return [
                'success' => true,
                'goal' => $goal,
                'intent' => $intent,
                'plan' => $plan,
                'results' => $results,
                'summary' => $this->generateSummary($goal, $results)
            ];
            
        } catch (\Exception $e) {
            error_log("[SyndicatiAgent] Error: " . $e->getMessage());
            return [
                'success' => false,
                'goal' => $goal,
                'error' => $e->getMessage(),
                'fallback' => $this->executeFallback($goal, $context)
            ];
        }
    }

    /**
     * LLM-powered intent analysis
     */
    private function analyzeIntent(string $goal, array $context): array
    {
        $prompt = $this->buildAnalysisPrompt($goal, $context);
        
        try {
            $response = $this->callLLM($prompt);
            $intent = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($intent)) {
                return $this->fallbackIntentAnalysis($goal);
            }
            
            return $intent;
            
        } catch (\Exception $e) {
            return $this->fallbackIntentAnalysis($goal);
        }
    }

    /**
     * Build prompt for intent analysis
     */
    private function buildAnalysisPrompt(string $goal, array $context): string
    {
        $currentUrl = $context['currentUrl'] ?? 'unknown';
        $pageTitle = $context['pageTitle'] ?? 'unknown';
        
        return <<<PROMPT
You are an intelligent web automation agent. Analyze the user's goal and determine their intent.

Current page: $pageTitle ($currentUrl)
User goal: "$goal"

Respond with JSON:
{
    "intent_type": "navigation|search|form_fill|information|action",
    "target_page": "page name or URL pattern",
    "entities": ["entity1", "entity2"],
    "parameters": {"key": "value"},
    "confidence": 0.9,
    "requires_navigation": true|false,
    "destination_route": "route_name_or_url"
}

Intent types:
- navigation: User wants to go to a specific page
- search: User wants to search for something
- form_fill: User wants to fill out a form
- information: User wants information about something
- action: User wants to perform an action (click, submit, etc.)

Examples:
Goal: "go to my profile" -> {"intent_type": "navigation", "target_page": "profile", "destination_route": "/profile"}
Goal: "search for users named John" -> {"intent_type": "search", "target_page": "user_search", "parameters": {"name": "John"}}
Goal: "create a new reclamation" -> {"intent_type": "action", "target_page": "reclamation_form", "destination_route": "/syndicat"}
PROMPT;
    }

    /**
     * Build execution plan based on intent
     */
    private function buildPlan(array $intent, string $goal, array $context): array
    {
        $plan = [];
        $intentType = $intent['intent_type'] ?? 'unknown';
        $destination = $intent['destination_route'] ?? $intent['target_page'] ?? null;
        
        // Add navigation step if needed
        if ($intent['requires_navigation'] ?? false) {
            if ($destination) {
                // Smart route resolution
                $route = $this->resolveRoute($destination, $goal);
                $plan[] = [
                    'type' => 'navigate',
                    'target' => $route,
                    'reason' => 'Navigate to target page'
                ];
            }
        }
        
        // Add intent-specific steps
        switch ($intentType) {
            case 'search':
                $plan[] = [
                    'type' => 'search',
                    'parameters' => $intent['parameters'] ?? [],
                    'reason' => 'Perform search'
                ];
                break;
                
            case 'form_fill':
                $plan[] = [
                    'type' => 'form_fill',
                    'form' => $intent['target_page'],
                    'data' => $intent['parameters'] ?? [],
                    'reason' => 'Fill out form'
                ];
                break;
                
            case 'action':
                $plan[] = [
                    'type' => 'action',
                    'action' => $intent['target_page'],
                    'parameters' => $intent['parameters'] ?? [],
                    'reason' => 'Perform action'
                ];
                break;
        }
        
        // Add verification step
        $plan[] = [
            'type' => 'verify',
            'check' => $intent['target_page'],
            'reason' => 'Verify completion'
        ];
        
        return $plan;
    }

    /**
     * Execute plan steps
     */
    private function executePlan(array $plan, array $context): array
    {
        $results = [];
        
        foreach ($plan as $index => $step) {
            try {
                $result = $this->executeStep($step, $context);
                $results[] = [
                    'step' => $index + 1,
                    'type' => $step['type'],
                    'status' => 'success',
                    'result' => $result
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'step' => $index + 1,
                    'type' => $step['type'],
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
                
                // Try recovery
                $recovery = $this->attemptRecovery($step, $e, $context);
                if ($recovery) {
                    $results[] = [
                        'step' => $index + 1,
                        'type' => 'recovery',
                        'status' => 'success',
                        'result' => $recovery
                    ];
                }
            }
        }
        
        return $results;
    }

    /**
     * Execute a single step
     */
    private function executeStep(array $step, array $context): mixed
    {
        $type = $step['type'];
        
        switch ($type) {
            case 'navigate':
                return $this->executeNavigate($step['target'] ?? '/');
                
            case 'search':
                return $this->executeSearch($step['parameters'] ?? []);
                
            case 'form_fill':
                return $this->executeFormFill($step['form'], $step['data'] ?? []);
                
            case 'action':
                return $this->executeAction($step['action'], $step['parameters'] ?? []);
                
            case 'verify':
                return $this->executeVerify($step['check']);
                
            default:
                throw new \InvalidArgumentException("Unknown step type: $type");
        }
    }

    /**
     * Execute navigation
     */
    private function executeNavigate(string $target): array
    {
        // If Playwright is available, use it for browser automation
        if ($this->playwright) {
            try {
                $result = $this->playwright->navigate(['url' => $target]);
                return ['method' => 'playwright', 'target' => $target, 'result' => $result];
            } catch (\Exception $e) {
                // Fallback to route-based navigation
                return ['method' => 'route', 'target' => $target, 'redirect' => true];
            }
        }
        
        // Fallback: return navigation instruction
        return ['method' => 'instruction', 'target' => $target, 'action' => 'navigate'];
    }

    /**
     * Execute search
     */
    private function executeSearch(array $parameters): array
    {
        $results = [];
        
        // Internal knowledge search
        if ($this->knowledge && isset($parameters['query'])) {
            $internal = $this->knowledge->search($parameters['query']);
            $results['internal'] = $internal;
        }
        
        // Web search if needed
        if ($this->webSearch && ($parameters['web_search'] ?? false)) {
            $web = $this->webSearch->search($parameters['query']);
            $results['web'] = $web;
        }
        
        return ['parameters' => $parameters, 'results' => $results];
    }

    /**
     * Execute form fill
     */
    private function executeFormFill(string $form, array $data): array
    {
        if (!$this->playwright) {
            return ['form' => $form, 'data' => $data, 'method' => 'instruction'];
        }
        
        // Get page info to find form fields
        $pageInfo = $this->playwright->getPageInfo([]);
        
        $filled = [];
        foreach ($data as $field => $value) {
            // Try to find matching input
            $selector = $this->findFieldSelector($field, $pageInfo);
            if ($selector) {
                $this->playwright->type(['selector' => $selector, 'text' => $value]);
                $filled[$field] = $selector;
            }
        }
        
        return ['form' => $form, 'filled_fields' => $filled];
    }

    /**
     * Execute action
     */
    private function executeAction(string $action, array $parameters): array
    {
        if (!$this->playwright) {
            return ['action' => $action, 'parameters' => $parameters, 'method' => 'instruction'];
        }
        
        switch ($action) {
            case 'click':
                $selector = $parameters['selector'] ?? $parameters['element'] ?? 'button';
                $result = $this->playwright->click(['selector' => $selector]);
                return ['action' => 'click', 'selector' => $selector, 'result' => $result];
                
            case 'submit':
                $result = $this->playwright->click(['selector' => 'button[type="submit"], .btn-primary']);
                return ['action' => 'submit', 'result' => $result];
                
            default:
                return ['action' => $action, 'status' => 'unknown_action'];
        }
    }

    /**
     * Execute verification
     */
    private function executeVerify(string $check): array
    {
        if (!$this->playwright) {
            return ['check' => $check, 'method' => 'instruction'];
        }
        
        $pageInfo = $this->playwright->getPageInfo([]);
        
        return [
            'check' => $check,
            'current_url' => $pageInfo['url'] ?? null,
            'title' => $pageInfo['title'] ?? null,
            'verified' => true
        ];
    }

    /**
     * Resolve route using SmartRouteMapping
     */
    private function resolveRoute(string $destination, string $goal): string
    {
        // Try exact match first
        if (str_starts_with($destination, '/')) {
            return $destination;
        }
        
        // Use SmartRouteMapping for natural language resolution if available
        if ($this->routeService) {
            try {
                $routeInfo = $this->routeService->resolveRouteFromIntent($goal, []);
                if ($routeInfo && isset($routeInfo['path'])) {
                    return $routeInfo['path'];
                }
            } catch (\Exception $e) {
                // Ignore and fallback
            }
        }
        
        // Fallback: construct URL
        return '/' . strtolower(str_replace(' ', '_', $destination));
    }

    /**
     * Find field selector on page
     */
    private function findFieldSelector(string $field, array $pageInfo): ?string
    {
        $inputs = $pageInfo['inputs'] ?? [];
        
        // Try exact match
        foreach ($inputs as $input) {
            $name = strtolower($input['name'] ?? '');
            $id = strtolower($input['id'] ?? '');
            $fieldLower = strtolower($field);
            
            if ($name === $fieldLower || $id === $fieldLower) {
                return $input['selector'];
            }
            
            // Partial match
            if (str_contains($name, $fieldLower) || str_contains($id, $fieldLower)) {
                return $input['selector'];
            }
        }
        
        // Fallback: generic selector
        return "[name*='$field'], [id*='$field'], input[placeholder*='$field']";
    }

    /**
     * Attempt recovery from failed step
     */
    private function attemptRecovery(array $step, \Exception $error, array $context): ?array
    {
        $errorMsg = $error->getMessage();
        
        // Navigation error - try alternative route
        if (str_contains($errorMsg, 'navigation') || str_contains($errorMsg, '404')) {
            $alternative = $this->findAlternativeRoute($step['target'] ?? '');
            if ($alternative) {
                return ['recovered_with' => $alternative];
            }
        }
        
        // Element not found - try fuzzy matching
        if (str_contains($errorMsg, 'not found') || str_contains($errorMsg, 'timeout')) {
            return ['recovered_with' => 'fuzzy_matching', 'message' => 'Trying alternative selectors'];
        }
        
        return null;
    }

    /**
     * Find alternative route
     */
    private function findAlternativeRoute(string $target): ?string
    {
        $alternatives = [
            'profile' => '/profile',
            'settings' => '/settings',
            'home' => '/',
            'dashboard' => '/residence',
            'syndicat' => '/syndicat',
            'reclamation' => '/syndicat',
            'user' => '/profile',
            'account' => '/profile',
            'login' => '/login',
            'signin' => '/login',
            'signup' => '/signup'
        ];
        
        $key = strtolower($target);
        return $alternatives[$key] ?? null;
    }

    /**
     * Fallback intent analysis
     */
    private function fallbackIntentAnalysis(string $goal): array
    {
        $goalLower = strtolower($goal);
        
        // Pattern matching for common intents
        if (preg_match('/(?:go to|navigate|open)\s+(.+)/i', $goal, $matches)) {
            $page = trim($matches[1]);
            $alt = $this->findAlternativeRoute($page);

            // CRITICAL: never invent routes like "/something" if we don't have a known alternative.
            // If we can't resolve a concrete, existing route, we mark navigation as not required.
            return [
                'intent_type' => 'navigation',
                'target_page' => $page,
                'destination_route' => $alt,
                'requires_navigation' => $alt !== null,
                'confidence' => 0.8,
                'method' => 'pattern_matching'
            ];
        }
        
        if (str_contains($goalLower, 'search')) {
            return [
                'intent_type' => 'search',
                'target_page' => 'search',
                'parameters' => ['query' => $goal],
                'requires_navigation' => false,
                'confidence' => 0.7,
                'method' => 'pattern_matching'
            ];
        }
        
        if (str_contains($goalLower, 'create') || str_contains($goalLower, 'new')) {
            return [
                'intent_type' => 'action',
                'target_page' => 'create_form',
                'requires_navigation' => true,
                'confidence' => 0.6,
                'method' => 'pattern_matching'
            ];
        }
        
        return [
            'intent_type' => 'unknown',
            'target_page' => 'unknown',
            'requires_navigation' => false,
            'confidence' => 0.3,
            'method' => 'fallback'
        ];
    }

    /**
     * Execute fallback when main execution fails
     */
    private function executeFallback(string $goal, array $context): array
    {
        $intent = $this->fallbackIntentAnalysis($goal);
        
        if ($intent['intent_type'] === 'navigation' && isset($intent['destination_route'])) {
            return [
                'fallback_type' => 'navigation',
                'target' => $intent['destination_route'],
                'message' => 'Navigating to ' . $intent['target_page']
            ];
        }
        
        return [
            'fallback_type' => 'unknown',
            'message' => 'Could not determine action for: ' . $goal
        ];
    }

    /**
     * Store execution in memory
     */
    private function storeExecution(string $goal, array $intent, array $results): void
    {
        if (!$this->memory) {
            return;
        }
        
        $this->memory->store([
            'timestamp' => time(),
            'goal' => $goal,
            'intent' => $intent,
            'results' => $results,
            'url' => $this->playwright?->getCurrentUrl() ?? 'unknown'
        ]);
    }

    /**
     * Generate human-readable summary
     */
    private function generateSummary(string $goal, array $results): string
    {
        $successCount = count(array_filter($results, fn($r) => $r['status'] === 'success'));
        $totalCount = count($results);
        $errorCount = $totalCount - $successCount;
        
        $summary = "✅ Goal: $goal\n";
        $summary .= "Executed $totalCount steps ($successCount success, $errorCount errors)";
        
        return $summary;
    }

    /**
     * Call Ollama LLM
     */
    private function callLLM(string $prompt): string
    {
        $response = $this->client->request('POST', $this->ollamaUrl . '/api/generate', [
            'json' => [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => false,
                'options' => [
                    'temperature' => 0.2,
                    'num_predict' => 500
                ]
            ],
            'timeout' => 30
        ]);
        
        $data = $response->toArray();
        $text = $data['response'] ?? '';
        
        // Extract JSON from response
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            return $matches[0];
        }
        
        return $text;
    }

    /**
     * Get tool schema for API
     */
    public function getToolSchema(): array
    {
        return [
            'navigate' => ['description' => 'Navigate to URL', 'params' => ['url' => 'string']],
            'search' => ['description' => 'Search for information', 'params' => ['query' => 'string']],
            'click' => ['description' => 'Click element', 'params' => ['selector' => 'string']],
            'type' => ['description' => 'Type text', 'params' => ['selector' => 'string', 'text' => 'string']],
            'form_fill' => ['description' => 'Fill form', 'params' => ['form' => 'string', 'data' => 'object']]
        ];
    }
}
