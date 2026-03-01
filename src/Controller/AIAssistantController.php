<?php

namespace App\Controller;

use App\Service\LangChainAIClient;
use App\Service\SmartRouteMappingService;
use App\Service\WebSearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class AIAssistantController extends AbstractController
{
    public function __construct(
        private readonly LangChainAIClient $langChainClient,
        private readonly SmartRouteMappingService $routeMappingService,
        private readonly WebSearchService $webSearch,
    ) {
    }

    #[Route('/ai/chat', name: 'ai_chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?: [];

        $messages = $payload['messages'] ?? [];
        $pageContext = $payload['pageContext'] ?? [];

        // Ensure messages is an array of {role, content}
        if (!is_array($messages)) {
            $messages = [];
        }

        // Build intelligent system prompt
        $systemPrompt = $this->buildSystemPrompt($pageContext);
        
        // Check prompt size to prevent overload (warn if too large)
        $promptSize = strlen($systemPrompt);
        if ($promptSize > 15000) {
            // If prompt is very large, truncate some content
            $systemPrompt = substr($systemPrompt, 0, 15000) . "\n\n[Note: Some content was truncated to prevent overload]";
        }

        // Prepend system prompt to messages (Ollama will use conversation history from messages array)
        array_unshift($messages, [
            'role' => 'system',
            'content' => $systemPrompt,
        ]);
        
        // Limit total messages to prevent overload (max 15 messages including system)
        if (count($messages) > 15) {
            $messages = [
                $messages[0], // Keep system prompt
                ...array_slice($messages, -14), // Keep last 14 messages
            ];
        }

        // Detect if user wants web search (Chat tab only - not Agent)
        $lastUserMessage = null;
        foreach (array_reverse($messages) as $msg) {
            if (($msg['role'] ?? '') === 'user') {
                $lastUserMessage = $msg['content'] ?? '';
                break;
            }
        }
        $wantsSearch = $lastUserMessage && (
            preg_match('/\b(search|find|look up|google|web search|internet search)\b/i', $lastUserMessage) ||
            preg_match('/\?$/', trim($lastUserMessage)) // Questions often benefit from search
        );
        $searchResults = null;
        $searchQuery = null;
        if ($wantsSearch && $this->webSearch->isAvailable()) {
            $searchQuery = preg_replace('/^(search|find|look up|google|web search|internet search)\s+(for\s+)?/i', '', $lastUserMessage);
            $searchQuery = trim($searchQuery);
            if ($searchQuery !== '' && strlen($searchQuery) > 2) {
                $searchResults = $this->webSearch->search($searchQuery, 8);
            }
        }

        // When we have real search results, return them as the reply (proper Google/search results, not random LLM answers)
        if ($searchResults && !empty($searchResults['results'])) {
            $replyParts = [];
            $replyParts[] = '**Search results for "' . $searchQuery . '"**';
            $replyParts[] = '';
            foreach (array_slice($searchResults['results'], 0, 8) as $i => $result) {
                $title = $result['title'] ?? 'Untitled';
                $link = $result['link'] ?? '#';
                $snippet = trim($result['snippet'] ?? '');
                $replyParts[] = ($i + 1) . '. **' . $title . '**';
                $replyParts[] = '   ' . $link;
                if ($snippet !== '') {
                    $replyParts[] = '   ' . $snippet;
                }
                $replyParts[] = '';
            }
            $searchSource = $searchResults['source'] ?? '';
            if ($searchSource) {
                $replyParts[] = '_Source: ' . $searchSource . '_';
            }
            return $this->json([
                'reply' => implode("\n", $replyParts),
                'actions' => [],
                'searchResults' => $searchResults,
                'searchQuery' => $searchQuery,
            ]);
        }

        // Optional: if user asked for search but we got no results, mention it in system prompt
        if ($wantsSearch && $searchQuery !== null && (empty($searchResults['results']) || !$searchResults)) {
            $systemPrompt .= "\n\n[The user asked for a web search for \"" . $searchQuery . "\" but no results were returned. Apologize briefly and suggest they try a different query or check their search API keys (Google Custom Search or SerpAPI).]";
        }
        
        // Note: Multi-page browsing temporarily disabled
        
        try {
            $responseData = $this->langChainClient->chat($messages, $pageContext);
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            
            // Check if Ollama is starting up
            $lockFile = sys_get_temp_dir() . '/horizon_ollama_startup.lock';
            if (file_exists($lockFile) && (time() - filemtime($lockFile) < 60)) {
                return $this->json([
                    'reply' => 'AI engine is still starting up. Please wait a moment and try again.',
                    'actions' => [],
                    'status' => 'starting',
                ]);
            }
            
            // Check if it's a timeout error
            if (strpos($errorMsg, 'timeout') !== false || strpos($errorMsg, 'Timeout') !== false) {
                return $this->json([
                    'reply' => 'The request took too long to process. The page might have too much content. Try asking about a specific section or topic instead.',
                    'actions' => [],
                    'error' => 'Timeout',
                ]);
            }
            
            // Check if it's a connection error
            if (strpos($errorMsg, 'Failed to connect') !== false || strpos($errorMsg, 'Connection') !== false) {
                return $this->json([
                    'reply' => 'Sorry, I cannot connect to the AI engine. Please make sure Ollama is running and try again.',
                    'actions' => [],
                    'error' => $errorMsg,
                ]);
            }
            
            return $this->json([
                'reply' => 'Sorry, I encountered an error processing your request. Please try again.',
                'actions' => [],
                'error' => $errorMsg,
            ]);
        }

        $replyText = '';
        $actions = [];

        // Handle different Ollama response structures
        $rawContent = null;
        if (isset($responseData['message']['content'])) {
            $rawContent = $responseData['message']['content'];
        } elseif (isset($responseData['content'])) {
            $rawContent = $responseData['content'];
        } elseif (is_string($responseData)) {
            $rawContent = $responseData;
        }

        if ($rawContent !== null) {
            // Clean and extract JSON from response
            $decoded = null;
            if (is_string($rawContent)) {
                $trimmed = trim($rawContent);
                
                // Remove markdown code blocks if present
                if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $trimmed, $matches)) {
                    $trimmed = $matches[1];
                }
                
                // Try to find first valid JSON object in the string
                if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/', $trimmed, $jsonMatches)) {
                    $trimmed = $jsonMatches[0];
                }
                
                // Clean up common issues
                $trimmed = preg_replace('/[^\x20-\x7E\n\r\t]/', '', $trimmed); // Remove non-printable chars
                $trimmed = preg_replace('/\s+/', ' ', $trimmed); // Normalize whitespace
                
                $decoded = json_decode($trimmed, true);
                
                // If JSON decode failed, try to extract just the reply text
                if ($decoded === null || json_last_error() !== JSON_ERROR_NONE) {
                    // Try to find a simple text response (first 200 chars that look like a reply)
                    $cleanText = preg_replace('/[^\w\s.,!?\-]/', '', $trimmed);
                    $cleanText = trim($cleanText);
                    if (strlen($cleanText) > 10 && strlen($cleanText) < 500) {
                        // Check if it looks like a reasonable response (not garbage)
                        $wordCount = str_word_count($cleanText);
                        if ($wordCount > 2 && $wordCount < 100) {
                            $replyText = $cleanText;
                            $actions = [];
                        }
                    }
                }
            } elseif (is_array($rawContent)) {
                $decoded = $rawContent;
            }

            // If we successfully decoded JSON, extract reply and actions
            if (is_array($decoded) && !empty($decoded)) {
                if (isset($decoded['reply']) && is_string($decoded['reply'])) {
                    $replyText = trim($decoded['reply']);
                    $actions = isset($decoded['actions']) && is_array($decoded['actions']) ? $decoded['actions'] : [];
                } elseif (isset($decoded['message']) && is_string($decoded['message'])) {
                    $replyText = trim($decoded['message']);
                    $actions = [];
                }
            }
        }
        
        // Validate and clean reply text
        if (!empty($replyText)) {
            // Check for garbage patterns BEFORE cleaning
            $garbagePatterns = [
                '/\b(implicitly|indirectly|synd|const|seg|translated|constr|compartment|deline|extrap|juxtap|mathem|encaps)\w*\b/i',
                '/(\w+\s+){10,}\1/', // Repetitive word sequences (very repetitive)
                '/\b(implicitly|indirectly)\s+(implicitly|indirectly)\s+/i', // Repeated keywords
            ];
            
            $isGarbage = false;
            $garbagePatternCount = 0;
            foreach ($garbagePatterns as $pattern) {
                if (preg_match($pattern, $replyText)) {
                    $garbagePatternCount++;
                }
            }
            
            // Only mark as garbage if multiple patterns match (more lenient)
            if ($garbagePatternCount >= 2) {
                $isGarbage = true;
            }
            
            // Check for repetitive words (but allow legitimate content descriptions)
            if (!$isGarbage) {
                $words = str_word_count(strtolower($replyText), 1);
                $wordFreq = array_count_values($words);
                $maxFreq = max($wordFreq);
                $totalWords = count($words);
                
                // Only flag as garbage if:
                // 1. Response is VERY long (>1000 chars or >200 words) AND
                // 2. Has excessive repetition (>30% of words are the same)
                if ($totalWords > 200 && $maxFreq > ($totalWords * 0.3)) {
                    $isGarbage = true;
                }
                
                // Also check for extremely long responses with no structure (likely garbage)
                if (strlen($replyText) > 1500 && !preg_match('/[.!?]/', $replyText)) {
                    $isGarbage = true;
                }
            }
            
            if ($isGarbage) {
                // If garbage detected, try to extract any meaningful content
                // Extract first sentence or meaningful fragment
                $sentences = preg_split('/[.!?]+/', $replyText);
                $meaningfulText = '';
                foreach ($sentences as $sentence) {
                    $sentence = trim($sentence);
                    if (strlen($sentence) > 20 && strlen($sentence) < 200 && !preg_match('/\b(implicitly|indirectly|synd|const|seg)\w*\b/i', $sentence)) {
                        $meaningfulText = $sentence;
                        break;
                    }
                }
                if (empty($meaningfulText)) {
                    // Last resort: return empty and let frontend handle it
                    $replyText = '';
                } else {
                    $replyText = $meaningfulText;
                }
            } else {
                // Clean up minor issues (but preserve legitimate content)
                $replyText = preg_replace('/\b(implicitly|indirectly|synd|const|seg|translated|constr)\w*\b/i', '', $replyText);
                $replyText = preg_replace('/\s{2,}/', ' ', $replyText); // Multiple spaces
                $replyText = trim($replyText);
                
                // Don't truncate legitimate content descriptions - allow up to 800 chars
                // Only truncate if it's clearly garbage (no punctuation, all one word, etc.)
                if (strlen($replyText) > 800) {
                    // Check if it looks like legitimate content (has punctuation, varied words)
                    $hasPunctuation = preg_match('/[.!?]/', $replyText);
                    $uniqueWords = count(array_unique(str_word_count(strtolower($replyText), 1)));
                    $totalWords = str_word_count($replyText);
                    
                    if ($hasPunctuation && $uniqueWords > ($totalWords * 0.5)) {
                        // Legitimate content - allow it but trim slightly if too long
                        if (strlen($replyText) > 1200) {
                            $replyText = substr($replyText, 0, 1200) . '...';
                        }
                    } else {
                        // Likely garbage - truncate
                        $replyText = substr($replyText, 0, 300) . '...';
                    }
                }
            }
        }

        // Final fallback: if replyText is still empty, analyze page content directly
        if (empty($replyText)) {
            $userMessage = strtolower(trim($payload['messages'][count($payload['messages']) - 1]['content'] ?? ''));
            
            // Try to understand what user is asking about
            if (preg_match('/\b(hi|hello|hey|greetings|good\s+(morning|afternoon|evening))\b/i', $userMessage)) {
                $replyText = 'Hello! I\'m Syndicati Agent. How can I help you today?';
            } elseif (preg_match('/\b(publication|article|content|page|what|tell|show|describe|list|analyze|information|data)\b/i', $userMessage)) {
                // User is asking about content - provide actual analysis from page context
                $analysis = $this->analyzePageContent($pageContext);
                if (!empty($analysis)) {
                    $replyText = $analysis;
                } else {
                    $replyText = 'I\'ve reviewed the page, but I don\'t see specific content to describe. The page appears to be mostly navigation or forms. What specific information are you looking for?';
                }
            } else {
                // Try to provide helpful response based on context
                $replyText = 'I understand your request. How can I assist you with the Syndicati website?';
            }
        }

        return $this->json([
            'reply' => $replyText,
            'actions' => $actions,
        ]);
    }
    
    private function analyzePageContent(array $pageContext): string
    {
        $analysis = [];
        
        if (isset($pageContext['summary'])) {
            $summary = $pageContext['summary'];
            
            // Analyze headings
            if (!empty($summary['headings'])) {
                $headingCount = count($summary['headings']);
                $analysis[] = "The page contains {$headingCount} main sections: " . implode(', ', array_slice($summary['headings'], 0, 15));
            }
            
            // Analyze articles/publications - DETAILED
            if (!empty($summary['articles'])) {
                $articleCount = count($summary['articles']);
                $analysis[] = "I found {$articleCount} " . ($articleCount === 1 ? 'article/publication' : 'articles/publications') . " on this page:";
                foreach (array_slice($summary['articles'], 0, 15) as $idx => $article) {
                    $title = $article['title'] ?? '';
                    $content = $article['content'] ?? '';
                    $author = $article['author'] ?? '';
                    $date = $article['date'] ?? '';
                    $category = $article['category'] ?? '';
                    
                    $articleInfo = [];
                    if ($title) {
                        $articleInfo[] = "\n" . ($idx + 1) . ". {$title}";
                    }
                    if ($author) {
                        $articleInfo[] = "   Author: {$author}";
                    }
                    if ($date) {
                        $articleInfo[] = "   Date: {$date}";
                    }
                    if ($category) {
                        $articleInfo[] = "   Category: {$category}";
                    }
                    if ($content) {
                        $preview = substr($content, 0, 150);
                        $articleInfo[] = "   Summary: {$preview}...";
                    }
                    
                    if (!empty($articleInfo)) {
                        $analysis[] = implode("\n", $articleInfo);
                    }
                }
            }
            
            // Analyze paragraphs
            if (!empty($summary['paragraphs'])) {
                $paraCount = count($summary['paragraphs']);
                if ($paraCount > 0) {
                    $analysis[] = "The page has {$paraCount} main content " . ($paraCount === 1 ? 'paragraph' : 'paragraphs') . " covering: " . substr(implode(' ', array_slice($summary['paragraphs'], 0, 3)), 0, 200) . "...";
                }
            }
            
            // Analyze lists
            if (!empty($summary['lists'])) {
                $totalItems = 0;
                $listItems = [];
                foreach ($summary['lists'] as $listIdx => $list) {
                    if (is_array($list)) {
                        $totalItems += count($list);
                        $listItems = array_merge($listItems, array_slice($list, 0, 10));
                    }
                }
                if ($totalItems > 0) {
                    $analysis[] = "There are {$totalItems} items in lists on this page, including: " . implode(', ', array_slice($listItems, 0, 10));
                }
            }
            
            // Analyze tables
            if (!empty($summary['tables'])) {
                $tableCount = count($summary['tables']);
                $analysis[] = "The page contains {$tableCount} data " . ($tableCount === 1 ? 'table' : 'tables') . " with structured information.";
                foreach (array_slice($summary['tables'], 0, 3) as $tableIdx => $table) {
                    if (isset($table['headers']) && is_array($table['headers'])) {
                        $analysis[] = "Table " . ($tableIdx + 1) . " columns: " . implode(', ', $table['headers']);
                    }
                }
            }
            
            // Analyze full text content if available
            if (!empty($summary['allTextContent'])) {
                $textPreview = substr($summary['allTextContent'], 0, 300);
                $analysis[] = "Page content overview: {$textPreview}...";
            }
        }
        
        return !empty($analysis) ? implode("\n\n", $analysis) : '';
    }

    #[Route('/ai/bootstrap', name: 'ai_bootstrap', methods: ['GET'])]
    public function bootstrap(): JsonResponse
    {
        // Check LangChain AI status
        $status = $this->langChainClient->checkStatus();
        
        // If Ollama is offline, check if we're already trying to start it
        if (!$status['running']) {
            $lockFile = sys_get_temp_dir() . '/horizon_ollama_startup.lock';
            
            // If lock file exists and is less than 30 seconds old, we're already starting
            if (file_exists($lockFile)) {
                $lockAge = time() - filemtime($lockFile);
                if ($lockAge < 30) {
                    return $this->json([
                        'status' => 'starting',
                        'message' => 'AI services are starting in the background...',
                        'ollamaRunning' => false,
                        'langchain' => false,
                        'langgraph' => false,
                    ]);
                }
            }
            
            // Create lock file and trigger background start
            touch($lockFile);
            $this->startOllamaBackground();
            
            return $this->json([
                'status' => 'starting',
                'message' => 'AI services are starting in the background...',
                'ollamaRunning' => false,
                'langchain' => false,
                'langgraph' => false,
            ]);
        }
        
        // Get user role information
        $userRole = $this->routeMappingService->getCurrentUserRole();
        $isAdmin = $this->routeMappingService->isCurrentUserAdmin();
        $availableRoutes = $this->routeMappingService->getAvailableRoutes();
        
        return $this->json([
            'status' => 'ok',
            'model' => $status['model'],
            'ollamaRunning' => $status['running'],
            'ollamaUrl' => $status['baseUrl'],
            'langchain' => true,
            'user' => [
                'role' => $userRole ?? 'Guest',
                'isAdmin' => $isAdmin,
                'isLoggedIn' => $userRole !== null,
            ],
            'accessibleRoutes' => array_keys($availableRoutes),
        ]);
    }
    
    /**
     * Start Ollama in the background without blocking
     */
    private function startOllamaBackground(): void
    {
        // Use the existing startOllama logic but fire-and-forget
        // We don't wait for result, just trigger it
        try {
            $projectRoot = dirname(__DIR__, 2);
            $pythonScript = $projectRoot . '/tools/ollama_manager.py';
            
            if (file_exists($pythonScript)) {
                // Use Python manager to start in background
                $pythonCmd = $this->findPython();
                if ($pythonCmd) {
                    $command = sprintf(
                        '%s %s start > /dev/null 2>&1 &',
                        escapeshellarg($pythonCmd),
                        escapeshellarg($pythonScript)
                    );
                    
                    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
                        $command = sprintf(
                            'start /B %s %s start > NUL 2>&1',
                            escapeshellarg($pythonCmd),
                            escapeshellarg($pythonScript)
                        );
                    }
                    
                    pclose(popen($command, 'r'));
                }
            } else {
                // Fallback: Try to start ollama serve directly
                $command = 'ollama serve > /dev/null 2>&1 &';
                if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
                    $command = 'start /B ollama serve > NUL 2>&1';
                }
                pclose(popen($command, 'r'));
            }
        } catch (\Exception $e) {
            // Silently fail - will retry on next request
        }
    }
    
    /**
     * Find Python executable
     */
    private function findPython(): ?string
    {
        $candidates = ['python', 'python3', 'py'];
        
        foreach ($candidates as $python) {
            $output = shell_exec($python . ' --version 2>&1');
            if ($output && strpos($output, 'Python') !== false) {
                return $python;
            }
        }
        
        return null;
    }

    #[Route('/ai/start-ollama', name: 'ai_start_ollama', methods: ['POST'])]
    public function startOllama(): JsonResponse
    {
        $model = $_ENV['OLLAMA_MODEL'] ?? 'phi4-mini:3.8b';
        
        // Try to start Ollama using Python manager first
        $pythonResult = $this->attemptStartWithPythonManager();
        
        if ($pythonResult['success']) {
            return $this->json([
                'success' => true,
                'message' => $pythonResult['message'],
                'method' => 'python_manager',
                'output' => $pythonResult['output'] ?? null,
            ]);
        }
        
        // Fallback to original method
        $result = $this->attemptStartOllama($model);
        
        return $this->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'method' => 'direct',
            'output' => $result['output'] ?? null,
        ]);
    }

    private function attemptStartWithPythonManager(): array
    {
        try {
            $projectRoot = dirname(__DIR__, 2);
            $pythonScript = $projectRoot . '/tools/ollama_manager.py';
            $configFile = $projectRoot . '/ollama_config.json';
            
            if (!file_exists($pythonScript)) {
                return [
                    'success' => false,
                    'message' => 'Python Ollama manager not found',
                ];
            }
            
            // Check if Python is available
            $pythonVersion = shell_exec('python --version 2>&1');
            if (strpos($pythonVersion, 'Python') === false) {
                return [
                    'success' => false,
                    'message' => 'Python is not available',
                ];
            }
            
            // Try to start using Python manager
            $command = sprintf(
                'cd "%s" && python tools/ollama_manager.py --start',
                escapeshellarg($projectRoot)
            );
            
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);
            
            if ($returnVar === 0) {
                // Wait a moment and check if Ollama started
                sleep(3);
                $ollamaUrl = $_ENV['OLLAMA_BASE_URL'] ?? 'http://127.0.0.1:11434';
                if ($this->checkOllamaStatus($ollamaUrl)) {
                    return [
                        'success' => true,
                        'message' => 'Ollama started successfully using Python manager',
                        'output' => implode("\n", $output),
                    ];
                }
            }
            
            return [
                'success' => false,
                'message' => 'Python manager failed to start Ollama',
                'output' => implode("\n", $output),
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error using Python manager: ' . $e->getMessage(),
            ];
        }
    }

    private function checkOllamaStatus(string $url): bool
    {
        try {
            $ch = curl_init($url . '/api/tags');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function attemptStartOllama(string $model): array
    {
        // Check if Ollama is already running
        if ($this->checkOllamaStatus($_ENV['OLLAMA_BASE_URL'] ?? 'http://127.0.0.1:11434')) {
            return [
                'success' => true,
                'message' => 'Ollama is already running',
            ];
        }

        // Try to find ollama executable
        $ollamaPath = $this->findOllamaPath();
        
        if (!$ollamaPath) {
            return [
                'success' => false,
                'message' => 'Could not find Ollama executable. Please start Ollama manually or ensure it\'s in your PATH.',
            ];
        }

        // Try to start Ollama server in background (Windows)
        if (PHP_OS_FAMILY === 'Windows') {
            // On Windows, start Ollama server in background
            $command = sprintf(
                'start /B "" "%s" serve > nul 2>&1',
                escapeshellarg($ollamaPath)
            );
            
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);
            
            // Wait a moment and check if it started
            sleep(2);
            
            if ($this->checkOllamaStatus($_ENV['OLLAMA_BASE_URL'] ?? 'http://127.0.0.1:11434')) {
                return [
                    'success' => true,
                    'message' => 'Ollama server started successfully',
                    'output' => implode("\n", $output),
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to start Ollama server. Please start it manually using: ollama serve',
                    'output' => implode("\n", $output),
                ];
            }
        } else {
            // Unix-like systems
            $command = sprintf(
                'nohup %s serve > /dev/null 2>&1 &',
                escapeshellarg($ollamaPath)
            );
            
            exec($command, $output, $returnVar);
            sleep(2);
            
            if ($this->checkOllamaStatus($_ENV['OLLAMA_BASE_URL'] ?? 'http://127.0.0.1:11434')) {
                return [
                    'success' => true,
                    'message' => 'Ollama server started successfully',
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to start Ollama server. Please start it manually.',
                ];
            }
        }
    }

    private function findOllamaPath(): ?string
    {
        // Common paths where Ollama might be installed
        $possiblePaths = [];
        
        if (PHP_OS_FAMILY === 'Windows') {
            // Check common Windows locations
            $possiblePaths = [
                'C:\\Program Files\\Ollama\\ollama.exe',
                'C:\\Program Files (x86)\\Ollama\\ollama.exe',
                getenv('LOCALAPPDATA') . '\\Programs\\Ollama\\ollama.exe',
            ];
            
            // Also check PATH
            $pathEnv = getenv('PATH');
            if ($pathEnv) {
                $paths = explode(';', $pathEnv);
                foreach ($paths as $path) {
                    $possiblePaths[] = rtrim($path, '\\') . '\\ollama.exe';
                }
            }
        } else {
            // Unix-like systems
            $possiblePaths = [
                '/usr/local/bin/ollama',
                '/usr/bin/ollama',
                '~/.local/bin/ollama',
            ];
            
            $pathEnv = getenv('PATH');
            if ($pathEnv) {
                $paths = explode(':', $pathEnv);
                foreach ($paths as $path) {
                    $possiblePaths[] = rtrim($path, '/') . '/ollama';
                }
            }
        }
        
        foreach ($possiblePaths as $path) {
            $expandedPath = str_replace('~', getenv('HOME') ?: getenv('USERPROFILE'), $path);
            if (file_exists($expandedPath) && is_executable($expandedPath)) {
                return $expandedPath;
            }
        }
        
        // Try to find it via 'which' or 'where'
        if (PHP_OS_FAMILY === 'Windows') {
            exec('where ollama 2>nul', $output, $returnVar);
        } else {
            exec('which ollama 2>/dev/null', $output, $returnVar);
        }
        
        if ($returnVar === 0 && !empty($output[0]) && file_exists($output[0])) {
            return $output[0];
        }
        
        return null;
    }

    private function buildSystemPrompt(array $pageContext): string
    {
        // Build comprehensive context with actual navigation routes
        $context = [
            'url' => $pageContext['url'] ?? '',
            'title' => $pageContext['title'] ?? '',
            'currentPage' => $pageContext['currentPage'] ?? 'unknown',
            'isLoggedIn' => $pageContext['isLoggedIn'] ?? false,
        ];
        
        // Include available navigation links (optimized - limit to prevent overload)
        $allNavLinks = [];
        if (isset($pageContext['navigation']['links']) && is_array($pageContext['navigation']['links'])) {
            foreach (array_slice($pageContext['navigation']['links'], 0, 20) as $link) { // Limit to 20
                if (isset($link['href']) && isset($link['text'])) {
                    $allNavLinks[] = [
                        'url' => $link['href'],
                        'label' => $link['text'],
                    ];
                }
            }
        }
        
        // Also include main nav links separately (limit to 10)
        if (isset($pageContext['navigation']['mainNavLinks']) && is_array($pageContext['navigation']['mainNavLinks'])) {
            foreach (array_slice($pageContext['navigation']['mainNavLinks'], 0, 10) as $link) {
                if (isset($link['href']) && isset($link['text'])) {
                    $allNavLinks[] = [
                        'url' => $link['href'],
                        'label' => $link['text'],
                    ];
                }
            }
        }
        
        // Remove duplicates
        $uniqueNavLinks = [];
        $seenUrls = [];
        foreach ($allNavLinks as $link) {
            $url = $link['url'];
            if (!isset($seenUrls[$url])) {
                $seenUrls[$url] = true;
                $uniqueNavLinks[] = $link;
            }
        }
        $context['availableRoutes'] = array_slice($uniqueNavLinks, 0, 20); // Reduced from 30 to 20
        
        // Include forms if they exist (limit to 3)
        if (isset($pageContext['forms']) && is_array($pageContext['forms']) && count($pageContext['forms']) > 0) {
            $context['forms'] = array_slice($pageContext['forms'], 0, 3);
        }
        
        // Include page content/summary for content analysis
        if (isset($pageContext['summary']) && is_array($pageContext['summary'])) {
            $context['pageContent'] = $pageContext['summary'];
        }
        
        $contextJson = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        
        // Build routes list for prompt
        $routesList = '';
        if (!empty($context['availableRoutes'])) {
            $routesList = "\n\nAVAILABLE NAVIGATION ROUTES:\n";
            foreach ($context['availableRoutes'] as $route) {
                $routesList .= "- {$route['url']} ({$route['label']})\n";
            }
        }
        
        // Build optimized content summary - limit size to prevent overload
        $contentSummary = '';
        if (isset($context['pageContent'])) {
            $content = $context['pageContent'];
            $contentSummary = "\n\n=== CURRENT PAGE CONTENT ===\n";
            $contentSummary .= "This is what's currently visible on the page. Use this information to answer questions naturally and intelligently.\n\n";
            
            // Limit full text content (reduced from 2000 to 1000 chars)
            if (!empty($content['allTextContent'])) {
                $contentSummary .= "PAGE TEXT CONTENT:\n";
                $contentSummary .= substr($content['allTextContent'], 0, 1000) . "\n\n";
            }
            
            // Limit headings (max 10 instead of all)
            if (!empty($content['headings'])) {
                $headings = array_slice($content['headings'], 0, 10);
                $contentSummary .= "MAIN HEADINGS (" . count($headings) . " of " . count($content['headings']) . "):\n";
                foreach ($headings as $heading) {
                    $contentSummary .= "- {$heading}\n";
                }
                $contentSummary .= "\n";
            }
            
            // Limit paragraphs (max 8 instead of 15)
            if (!empty($content['paragraphs'])) {
                $paragraphs = array_slice($content['paragraphs'], 0, 8);
                $contentSummary .= "KEY PARAGRAPHS (" . count($paragraphs) . " of " . count($content['paragraphs']) . "):\n";
                foreach ($paragraphs as $idx => $para) {
                    // Truncate long paragraphs
                    $para = strlen($para) > 200 ? substr($para, 0, 200) . '...' : $para;
                    $contentSummary .= ($idx + 1) . ". {$para}\n";
                }
                $contentSummary .= "\n";
            }
            
            // Limit articles (max 10 instead of all, truncate content)
            if (!empty($content['articles'])) {
                $articles = array_slice($content['articles'], 0, 10);
                $articleCount = count($content['articles']);
                $contentSummary .= "ARTICLES/PUBLICATIONS (" . count($articles) . " of {$articleCount}):\n";
                foreach ($articles as $idx => $article) {
                    $title = $article['title'] ?? '';
                    $contentText = $article['content'] ?? '';
                    $date = $article['date'] ?? '';
                    $author = $article['author'] ?? '';
                    $category = $article['category'] ?? '';
                    
                    // Truncate content to prevent overload
                    if ($contentText && strlen($contentText) > 150) {
                        $contentText = substr($contentText, 0, 150) . '...';
                    }
                    
                    $contentSummary .= ($idx + 1) . ". {$title}";
                    if ($author) $contentSummary .= " by {$author}";
                    if ($date) $contentSummary .= " ({$date})";
                    if ($category) $contentSummary .= " [{$category}]";
                    if ($contentText) $contentSummary .= "\n   {$contentText}";
                    $contentSummary .= "\n";
                }
                if ($articleCount > 10) {
                    $contentSummary .= "... and " . ($articleCount - 10) . " more articles\n";
                }
                $contentSummary .= "\n";
            }
            
            // Limit lists (max 3 lists, max 8 items each)
            if (!empty($content['lists'])) {
                $lists = array_slice($content['lists'], 0, 3);
                $contentSummary .= "LISTS:\n";
                foreach ($lists as $listIdx => $list) {
                    if (is_array($list)) {
                        $items = array_slice($list, 0, 8);
                        $contentSummary .= "List " . ($listIdx + 1) . " (" . count($items) . " items):\n";
                        foreach ($items as $item) {
                            // Truncate long items
                            $item = strlen($item) > 100 ? substr($item, 0, 100) . '...' : $item;
                            $contentSummary .= "  - {$item}\n";
                        }
                        if (count($list) > 8) {
                            $contentSummary .= "  ... and " . (count($list) - 8) . " more items\n";
                        }
                        $contentSummary .= "\n";
                    }
                }
            }
            
            // Limit tables (max 2 tables, max 5 rows each)
            if (!empty($content['tables'])) {
                $tables = array_slice($content['tables'], 0, 2);
                $contentSummary .= "DATA TABLES:\n";
                foreach ($tables as $tableIdx => $table) {
                    $contentSummary .= "Table " . ($tableIdx + 1) . ":\n";
                    if (isset($table['headers']) && is_array($table['headers'])) {
                        $contentSummary .= "  Columns: " . implode(' | ', array_slice($table['headers'], 0, 5)) . "\n";
                    }
                    if (isset($table['rows']) && is_array($table['rows'])) {
                        $rows = array_slice($table['rows'], 0, 5);
                        foreach ($rows as $row) {
                            $contentSummary .= "  {$row}\n";
                        }
                        if (count($table['rows']) > 5) {
                            $contentSummary .= "  ... and " . (count($table['rows']) - 5) . " more rows\n";
                        }
                    }
                    $contentSummary .= "\n";
                }
            }
            
            // Limit images (max 5)
            if (!empty($content['images'])) {
                $images = array_slice($content['images'], 0, 5);
                $contentSummary .= "IMAGES:\n";
                foreach ($images as $img) {
                    if (!empty($img['alt'])) {
                        $contentSummary .= "- {$img['alt']}\n";
                    }
                }
                $contentSummary .= "\n";
            }
            
            // Metadata (always include, it's small)
            if (!empty($content['metadata'])) {
                $meta = $content['metadata'];
                if (!empty($meta['description'])) {
                    $contentSummary .= "Page Description: {$meta['description']}\n";
                }
            }
            
            $contentSummary .= "=== END PAGE CONTENT ===\n";
            
            // Check total size and truncate if needed (max 8000 chars)
            if (strlen($contentSummary) > 8000) {
                $contentSummary = substr($contentSummary, 0, 8000) . "\n\n[Content truncated due to size limits]\n";
            }
        }
        
        // Add website-wide context understanding
        $websiteContext = "\n\n=== WEBSITE UNDERSTANDING ===\n";
        $websiteContext .= "You have access to the entire Syndicati website structure. ";
        $websiteContext .= "Use the navigation routes to understand what sections exist. ";
        $websiteContext .= "When users ask about the website, be intelligent about explaining its structure, purpose, and content. ";
        $websiteContext .= "Show that you understand how the website works, not just individual pages.\n";

        return <<<PROMPT
You are Syndicati Agent, an intelligent AI assistant embedded in the Syndicati website. You're conversational, helpful, and deeply understand the website's structure and content.

IMPORTANT: Respond with ONLY valid JSON. Format: {"reply": "your natural response", "actions": []}

PERSONALITY & CONVERSATION STYLE:
- Be natural, conversational, and intelligent - like ChatGPT or a knowledgeable colleague
- Show genuine understanding of the website and its content
- Be helpful, friendly, and engaging
- Think critically about what the user is asking
- Provide thoughtful, contextual responses based on the actual website data
- Don't sound scripted or robotic - be genuinely helpful
- If you don't know something, say so honestly but offer to help find it
- Reference specific details from the page content when relevant
- Show that you've actually analyzed the content, not just giving generic answers

UNDERSTANDING THE WEBSITE:
You have access to comprehensive information about the current page and the website structure:
- Current page URL, title, and full content
- All navigation routes available on the website
- Complete page content including articles, publications, headings, paragraphs, lists, tables
- Forms and interactive elements
- User's login status

When answering questions:
- Use the pageContent snapshot to provide SPECIFIC, DETAILED answers
- Reference actual titles, authors, dates, content from the page
- If asked about publications/articles, list them with full details
- If asked "what's on this page?", give a comprehensive overview
- If asked about website structure, use the navigation routes information
- Be thorough but natural - don't just list things, explain them contextually

NAVIGATION:
When user wants to navigate, use: {"type": "navigate", "url": "/exact-path"}
- Match natural language requests to routes (e.g., "profile" → "/profile")
- Use routes from availableRoutes list
- Be smart about synonyms and variations

FORM INTERACTIONS:
- Understand form purposes from context
- Fill fields naturally using selectors
- Submit forms when appropriate

CONVERSATION FLOW:
- Respond naturally to the user's question
- If they ask about content, analyze it deeply and provide insights
- If they ask about the website, explain its structure intelligently
- If they need help, be genuinely helpful
- Show that you understand context from previous messages
- Be conversational - like you're actually reading and understanding the page

Current context: {$contextJson}{$routesList}{$contentSummary}{$websiteContext}

Remember: You're an intelligent assistant who understands this website deeply. Be conversational, helpful, and show genuine understanding. Respond naturally - like you're actually analyzing and thinking about the content, not just following scripts.
PROMPT;
    }

    /**
     * Detect if user is asking for information from multiple pages
     */
    private function shouldBrowseMultiplePages(string $message): bool
    {
        $multiPageKeywords = [
            'gather information', 'browse', 'multiple pages', 'different pages',
            'various pages', 'across the site', 'throughout the site',
            'all residences', 'all apartments', 'all listings',
            'compare', 'multiple locations', 'different sections',
            'explore', 'find all', 'search for', 'look for',
            'information from', 'details from', 'data from'
        ];
        
        $messageLower = strtolower($message);
        
        foreach ($multiPageKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Extract paths to browse based on user message and context
     */
    private function extractPathsToBrowse(string $message, array $pageContext): array
    {
        $paths = [];
        $messageLower = strtolower($message);
        
        // Common page patterns based on keywords
        $pageMappings = [
            'residence' => ['/residence', '/residences', '/appartements', '/apartments'],
            'apartment' => ['/residence', '/appartements', '/apartments'],
            'forum' => ['/forum', '/forums', '/discussion'],
            'event' => ['/evenement', '/evenements', '/events', '/event'],
            'profile' => ['/profile', '/user', '/account'],
            'syndicat' => ['/syndicat', '/syndicats', '/management'],
            'home' => ['/'],
            'about' => ['/about', '/a-propos'],
            'contact' => ['/contact'],
        ];
        
        // Check for specific keywords in the message
        foreach ($pageMappings as $keyword => $possiblePaths) {
            if (strpos($messageLower, $keyword) !== false) {
                // Add the first matching path
                $paths[] = $possiblePaths[0];
            }
        }
        
        // If no specific keywords found, suggest relevant pages based on current context
        if (empty($paths)) {
            // Default to common pages that might have the information
            $paths = ['/', '/residence', '/forum'];
        }
        
        // Remove duplicates and limit to 5 pages
        $paths = array_unique($paths);
        $paths = array_slice($paths, 0, 5);
        
        return $paths;
    }

    #[Route('/ai/navigate', name: 'ai_navigate', methods: ['POST'])]
    public function navigate(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?: [];
        $message = $payload['message'] ?? '';
        
        if (empty($message)) {
            return $this->json([
                'success' => false,
                'error' => 'No message provided',
            ], 400);
        }
        
        // Use smart route mapping to get proper Symfony route
        $result = $this->routeMappingService->detectNavigationUrl($message);
        
        if ($result) {
            // Check if user has access to this route
            $requiredRoles = $result['required_roles'];
            $hasAccess = $this->routeMappingService->userHasAccess($requiredRoles);
            
            if (!$hasAccess) {
                // Return access denied with helpful message
                $userRole = $this->routeMappingService->getCurrentUserRole() ?? 'Guest';
                $requiredRolesList = $requiredRoles ? implode(', ', $requiredRoles) : 'authenticated user';
                
                return $this->json([
                    'success' => false,
                    'access_denied' => true,
                    'destination' => $result['destination'],
                    'url' => $result['url'],
                    'route_name' => $result['route_name'],
                    'required_roles' => $requiredRoles,
                    'user_role' => $userRole,
                    'message' => "You don't have access to {$result['destination']}. Required: {$requiredRolesList}, Your role: {$userRole}",
                    'suggestion' => $userRole === 'Guest' 
                        ? 'Please log in to access this page.' 
                        : 'This area requires higher privileges. Contact an administrator if you need access.',
                ]);
            }
            
            return $this->json([
                'success' => true,
                'destination' => $result['destination'],
                'url' => $result['url'],
                'route_name' => $result['route_name'],
                'required_roles' => $requiredRoles,
                'user_role' => $this->routeMappingService->getCurrentUserRole(),
                'is_admin' => $this->routeMappingService->isCurrentUserAdmin(),
            ]);
        }
        
        return $this->json([
            'success' => false,
            'message' => 'Could not determine navigation destination',
        ]);
    }
}
