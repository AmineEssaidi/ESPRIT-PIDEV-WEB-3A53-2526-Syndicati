<?php

namespace App\Service;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Smart Route Mapping Service for AI Navigation
 * DYNAMICALLY discovers routes from Symfony Router - no hardcoded mappings!
 * Uses intelligent pattern matching to categorize routes and generate aliases
 */
class SmartRouteMappingService
{
    private RouterInterface $router;
    private RequestStack $requestStack;
    private ?RouteCollection $routeCollection = null;
    
    /**
     * Patterns to exclude from navigation (internal, API, admin routes that shouldn't be navigated)
     */
    private array $excludePatterns = [
        '/^_/',                 // Internal routes (starting with _)
        '/^api_/',              // API routes
        '/^webauthn_/',         // WebAuthn internal routes
        '/^oauth_/',            // OAuth internal routes
        '/^2fa_verify/',        // 2FA verify handlers
        '/^2fa_cancel/',        // 2FA cancel handlers
        '/^admin_.*_edit$/',    // Admin edit forms
        '/^admin_.*_delete$/',  // Admin delete actions
        '/^admin_.*_add$/',     // Admin add forms
        '/_add$/',              // Add forms
        '/_edit$/',             // Edit forms
        '/_delete$/',           // Delete actions
        '/_new$/',              // New forms
        '/_upload$/',           // Upload handlers
        '/_ajax$/',             // AJAX endpoints
        '/_overlay/',           // Overlay handlers
        '/_check$/',            // Check endpoints
        '/_update$/',           // Update handlers
        '/api$/',               // API endpoints
        '/^app_.*_new$/',       // Entity creation forms
        '/^app_.*_edit$/',      // Entity edit forms
        '/^app_.*_delete$/',    // Entity delete forms
        '/^app_.*_upload$/',    // Upload handlers
        '/^forum_comment_/',    // Forum comment actions

        // CRITICAL: never navigate user to reclamation index routes.
        // Reclamation is handled via modals/forms on existing pages, not via a standalone page.
        '/^app_reclamation_/',  // All reclamation routes (index/show/new/delete)
    ];
    
    /**
     * Route patterns that indicate admin-only access
     */
    private array $adminRoutePatterns = [
        '/^admin_dashboard$/',
        '/^admin_[^_]+$/',    // Admin main routes (but not edit/delete forms)
    ];
    
    /**
     * Natural language aliases for common route patterns
     */
    private array $routeAliasPatterns = [
        'main_home' => ['home', 'main', 'main home', 'accueil', 'index'],
        'frontend_profile' => ['profile', 'my profile', 'account', 'my account', 'user'],
        'frontend_settings' => ['settings', 'preferences', 'config'],
        'auth_sign_in' => ['login', 'signin', 'sign in', 'log in'],
        'auth_sign_up' => ['signup', 'sign up', 'register', 'create account'],
        'auth_logout' => ['logout', 'sign out', 'log out', 'exit'],
        'admin_dashboard' => ['dashboard', 'admin', 'admin dashboard', 'backoffice', 'control panel'],
        'frontend_forum' => ['forum', 'forums', 'discussion', 'discussions', 'community', 'publications', 'posts'],
        'app_residence_index' => ['residence', 'residences', 'apartment', 'apartments', 'housing', 'properties'],
        'app_evenement_index' => ['event', 'events', 'evenement', 'evenements', 'calendar'],
        'frontend_syndicat' => ['syndicat', 'syndicats', 'management'],
        'app_reclamation_index' => ['reclamation', 'reclamations', 'complaint', 'complaints', 'claims'],
        'frontend_our_team' => ['about', 'team', 'our team', 'about us'],
        'frontend_contact' => ['contact', 'help', 'support', 'contact us'],
        'onboarding' => ['onboarding', 'welcome', 'getting started', 'start'],
        '2fa_login' => ['2fa', 'two factor', 'two factor auth'],
    ];
    
    public function __construct(RouterInterface $router, RequestStack $requestStack)
    {
        $this->router = $router;
        $this->requestStack = $requestStack;
    }
    
    /**
     * Get all routes from Symfony Router dynamically
     */
    private function getRouteCollection(): RouteCollection
    {
        if ($this->routeCollection === null) {
            $this->routeCollection = $this->router->getRouteCollection();
        }
        return $this->routeCollection;
    }
    
    /**
     * Check if a route name should be excluded from navigation
     */
    private function shouldExcludeRoute(string $routeName): bool
    {
        foreach ($this->excludePatterns as $pattern) {
            if (preg_match($pattern, $routeName)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Determine required roles for a route based on naming patterns
     */
    private function determineRouteRoles(string $routeName): ?array
    {
        // Public routes (no login required)
        $publicPatterns = [
            '/^main_home$/',
            '/^frontend_our_team$/',
            '/^frontend_contact$/',
            '/^auth_sign_/',
            '/^user_signup$/',
            '/^app_residence_index$/',
            '/^app_evenement_index$/',
            '/^frontend_forum$/',
            '/^app_.*_show$/', // Public detail pages
        ];
        
        foreach ($publicPatterns as $pattern) {
            if (preg_match($pattern, $routeName)) {
                return null; // Public access
            }
        }
        
        // Check for admin routes
        foreach ($this->adminRoutePatterns as $pattern) {
            if (preg_match($pattern, $routeName)) {
                return ['OWNER', 'ADMIN', 'SUPERADMIN', 'SYNDIC'];
            }
        }
        
        // Default: requires authentication
        return ['USER', 'OWNER', 'ADMIN', 'SUPERADMIN', 'SYNDIC'];
    }
    
    /**
     * Generate natural language aliases for a route name
     */
    private function generateAliases(string $routeName): array
    {
        $aliases = [];
        
        // Check predefined patterns first
        if (isset($this->routeAliasPatterns[$routeName])) {
            $aliases = $this->routeAliasPatterns[$routeName];
        }
        
        // Generate intelligent alias from route name
        $generated = $this->parseRouteNameToReadable($routeName);
        if ($generated && !in_array(strtolower($generated), array_map('strtolower', $aliases))) {
            $aliases[] = strtolower($generated);
        }
        
        // Add the route name itself (with underscores replaced)
        $aliases[] = str_replace('_', ' ', $routeName);
        
        return array_unique($aliases);
    }
    
    /**
     * Parse a route name to generate human-readable aliases
     */
    private function parseRouteNameToReadable(string $routeName): ?string
    {
        $parts = explode('_', $routeName);
        
        // Remove common prefixes
        $prefixesToRemove = ['frontend', 'app', 'auth'];
        if (in_array($parts[0] ?? '', $prefixesToRemove, true)) {
            array_shift($parts);
        }
        
        // Remove common suffixes
        $suffixesToRemove = ['index', 'new', 'edit', 'delete', 'show', 'list'];
        if (in_array(end($parts), $suffixesToRemove, true)) {
            array_pop($parts);
        }
        
        if (empty($parts)) {
            return null;
        }
        
        // Convert to readable format
        return ucwords(implode(' ', $parts));
    }
    
    /**
     * Build dynamic route mappings from all Symfony routes
     */
    private function buildDynamicRouteMappings(): array
    {
        $mappings = [];
        $routes = $this->getRouteCollection();
        
        foreach ($routes->all() as $routeName => $route) {
            // Skip excluded routes
            if ($this->shouldExcludeRoute($routeName)) {
                continue;
            }
            
            // Try to generate URL for this route
            try {
                $url = $this->router->generate($routeName);
            } catch (\Exception $e) {
                // Route requires parameters, skip it for navigation
                continue;
            }
            
            // Determine required roles
            $roles = $this->determineRouteRoles($routeName);
            
            // Generate aliases
            $aliases = $this->generateAliases($routeName);
            
            // Add mapping for each alias
            foreach ($aliases as $alias) {
                $alias = strtolower(trim($alias));
                if (!empty($alias) && !isset($mappings[$alias])) {
                    $mappings[$alias] = [
                        'route' => $routeName,
                        'url' => $url,
                        'roles' => $roles,
                        'path' => $route->getPath(),
                    ];
                }
            }
        }
        
        return $mappings;
    }
    
    /**
     * Check if current user has required role for a route
     */
    public function userHasAccess(?array $requiredRoles): bool
    {
        if ($requiredRoles === null) {
            return true;
        }
        
        $session = $this->requestStack->getSession();
        $user = $session->get('user');
        
        if (!$user || !is_array($user)) {
            return false;
        }
        
        $userRole = $user['role'] ?? null;
        if (!$userRole) {
            return false;
        }
        
        return in_array($userRole, $requiredRoles, true);
    }
    
    /**
     * Get the actual Symfony route URL from a natural language term
     */
    public function getRouteUrl(string $term, bool $checkAccess = true): ?string
    {
        $term = strtolower(trim($term));
        $mappings = $this->buildDynamicRouteMappings();
        
        // Direct mapping
        if (isset($mappings[$term])) {
            $mapping = $mappings[$term];
            
            if ($checkAccess && !$this->userHasAccess($mapping['roles'])) {
                return null;
            }
            
            return $mapping['url'];
        }
        
        // Try to find partial matches
        foreach ($mappings as $key => $mapping) {
            if (strpos($term, $key) !== false || strpos($key, $term) !== false) {
                if ($checkAccess && !$this->userHasAccess($mapping['roles'])) {
                    continue;
                }
                
                return $mapping['url'];
            }
        }
        
        return null;
    }
    
    /**
     * Detect navigation intent from message and return proper URL with access check
     */
    public function detectNavigationUrl(string $message): ?array
    {
        $message = strtolower(trim($message));
        $mappings = $this->buildDynamicRouteMappings();
        
        // Common navigation patterns
        $patterns = [
            '/(?:go to|take me to|navigate to|show me|open|visit)\s+(?:the\s+)?(.+)/i',
            '/^(?:go to|take me to|navigate to|show me|open|visit)\s+(.+)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $destination = strtolower(trim($matches[1] ?? ''));
                
                // First try exact match
                if (isset($mappings[$destination])) {
                    $mapping = $mappings[$destination];
                    if (!$this->userHasAccess($mapping['roles'])) {
                        return [
                            'destination' => $destination,
                            'url' => null,
                            'access_denied' => true,
                            'route_name' => $mapping['route'],
                            'required_roles' => $mapping['roles'],
                            'user_role' => $this->getCurrentUserRole() ?? 'Guest',
                        ];
                    }
                    return [
                        'destination' => $destination,
                        'url' => $mapping['url'],
                        'route_name' => $mapping['route'],
                        'required_roles' => $mapping['roles'],
                        'matched_alias' => $destination,
                    ];
                }
                
                // Then try fuzzy matching with higher threshold
                foreach ($mappings as $alias => $mapping) {
                    $aliasLower = strtolower($alias);
                    // Exact match
                    if ($aliasLower === $destination) {
                        if (!$this->userHasAccess($mapping['roles'])) {
                            return [
                                'destination' => $destination,
                                'url' => null,
                                'access_denied' => true,
                                'route_name' => $mapping['route'],
                                'required_roles' => $mapping['roles'],
                                'user_role' => $this->getCurrentUserRole() ?? 'Guest',
                            ];
                        }
                        return [
                            'destination' => $destination,
                            'url' => $mapping['url'],
                            'route_name' => $mapping['route'],
                            'required_roles' => $mapping['roles'],
                            'matched_alias' => $alias,
                        ];
                    }
                    // Check if destination is contained in alias (e.g., "home" in "main home")
                    if (strpos($aliasLower, $destination) !== false && strlen($destination) >= 3) {
                        if (!$this->userHasAccess($mapping['roles'])) {
                            return [
                                'destination' => $destination,
                                'url' => null,
                                'access_denied' => true,
                                'route_name' => $mapping['route'],
                                'required_roles' => $mapping['roles'],
                                'user_role' => $this->getCurrentUserRole() ?? 'Guest',
                            ];
                        }
                        return [
                            'destination' => $destination,
                            'url' => $mapping['url'],
                            'route_name' => $mapping['route'],
                            'required_roles' => $mapping['roles'],
                            'matched_alias' => $alias,
                        ];
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get all available routes for AI context (only accessible ones)
     */
    public function getAvailableRoutes(): array
    {
        $mappings = $this->buildDynamicRouteMappings();
        $routes = [];
        
        foreach ($mappings as $alias => $mapping) {
            // Skip if user doesn't have access
            if (!$this->userHasAccess($mapping['roles'])) {
                continue;
            }
            
            $routes[$alias] = [
                'route_name' => $mapping['route'],
                'url' => $mapping['url'],
                'roles' => $mapping['roles'],
                'path' => $mapping['path'],
            ];
        }
        
        return $routes;
    }
    
    /**
     * Get routes that require authentication
     */
    public function getAuthenticatedRoutes(): array
    {
        return array_filter($this->getAvailableRoutes(), function($route) {
            return $route['roles'] !== null;
        });
    }
    
    /**
     * Get routes that are public (no authentication required)
     */
    public function getPublicRoutes(): array
    {
        return array_filter($this->getAvailableRoutes(), function($route) {
            return $route['roles'] === null;
        });
    }
    
    /**
     * Get admin-only routes
     */
    public function getAdminRoutes(): array
    {
        $adminRoles = ['OWNER', 'ADMIN', 'SUPERADMIN', 'SYNDIC'];
        return array_filter($this->getAvailableRoutes(), function($route) use ($adminRoles) {
            return $route['roles'] !== null && array_intersect($route['roles'], $adminRoles);
        });
    }
    
    /**
     * Get route context for AI prompt with user-specific access info
     */
    public function getRouteContextForAI(): string
    {
        $routes = $this->getAvailableRoutes();
        
        if (empty($routes)) {
            return '';
        }
        
        $user = $this->security->getUser();
        $userRole = $user && method_exists($user, 'getRoleUser') ? $user->getRoleUser() : 'Guest';
        
        // Group by category for better readability
        $grouped = $this->groupRoutesByCategory($routes);
        
        $context = "\n\nDYNAMICALLY DISCOVERED ROUTES (User Role: {$userRole}):\n";
        $context .= "These routes were automatically discovered from the application:\n";
        
        foreach ($grouped as $category => $categoryRoutes) {
            $context .= "\n{$category}:\n";
            foreach ($categoryRoutes as $alias => $info) {
                $roleInfo = $info['roles'] ? ' [' . implode(', ', $info['roles']) . ']' : ' [Public]';
                $context .= "  - '{$alias}' → {$info['url']}{$roleInfo}\n";
            }
        }
        
        $context .= "\nNAVIGATION INSTRUCTIONS:\n";
        $context .= "1. Routes are discovered dynamically from the application - no hardcoded list\n";
        $context .= "2. Match user requests to the most appropriate route\n";
        $context .= "3. Use exact URLs provided, not guessed paths\n";
        $context .= "4. Current user role: {$userRole}\n";
        $context .= "5. If user lacks access, suggest they log in or request access\n";
        
        return $context;
    }
    
    /**
     * Group routes by category for better organization
     */
    private function groupRoutesByCategory(array $routes): array
    {
        $grouped = [
            'Main' => [],
            'User Account' => [],
            'Public Content' => [],
            'Management' => [],
            'Admin' => [],
            'Other' => [],
        ];
        
        foreach ($routes as $alias => $info) {
            $routeName = $info['route_name'];
            
            if (str_contains($routeName, 'main_home')) {
                $grouped['Main'][$alias] = $info;
            } elseif (str_contains($routeName, 'profile') || str_contains($routeName, 'auth') || str_contains($routeName, 'login') || str_contains($routeName, 'settings') || str_contains($routeName, 'logout') || str_contains($routeName, 'signup')) {
                $grouped['User Account'][$alias] = $info;
            } elseif (str_contains($routeName, 'admin_dashboard')) {
                $grouped['Admin'][$alias] = $info;
            } elseif (str_contains($routeName, 'residence') || str_contains($routeName, 'forum') || str_contains($routeName, 'evenement') || str_contains($routeName, 'our_team') || str_contains($routeName, 'contact')) {
                $grouped['Public Content'][$alias] = $info;
            } elseif (str_contains($routeName, 'syndicat') || str_contains($routeName, 'reclamation')) {
                $grouped['Management'][$alias] = $info;
            } else {
                $grouped['Other'][$alias] = $info;
            }
        }
        
        // Remove empty groups
        return array_filter($grouped, fn($g) => !empty($g));
    }

    /**
     * Get the concrete URL for a given Symfony route name.
     * This is used by deep template scanners that only know the route name.
     */
    public function getUrlForRouteName(string $routeName): ?string
    {
        $routes = $this->getRouteCollection();
        if (!$routes->get($routeName)) {
            return null;
        }

        try {
            return $this->router->generate($routeName);
        } catch (\Throwable) {
            return null;
        }
    }
    
    /**
     * Check if current user is an admin
     */
    public function isCurrentUserAdmin(): bool
    {
        $session = $this->requestStack->getSession();
        $user = $session->get('user');
        
        if (!$user || !is_array($user)) {
            return false;
        }
        
        $userRole = $user['role'] ?? null;
        $adminRoles = ['OWNER', 'ADMIN', 'SUPERADMIN'];
        return in_array($userRole, $adminRoles, true);
    }
    
    /**
     * Get current user role
     */
    public function getCurrentUserRole(): ?string
    {
        $session = $this->requestStack->getSession();
        $user = $session->get('user');
        
        if (!$user || !is_array($user)) {
            return null;
        }
        
        return $user['role'] ?? null;
    }
    
    /**
     * Get all discovered routes as simple array for debugging
     */
    public function getDiscoveredRoutes(): array
    {
        $mappings = $this->buildDynamicRouteMappings();
        $result = [];
        
        foreach ($mappings as $alias => $mapping) {
            if (!isset($result[$mapping['route']])) {
                $result[$mapping['route']] = [
                    'aliases' => [],
                    'url' => $mapping['url'],
                    'roles' => $mapping['roles'],
                ];
            }
            $result[$mapping['route']]['aliases'][] = $alias;
        }
        
        return $result;
    }
}
