<?php

namespace App\Service;

use Symfony\Component\Finder\Finder;

/**
 * Internal Knowledge Service - RAG for Symfony App
 * 
 * Provides internal knowledge about:
 * - Routes and their purposes
 * - Twig templates structure
 * - Entity schemas
 * - Business rules and validation
 */
class InternalKnowledgeService
{
    private SmartRouteMappingService $routeService;
    private DeepCodebaseScanner $deepScanner;
    private string $projectDir;
    
    // Cached knowledge
    private ?array $routeKnowledge = null;
    private ?array $templateKnowledge = null;
    private ?array $entityKnowledge = null;
    
    public function __construct(
        SmartRouteMappingService $routeService,
        DeepCodebaseScanner $deepScanner,
        string $projectDir
    ) {
        $this->routeService = $routeService;
        $this->deepScanner = $deepScanner;
        $this->projectDir = $projectDir;
    }
    
    /**
     * Search internal documentation
     */
    public function search(string $query, string $context = ''): array
    {
        $results = [];
        
        // Search routes
        $routeResults = $this->searchRoutes($query);
        if (!empty($routeResults)) {
            $results['routes'] = $routeResults;
        }
        
        // Search templates
        $templateResults = $this->searchTemplates($query);
        if (!empty($templateResults)) {
            $results['templates'] = $templateResults;
        }
        
        // Search entities
        $entityResults = $this->searchEntities($query);
        if (!empty($entityResults)) {
            $results['entities'] = $entityResults;
        }
        
        return [
            'query' => $query,
            'context' => $context,
            'results' => $results,
            'summary' => $this->generateSummary($results)
        ];
    }
    
    /**
     * Search for routes related to query with DEEP codebase scanning
     * ONLY returns routes that actually exist - never generates fake URLs
     */
    private function searchRoutes(string $query): array
    {
        $queryLower = strtolower($query);
        $matches = [];
        
        // 1. Use DeepCodebaseScanner for comprehensive codebase search
        // This scans controllers, templates, configs, entities - everything
        $deepMatches = $this->deepScanner->searchRoutes($query);
        
        foreach ($deepMatches as $match) {
            $routeName = $match['route_name'];
            $url = $match['url'];
            
            // CRITICAL: Only use routes that are validated and exist
            if (!$this->deepScanner->routeExists($routeName)) {
                continue;
            }
            
            // Get route info from SmartRouteMappingService for roles/aliases
            $routeInfo = null;
            $availableRoutes = $this->routeService->getAvailableRoutes();
            foreach ($availableRoutes as $alias => $info) {
                if ($info['route_name'] === $routeName) {
                    $routeInfo = $info;
                    break;
                }
            }
            
            $matches[$url] = [
                'alias' => $routeInfo['route_name'] ?? $routeName,
                'route_name' => $routeName,
                'url' => $url,
                'path' => $match['path'] ?? '',
                'score' => $match['score'],
                'roles' => $routeInfo['roles'] ?? null,
                'type' => $match['type'] ?? 'codebase_match'
            ];
        }
        
        // REMOVED: SmartRouteMappingService fallback
        // We ONLY use DeepCodebaseScanner now - it validates all routes exist before returning them
        // No fallbacks that could invent fake URLs

        // 3. Deep scan templates for content-based navigation (legacy support)
        $templateMatches = $this->deepScanTemplatesForNavigation($queryLower);
        foreach ($templateMatches as $tm) {
            $url = $tm['url'];
            $routeName = $tm['route_name'] ?? null;
            
            // CRITICAL: Only use if route exists
            if ($routeName && $this->deepScanner->routeExists($routeName)) {
                if (!isset($matches[$url]) || $tm['score'] > $matches[$url]['score']) {
                    $matches[$url] = $tm;
                }
            }
        }
        
        $finalMatches = array_values($matches);
        usort($finalMatches, fn($a, $b) => $b['score'] <=> $a['score']);
        
        return array_slice($finalMatches, 0, 10);
    }

    /**
     * Scan all Twig templates for text matching the query and resolve them to routes
     */
    private function deepScanTemplatesForNavigation(string $queryLower): array
    {
        $matches = [];
        $templatesDir = $this->projectDir . '/templates';
        if (!is_dir($templatesDir)) {
            return [];
        }

        try {
            $finder = new Finder();
            $finder->files()->in($templatesDir)->name('*.twig');
            
            foreach ($finder as $file) {
                $content = $file->getContents();

                // Look for labels in buttons, links, or tabs whose text contains the query.
                if (preg_match_all(
                    '/(?:<button|<a|<li|<label)[^>]*>([^<]*' . preg_quote($queryLower, '/') . '[^<]*)<\/(?:button|a|li|label)>/i',
                    $content,
                    $textMatches
                )) {
                    // Try to resolve this template to a route
                    $routeName = $this->resolveTemplateToRoute($file->getRelativePathname(), $content);
                    if ($routeName) {
                        // Get concrete URL for this route name from router
                        $url = $this->routeService->getUrlForRouteName($routeName);
                        if ($url && $url !== '/path') {
                            $matches[] = [
                                'alias' => trim(strip_tags($textMatches[1][0])),
                                'route_name' => $routeName,
                                'url' => $url,
                                'score' => 75,
                                'type' => 'template_match'
                            ];
                        }
                    }
                }
            }
        } catch (\Exception) {
        }

        return $matches;
    }

    private function resolveTemplateToRoute(string $relativePath, string $content): ?string
    {
        // 1. Scan for path() or url() calls - extract route names
        $routeNames = [];
        
        if (preg_match_all('/path\([\'"]([\w_]+)[\'"]/', $content, $pathMatches)) {
            $routeNames = array_merge($routeNames, $pathMatches[1]);
        }
        
        if (preg_match_all('/url\([\'"]([\w_]+)[\'"]/', $content, $urlMatches)) {
            $routeNames = array_merge($routeNames, $urlMatches[1]);
        }
        
        // 2. CRITICAL: Only return route names that actually exist
        foreach ($routeNames as $routeName) {
            if ($this->deepScanner->routeExists($routeName)) {
                return $routeName;
            }
        }
        
        // 3. Fallback: Check if path suggests a route name
        $cleanPath = str_replace(['.html.twig', '/'], ['', '_'], $relativePath);
        $cleanPath = strtolower($cleanPath);
        
        // Try to match against validated routes
        $validatedRoutes = $this->deepScanner->getValidatedRoutes();
        foreach (array_keys($validatedRoutes) as $routeName) {
            if (str_contains(strtolower($routeName), $cleanPath) || str_contains($cleanPath, strtolower($routeName))) {
                return $routeName;
            }
        }

        return null;
    }
    
    /**
     * Search templates
     */
    private function searchTemplates(string $query): array
    {
        $queryLower = strtolower($query);
        $matches = [];
        
        $templatesDir = $this->projectDir . '/templates';
        if (!is_dir($templatesDir)) {
            return [];
        }
        
        try {
            $finder = new Finder();
            $finder->files()->in($templatesDir)->name('*.twig');
            
            foreach ($finder as $file) {
                $path = $file->getRelativePathname();
                $pathLower = strtolower($path);
                
                $score = 0;
                
                // Check if template matches query
                if (str_contains($pathLower, $queryLower)) {
                    $score = 60;
                }
                
                // Read content for more context
                if ($score > 0 || $this->isRelevantTemplate($pathLower, $queryLower)) {
                    $content = $file->getContents();
                    
                    // Extract key info
                    $forms = $this->extractFormsFromTemplate($content);
                    $blocks = $this->extractBlocksFromTemplate($content);
                    
                    $matches[] = [
                        'path' => $path,
                        'score' => $score,
                        'forms' => $forms,
                        'blocks' => $blocks
                    ];
                }
            }
        } catch (\Exception $e) {
            // Ignore finder errors
        }
        
        return array_slice($matches, 0, 3);
    }
    
    /**
     * Check if template is relevant to query
     */
    private function isRelevantTemplate(string $pathLower, string $queryLower): bool
    {
        $relevantPatterns = [
            'profile' => ['profile', 'user', 'account'],
            'residence' => ['residence', 'apartment', 'housing'],
            'forum' => ['forum', 'discussion', 'post'],
            'auth' => ['auth', 'login', 'signup', 'register'],
            'admin' => ['admin', 'dashboard'],
            'settings' => ['settings', 'preferences'],
        ];
        
        foreach ($relevantPatterns as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($queryLower, $keyword) && str_contains($pathLower, $category)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Extract form info from template
     */
    private function extractFormsFromTemplate(string $content): array
    {
        $forms = [];
        
        // Find form blocks
        if (preg_match_all('/<form[^>]*>(.*?)<\/form>/is', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $formHtml = $match[0];
                
                // Extract inputs
                $inputs = [];
                if (preg_match_all('/<input[^>]+name=["\']([^"\']+)["\'][^>]*>/i', $formHtml, $inputMatches)) {
                    $inputs = $inputMatches[1];
                }
                
                // Extract action
                $action = '';
                if (preg_match('/action=["\']([^"\']+)["\']/i', $formHtml, $actionMatch)) {
                    $action = $actionMatch[1];
                }
                
                $forms[] = [
                    'action' => $action,
                    'inputs' => $inputs
                ];
            }
        }
        
        return $forms;
    }
    
    /**
     * Extract block names from template
     */
    private function extractBlocksFromTemplate(string $content): array
    {
        $blocks = [];
        
        if (preg_match_all('/{% block ([\w_]+) %}/', $content, $matches)) {
            $blocks = $matches[1];
        }
        
        return $blocks;
    }
    
    /**
     * Search entities
     */
    private function searchEntities(string $query): array
    {
        $queryLower = strtolower($query);
        $matches = [];
        
        $entitiesDir = $this->projectDir . '/src/Entity';
        if (!is_dir($entitiesDir)) {
            return [];
        }
        
        try {
            $finder = new Finder();
            $finder->files()->in($entitiesDir)->name('*.php');
            
            foreach ($finder as $file) {
                $className = $file->getBasename('.php');
                $classNameLower = strtolower($className);
                
                if (str_contains($classNameLower, $queryLower) || 
                    str_contains($queryLower, strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className)))) {
                    
                    $content = $file->getContents();
                    
                    // Extract properties
                    $properties = $this->extractEntityProperties($content);
                    
                    $matches[] = [
                        'class' => $className,
                        'properties' => $properties
                    ];
                }
            }
        } catch (\Exception $e) {
            // Ignore finder errors
        }
        
        return array_slice($matches, 0, 3);
    }
    
    /**
     * Extract entity properties
     */
    private function extractEntityProperties(string $content): array
    {
        $properties = [];
        
        if (preg_match_all('/private\s+(?:\??\w+)\s+\$(\w+);/', $content, $matches)) {
            foreach ($matches[1] as $prop) {
                $properties[] = $prop;
            }
        }
        
        return $properties;
    }
    
    /**
     * Validate that a route exists (CRITICAL: never return fake routes)
     */
    public function validateRouteExists(string $routeName): bool
    {
        return $this->deepScanner->routeExists($routeName);
    }
    
    /**
     * Get route suggestions for navigation
     */
    public function suggestRoute(string $destination): ?array
    {
        $result = $this->routeService->detectNavigationUrl("go to $destination");
        
        if ($result && !empty($result['url'])) {
            // CRITICAL: Validate route exists before returning
            $routeName = $result['route_name'] ?? null;
            if ($routeName && !$this->validateRouteExists($routeName)) {
                return null; // Route doesn't exist, don't suggest it
            }
            
            return [
                'destination' => $result['destination'],
                'url' => $result['url'],
                'route_name' => $routeName,
                'matched_alias' => $result['matched_alias'] ?? null,
                'required_roles' => $result['required_roles'] ?? null
            ];
        }
        
        return null;
    }
    
    /**
     * Get form structure for a specific page
     */
    public function getFormStructure(string $pageName): array
    {
        // Search for templates matching page name
        $templatesDir = $this->projectDir . '/templates';
        $forms = [];
        
        if (!is_dir($templatesDir)) {
            return [];
        }
        
        try {
            $finder = new Finder();
            $finder->files()->in($templatesDir)->name('*' . $pageName . '*.twig');
            
            foreach ($finder as $file) {
                $content = $file->getContents();
                $templateForms = $this->extractFormsFromTemplate($content);
                
                if (!empty($templateForms)) {
                    $forms[] = [
                        'template' => $file->getRelativePathname(),
                        'forms' => $templateForms
                    ];
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }
        
        return $forms;
    }
    
    /**
     * Get business rules summary
     */
    public function getBusinessRules(): array
    {
        return [
            'roles' => [
                'Guest' => 'Not logged in',
                'USER' => 'Basic authenticated user',
                'OWNER' => 'Property owner',
                'ADMIN' => 'Administrator',
                'SUPERADMIN' => 'Super administrator',
                'SYNDIC' => 'Syndicate manager'
            ],
            'public_pages' => [
                'main_home', 'frontend_our_team', 'frontend_contact', 
                'auth_sign_in', 'auth_sign_up'
            ],
            'auth_required_pages' => [
                'frontend_profile', 'frontend_settings', 'frontend_forum',
                'app_residence_index', 'app_evenement_index'
            ],
            'admin_pages' => [
                'admin_dashboard'
            ]
        ];
    }
    
    /**
     * Generate search summary
     */
    private function generateSummary(array $results): string
    {
        $parts = [];
        
        if (!empty($results['routes'])) {
            $routeCount = count($results['routes']);
            $topRoute = $results['routes'][0];
            $parts[] = "Found $routeCount route(s), best match: {$topRoute['alias']} at {$topRoute['url']}";
        }
        
        if (!empty($results['templates'])) {
            $parts[] = "Found " . count($results['templates']) . " relevant template(s)";
        }
        
        if (!empty($results['entities'])) {
            $parts[] = "Found " . count($results['entities']) . " entity class(es)";
        }
        
        return implode('. ', $parts) ?: 'No internal matches found';
    }
}
