<?php

namespace App\Service;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Routing\RouterInterface;

/**
 * Deep Codebase Scanner - Comprehensive text-to-route mapping
 * 
 * Scans the ENTIRE codebase (controllers, templates, configs) to build
 * a semantic index mapping text content to actual Symfony routes.
 * 
 * CRITICAL: Only returns routes that actually exist in Symfony's router.
 * Never generates or guesses URLs - only uses validated routes.
 */
class DeepCodebaseScanner
{
    private RouterInterface $router;
    private SmartRouteMappingService $routeService;
    private string $projectDir;
    
    // Cache of validated routes (route name => URL)
    private ?array $validatedRoutes = null;
    
    // Text index: query terms => [route_name => score]
    private ?array $textIndex = null;
    
    public function __construct(
        RouterInterface $router,
        SmartRouteMappingService $routeService,
        string $projectDir
    ) {
        $this->router = $router;
        $this->routeService = $routeService;
        $this->projectDir = $projectDir;
    }
    
    /**
     * Build comprehensive text-to-route index from entire codebase
     */
    public function buildTextIndex(): array
    {
        if ($this->textIndex !== null) {
            return $this->textIndex;
        }
        
        $this->textIndex = [];
        $validatedRoutes = $this->getValidatedRoutes();
        
        // 1. Scan PHP Controllers for route names and descriptions
        $this->scanControllers($validatedRoutes);
        
        // 2. Scan Twig templates for text content
        $this->scanTemplates($validatedRoutes);
        
        // 3. Scan route configuration files
        $this->scanRouteConfigs($validatedRoutes);
        
        // 4. Scan entity classes for domain terms
        $this->scanEntities($validatedRoutes);
        
        return $this->textIndex;
    }
    
    
    /**
     * Scan PHP controllers for route definitions and text content
     */
    private function scanControllers(array $validatedRoutes): void
    {
        $controllersDir = $this->projectDir . '/src/Controller';
        if (!is_dir($controllersDir)) {
            return;
        }
        
        try {
            $finder = new Finder();
            $finder->files()->in($controllersDir)->name('*.php');
            
            foreach ($finder as $file) {
                $content = $file->getContents();
                $relativePath = $file->getRelativePathname();
                
                // Extract route names from #[Route] attributes
                if (preg_match_all('/#\[Route\([\'"]([^\'"]+)[\'"]/', $content, $routeMatches)) {
                    foreach ($routeMatches[1] as $routePath) {
                        // Find route name that matches this path
                        foreach ($validatedRoutes as $routeName => $routeInfo) {
                            if ($routeInfo['path'] === $routePath || str_contains($routeInfo['path'], $routePath)) {
                                $this->indexRouteText($routeName, $relativePath, 90);
                                
                                // Extract class and method names as keywords
                                if (preg_match('/class\s+(\w+)/', $content, $classMatch)) {
                                    $className = strtolower($classMatch[1]);
                                    $this->indexTextForRoute($routeName, $className, 60);
                                }
                                
                                if (preg_match('/function\s+(\w+)\s*\(/', $content, $methodMatch)) {
                                    $methodName = strtolower($methodMatch[1]);
                                    $this->indexTextForRoute($routeName, $methodName, 50);
                                }
                            }
                        }
                    }
                }
                
                // Extract route names from route names in comments or docblocks
                if (preg_match_all('/@Route\([\'"]([\w_]+)[\'"]/', $content, $nameMatches)) {
                    foreach ($nameMatches[1] as $routeName) {
                        if (isset($validatedRoutes[$routeName])) {
                            $this->indexRouteText($routeName, $relativePath, 85);
                        }
                    }
                }
                
                // Extract text from docblocks and comments
                if (preg_match_all('/\/\*\*.*?\*\//s', $content, $docMatches)) {
                    foreach ($docMatches[0] as $doc) {
                        $text = strip_tags($doc);
                        $words = $this->extractKeywords($text);
                        foreach ($words as $word) {
                            foreach (array_keys($validatedRoutes) as $routeName) {
                                if (str_contains(strtolower($routeName), strtolower($word))) {
                                    $this->indexTextForRoute($routeName, $word, 40);
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // Ignore errors
        }
    }
    
    /**
     * Scan Twig templates for text content and route references
     */
    private function scanTemplates(array $validatedRoutes): void
    {
        $templatesDir = $this->projectDir . '/templates';
        if (!is_dir($templatesDir)) {
            return;
        }
        
        try {
            $finder = new Finder();
            $finder->files()->in($templatesDir)->name('*.twig');
            
            foreach ($finder as $file) {
                $content = $file->getContents();
                $relativePath = $file->getRelativePathname();
                
                // Extract route names from path() and url() calls
                if (preg_match_all('/path\([\'"]([\w_]+)[\'"]/', $content, $pathMatches)) {
                    foreach ($pathMatches[1] as $routeName) {
                        if (isset($validatedRoutes[$routeName])) {
                            $this->indexRouteText($routeName, $relativePath, 80);
                        }
                    }
                }
                
                if (preg_match_all('/url\([\'"]([\w_]+)[\'"]/', $content, $urlMatches)) {
                    foreach ($urlMatches[1] as $routeName) {
                        if (isset($validatedRoutes[$routeName])) {
                            $this->indexRouteText($routeName, $relativePath, 80);
                        }
                    }
                }
                
                // Extract text from HTML content
                $htmlText = strip_tags($content);
                $htmlText = preg_replace('/\{%.*?%\}/s', '', $htmlText); // Remove Twig blocks
                $htmlText = preg_replace('/\{\{.*?\}\}/s', '', $htmlText); // Remove Twig expressions
                
                // Extract keywords from text
                $keywords = $this->extractKeywords($htmlText);
                
                // Match keywords to route names
                foreach ($keywords as $keyword) {
                    foreach ($validatedRoutes as $routeName => $routeInfo) {
                        $routeNameLower = strtolower($routeName);
                        $pathLower = strtolower($routeInfo['path']);
                        
                        if (str_contains($routeNameLower, $keyword) || str_contains($pathLower, $keyword)) {
                            $this->indexTextForRoute($routeName, $keyword, 70);
                        }
                    }
                }
                
                // Extract button/link text
                if (preg_match_all('/<(?:button|a|li|label)[^>]*>([^<]+)<\/(?:button|a|li|label)>/i', $content, $textMatches)) {
                    foreach ($textMatches[1] as $buttonText) {
                        $buttonText = trim(strip_tags($buttonText));
                        if (strlen($buttonText) > 2) {
                            $keywords = $this->extractKeywords($buttonText);
                            foreach ($keywords as $keyword) {
                                // Try to find route that matches this button text
                                foreach ($validatedRoutes as $routeName => $routeInfo) {
                                    if ($this->textMatchesRoute($keyword, $routeName, $routeInfo['path'])) {
                                        $this->indexTextForRoute($routeName, $keyword, 85);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // Ignore errors
        }
    }
    
    /**
     * Scan route configuration files
     */
    private function scanRouteConfigs(array $validatedRoutes): void
    {
        $configDir = $this->projectDir . '/config';
        if (!is_dir($configDir)) {
            return;
        }
        
        try {
            $finder = new Finder();
            $finder->files()->in($configDir)->name('routes*.yaml')->name('routes*.yml');
            
            foreach ($finder as $file) {
                $content = $file->getContents();
                
                // Extract route names and paths from YAML
                if (preg_match_all('/^(\w+):\s*$/m', $content, $routeNameMatches)) {
                    foreach ($routeNameMatches[1] as $routeName) {
                        if (isset($validatedRoutes[$routeName])) {
                            $this->indexRouteText($routeName, $file->getRelativePathname(), 95);
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // Ignore errors
        }
    }
    
    /**
     * Scan entity classes for domain terms
     */
    private function scanEntities(array $validatedRoutes): void
    {
        $entitiesDir = $this->projectDir . '/src/Entity';
        if (!is_dir($entitiesDir)) {
            return;
        }
        
        try {
            $finder = new Finder();
            $finder->files()->in($entitiesDir)->name('*.php');
            
            foreach ($finder as $file) {
                $content = $file->getContents();
                $className = $file->getBasename('.php');
                $classNameLower = strtolower($className);
                
                // Match entity names to routes
                foreach ($validatedRoutes as $routeName => $routeInfo) {
                    $routeNameLower = strtolower($routeName);
                    if (str_contains($routeNameLower, $classNameLower)) {
                        $this->indexTextForRoute($routeName, $classNameLower, 75);
                    }
                }
            }
        } catch (\Throwable) {
            // Ignore errors
        }
    }
    
    /**
     * Search codebase for routes matching query text
     */
    public function searchRoutes(string $query): array
    {
        $this->buildTextIndex();
        $queryLower = strtolower(trim($query));
        $queryWords = $this->extractKeywords($queryLower);
        
        $matches = [];
        $validatedRoutes = $this->getValidatedRoutes();
        
        // Score each route based on text index
        foreach ($validatedRoutes as $routeName => $routeInfo) {
            $score = 0;
            
            // Check exact query match
            if (isset($this->textIndex[$queryLower][$routeName])) {
                $score += $this->textIndex[$queryLower][$routeName];
            }
            
            // Check individual word matches
            foreach ($queryWords as $word) {
                if (isset($this->textIndex[$word][$routeName])) {
                    $score += $this->textIndex[$word][$routeName];
                }
            }
            
            // Check route name and path matches
            $routeNameLower = strtolower($routeName);
            $pathLower = strtolower($routeInfo['path']);
            
            if ($routeNameLower === $queryLower) {
                $score += 100;
            } elseif (str_contains($routeNameLower, $queryLower)) {
                $score += 90;
            } elseif (str_contains($pathLower, $queryLower)) {
                $score += 60;
            }
            
            // Check word-by-word matching
            foreach ($queryWords as $word) {
                if (str_contains($routeNameLower, $word)) {
                    $score += 50;
                }
                if (str_contains($pathLower, $word)) {
                    $score += 30;
                }
            }
            
            if ($score > 0) {
                $matches[] = [
                    'route_name' => $routeName,
                    'url' => $routeInfo['url'],
                    'path' => $routeInfo['path'],
                    'score' => $score,
                    'type' => 'codebase_match'
                ];
            }
        }
        
        // Sort by score descending
        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);
        
        return array_slice($matches, 0, 10);
    }
    
    /**
     * Index text for a specific route
     */
    private function indexTextForRoute(string $routeName, string $text, int $score): void
    {
        $textLower = strtolower(trim($text));
        if (empty($textLower) || strlen($textLower) < 2) {
            return;
        }
        
        if (!isset($this->textIndex[$textLower])) {
            $this->textIndex[$textLower] = [];
        }
        
        if (!isset($this->textIndex[$textLower][$routeName])) {
            $this->textIndex[$textLower][$routeName] = 0;
        }
        
        $this->textIndex[$textLower][$routeName] = max(
            $this->textIndex[$textLower][$routeName],
            $score
        );
    }
    
    /**
     * Index route text from file path
     */
    private function indexRouteText(string $routeName, string $filePath, int $baseScore): void
    {
        $pathParts = explode('/', $filePath);
        $fileName = end($pathParts);
        $fileName = preg_replace('/\.(php|twig|yaml|yml)$/', '', $fileName);
        
        $keywords = $this->extractKeywords($fileName);
        foreach ($keywords as $keyword) {
            $this->indexTextForRoute($routeName, $keyword, $baseScore);
        }
    }
    
    /**
     * Extract keywords from text
     */
    private function extractKeywords(string $text): array
    {
        // Remove common words
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from'];
        
        // Split into words
        $words = preg_split('/[\s\-_]+/', strtolower($text));
        
        // Filter and clean
        $keywords = [];
        foreach ($words as $word) {
            $word = preg_replace('/[^a-z0-9]/', '', $word);
            if (strlen($word) >= 3 && !in_array($word, $stopWords)) {
                $keywords[] = $word;
            }
        }
        
        return array_unique($keywords);
    }
    
    /**
     * Check if text matches route name or path
     */
    private function textMatchesRoute(string $text, string $routeName, string $path): bool
    {
        $textLower = strtolower($text);
        $routeNameLower = strtolower($routeName);
        $pathLower = strtolower($path);
        
        return str_contains($routeNameLower, $textLower) || str_contains($pathLower, $textLower);
    }
    
    /**
     * Check if route should be excluded
     */
    /**
     * Exclude list: app uses modals for all CRUD (no separate Twig pages for add/edit).
     * Never suggest API, reclamation index, or any _new/_edit/_delete route.
     */
    private function shouldExcludeRoute(string $routeName): bool
    {
        $excludePatterns = [
            '/^_/',
            '/^api_/',
            '/^webauthn_/',
            '/^oauth_/',
            '/^2fa_verify/',
            '/^2fa_cancel/',
            '/^admin_.*_edit$/',
            '/^admin_.*_delete$/',
            '/^admin_.*_add$/',
            '/_add$/',
            '/_edit$/',
            '/_delete$/',
            '/_new$/',
            '/_upload$/',
            '/_ajax$/',
            '/_overlay/',
            '/_check$/',
            '/_update$/',
            '/api$/',
            '/^app_.*_new$/',
            '/^app_.*_edit$/',
            '/^app_.*_delete$/',
            '/^app_.*_upload$/',
            '/^forum_comment_/',
            '/^app_reclamation_/',  // Complaints = modals on same page, not standalone URL
        ];
        
        foreach ($excludePatterns as $pattern) {
            if (preg_match($pattern, $routeName)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get validated route URL (only if route exists)
     */
    public function getValidatedRouteUrl(string $routeName): ?string
    {
        $validatedRoutes = $this->getValidatedRoutes();
        return $validatedRoutes[$routeName]['url'] ?? null;
    }
    
    /**
     * Check if route exists and is valid
     */
    public function routeExists(string $routeName): bool
    {
        $validatedRoutes = $this->getValidatedRoutes();
        return isset($validatedRoutes[$routeName]);
    }
    
    /**
     * Get all validated routes (for internal use)
     */
    public function getValidatedRoutes(): array
    {
        if ($this->validatedRoutes === null) {
            $this->validatedRoutes = [];
            $routeCollection = $this->router->getRouteCollection();
            
            foreach ($routeCollection->all() as $routeName => $route) {
                // Skip excluded routes
                if ($this->shouldExcludeRoute($routeName)) {
                    continue;
                }
                
                // Try to generate URL - only include routes that can be generated
                try {
                    $url = $this->router->generate($routeName);
                    $this->validatedRoutes[$routeName] = [
                        'url' => $url,
                        'path' => $route->getPath(),
                        'methods' => $route->getMethods() ?: ['GET'],
                    ];
                } catch (\Throwable) {
                    // Route requires parameters, skip it
                    continue;
                }
            }
        }
        
        return $this->validatedRoutes;
    }
}
