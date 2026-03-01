<?php

namespace App\Controller;

use App\Service\SyndicatiAgent;
use App\Service\PlaywrightService;
use App\Service\WebSearchService;
use App\Service\InternalKnowledgeService;
use App\Service\AgentMemoryService;
use App\Service\SmartRouteMappingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Syndicati Agent Controller - API endpoints for the agent
 */
class AgentController extends AbstractController
{
    private SyndicatiAgent $agent;
    private PlaywrightService $playwright;
    private WebSearchService $webSearch;
    private InternalKnowledgeService $knowledge;
    private AgentMemoryService $memory;
    
    public function __construct(
        HttpClientInterface $httpClient,
        ?SmartRouteMappingService $routeService = null,
        string $projectDir
    ) {
        // Initialize services - routeService is now optional
        $this->playwright = new PlaywrightService($httpClient);
        $this->webSearch = new WebSearchService($httpClient);
        $this->knowledge = $routeService ? new InternalKnowledgeService($routeService, $projectDir) : null;
        $this->memory = new AgentMemoryService();
        
        // Initialize main agent - pass null for routeService
        $this->agent = new SyndicatiAgent(
            $httpClient,
            null,
            $this->playwright,
            $this->webSearch,
            $this->knowledge,
            $this->memory
        );
    }
    
    /**
     * Execute an agent goal
     */
    #[Route('/agent/execute', name: 'agent_execute', methods: ['POST'])]
    public function execute(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        
        $goal = $data['goal'] ?? '';
        $context = $data['context'] ?? [];
        
        if (empty($goal)) {
            return $this->json([
                'success' => false,
                'error' => 'No goal provided'
            ], 400);
        }
        
        try {
            $result = $this->agent->executeGoal($goal, $context);
            
            return $this->json([
                'success' => true,
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    
    /**
     * Get available tools
     */
    #[Route('/agent/tools', name: 'agent_tools', methods: ['GET'])]
    public function getTools(): JsonResponse
    {
        return $this->json([
            'success' => true,
            'tools' => $this->agent->getToolSchema()
        ]);
    }
    
    /**
     * Web search endpoint
     */
    #[Route('/agent/search/web', name: 'agent_web_search', methods: ['POST'])]
    public function webSearch(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $query = $data['query'] ?? '';
        
        if (empty($query)) {
            return $this->json([
                'success' => false,
                'error' => 'No query provided'
            ], 400);
        }
        
        $results = $this->webSearch->search($query);
        
        return $this->json([
            'success' => true,
            'results' => $results
        ]);
    }
    
    /**
     * Internal knowledge search
     */
    #[Route('/agent/search/internal', name: 'agent_internal_search', methods: ['POST'])]
    public function internalSearch(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $query = $data['query'] ?? '';
        $context = $data['context'] ?? '';
        
        if (empty($query)) {
            return $this->json([
                'success' => false,
                'error' => 'No query provided'
            ], 400);
        }
        
        $results = $this->knowledge->search($query, $context);
        
        return $this->json([
            'success' => true,
            'results' => $results
        ]);
    }
    
    /**
     * Browser action - navigate
     */
    #[Route('/agent/browser/navigate', name: 'agent_browser_navigate', methods: ['POST'])]
    public function browserNavigate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        
        try {
            $result = $this->playwright->navigate($data);
            return $this->json(['success' => true, 'result' => $result]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Browser action - click
     */
    #[Route('/agent/browser/click', name: 'agent_browser_click', methods: ['POST'])]
    public function browserClick(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        
        try {
            $result = $this->playwright->click($data);
            return $this->json(['success' => true, 'result' => $result]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Browser action - type
     */
    #[Route('/agent/browser/type', name: 'agent_browser_type', methods: ['POST'])]
    public function browserType(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        
        try {
            $result = $this->playwright->type($data);
            return $this->json(['success' => true, 'result' => $result]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Browser action - get page info
     */
    #[Route('/agent/browser/page-info', name: 'agent_browser_page_info', methods: ['GET'])]
    public function browserPageInfo(): JsonResponse
    {
        try {
            $info = $this->playwright->getPageInfo();
            return $this->json(['success' => true, 'info' => $info]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Browser action - screenshot
     */
    #[Route('/agent/browser/screenshot', name: 'agent_browser_screenshot', methods: ['GET'])]
    public function browserScreenshot(): JsonResponse
    {
        try {
            $screenshot = $this->playwright->screenshot();
            return $this->json([
                'success' => true,
                'screenshot' => $screenshot,
                'format' => 'base64'
            ]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Memory - store
     */
    #[Route('/agent/memory/store', name: 'agent_memory_store', methods: ['POST'])]
    public function memoryStore(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        
        if (empty($data)) {
            return $this->json(['success' => false, 'error' => 'No data to store'], 400);
        }
        
        $key = $this->memory->store($data);
        
        return $this->json([
            'success' => true,
            'key' => $key
        ]);
    }
    
    /**
     * Memory - recall
     */
    #[Route('/agent/memory/recall', name: 'agent_memory_recall', methods: ['POST'])]
    public function memoryRecall(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $query = $data['query'] ?? '';
        $limit = $data['limit'] ?? 5;
        
        if (empty($query)) {
            return $this->json(['success' => false, 'error' => 'No query provided'], 400);
        }
        
        $memories = $this->memory->recall($query, $limit);
        
        return $this->json([
            'success' => true,
            'memories' => $memories
        ]);
    }
    
    /**
     * Get agent status
     */
    #[Route('/agent/status', name: 'agent_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $status = [
            'playwright_available' => $this->playwright->isAvailable(),
            'web_search_available' => $this->webSearch->isAvailable(),
            'knowledge_available' => true,
            'memory_available' => true,
            'timestamp' => time()
        ];
        
        return $this->json([
            'success' => true,
            'status' => $status
        ]);
    }
}
