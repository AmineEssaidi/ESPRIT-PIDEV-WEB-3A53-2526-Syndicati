<?php

namespace App\Service;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Agent Memory Service - Persistence for Syndicati Agent
 * 
 * Stores:
 * - Past interactions and workflows
 * - User preferences
 * - Learned selectors and patterns
 * - Session state across agent runs
 */
class AgentMemoryService
{
    private CacheInterface $cache;
    private string $namespace;
    
    public function __construct(?CacheInterface $cache = null, string $namespace = 'agent_memory')
    {
        $this->cache = $cache ?? new FilesystemAdapter($namespace, 0, sys_get_temp_dir());
        $this->namespace = $namespace;
    }
    
    /**
     * Store a memory entry
     */
    public function store(array $data, ?string $key = null): string
    {
        $key = $key ?? $this->generateKey($data);
        $key = $this->sanitizeCacheKey($key);
        
        // Add metadata
        $data['_stored_at'] = time();
        $data['_key'] = $key;
        
        // Store in cache
        $item = $this->cache->getItem($key);
        $item->set($data);
        $item->expiresAfter(86400 * 30); // 30 days
        $this->cache->save($item);
        
        return $key;
    }
    
    /**
     * Recall memories by query
     */
    public function recall(string $query, int $limit = 5): array
    {
        $queryLower = strtolower($query);
        $matches = [];
        
        // Get all cache keys (this is a simplified approach)
        // In production, use a proper index or search system
        $allMemories = $this->getAllMemories();
        
        foreach ($allMemories as $key => $memory) {
            $score = $this->calculateRelevanceScore($memory, $queryLower);
            
            if ($score > 0) {
                $matches[] = [
                    'key' => $key,
                    'memory' => $memory,
                    'score' => $score
                ];
            }
        }
        
        // Sort by score
        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);
        
        return array_slice($matches, 0, $limit);
    }
    
    /**
     * Get specific memory by key
     */
    public function get(string $key): ?array
    {
        $item = $this->cache->getItem($key);
        
        if ($item->isHit()) {
            return $item->get();
        }
        
        return null;
    }
    
    /**
     * Update existing memory
     */
    public function update(string $key, array $updates): bool
    {
        $existing = $this->get($key);
        
        if (!$existing) {
            return false;
        }
        
        $updated = array_merge($existing, $updates);
        $updated['_updated_at'] = time();
        
        $item = $this->cache->getItem($key);
        $item->set($updated);
        $this->cache->save($item);
        
        return true;
    }
    
    /**
     * Delete memory
     */
    public function delete(string $key): bool
    {
        return $this->cache->delete($key);
    }
    
    /**
     * Store user preference
     */
    public function storePreference(string $userId, string $key, $value): void
    {
        $prefKey = "preference:$userId:$key";
        
        $this->store([
            'type' => 'preference',
            'user_id' => $userId,
            'key' => $key,
            'value' => $value
        ], $prefKey);
    }
    
    /**
     * Get user preference
     */
    public function getPreference(string $userId, string $key, $default = null)
    {
        $prefKey = "preference:$userId:$key";
        $memory = $this->get($prefKey);
        
        return $memory['value'] ?? $default;
    }
    
    /**
     * Store a successful workflow pattern
     */
    public function storeWorkflowPattern(string $goalType, array $steps, array $selectors): void
    {
        $this->store([
            'type' => 'workflow_pattern',
            'goal_type' => $goalType,
            'steps' => $steps,
            'selectors' => $selectors,
            'usage_count' => 1
        ], "pattern:$goalType:" . md5(serialize($steps)));
    }
    
    /**
     * Find workflow patterns for goal type
     */
    public function findWorkflowPatterns(string $goalType): array
    {
        $patterns = [];
        $allMemories = $this->getAllMemories();
        
        foreach ($allMemories as $key => $memory) {
            if (($memory['type'] ?? '') === 'workflow_pattern' && 
                ($memory['goal_type'] ?? '') === $goalType) {
                $patterns[] = $memory;
            }
        }
        
        return $patterns;
    }
    
    /**
     * Store a good selector for an element
     */
    public function storeSelector(string $page, string $elementDescription, string $selector, int $reliability = 100): void
    {
        $this->store([
            'type' => 'selector',
            'page' => $page,
            'element' => $elementDescription,
            'selector' => $selector,
            'reliability' => $reliability
        ], "selector:$page:" . md5($elementDescription));
    }
    
    /**
     * Find stored selector
     */
    public function findSelector(string $page, string $elementDescription): ?string
    {
        $key = "selector:$page:" . md5($elementDescription);
        $memory = $this->get($key);
        
        return $memory['selector'] ?? null;
    }
    
    /**
     * Get session state
     */
    public function getSessionState(string $sessionId): array
    {
        $memory = $this->get("session:$sessionId");
        return $memory ?? [];
    }
    
    /**
     * Save session state
     */
    public function saveSessionState(string $sessionId, array $state): void
    {
        $this->store($state, "session:$sessionId");
    }
    
    /**
     * Clear session state
     */
    public function clearSessionState(string $sessionId): void
    {
        $this->delete("session:$sessionId");
    }
    
    /**
     * Generate memory key
     */
    private function generateKey(array $data): string
    {
        $type = $data['type'] ?? 'memory';
        $timestamp = time();
        $random = bin2hex(random_bytes(4));
        
        return "$type:$timestamp:$random";
    }

    private function sanitizeCacheKey(string $key): string
    {
        // Symfony Cache keys must not contain: {}()/\\@:
        // Replace reserved characters with '_', keep it readable.
        return preg_replace('/[\{\}\(\)\/\\\\@:\\s]/', '_', $key) ?? $key;
    }
    
    /**
     * Calculate relevance score for memory
     */
    private function calculateRelevanceScore(array $memory, string $queryLower): int
    {
        $score = 0;
        
        // Check goal
        if (isset($memory['goal'])) {
            similar_text(strtolower($memory['goal']), $queryLower, $percent);
            $score += (int)($percent / 10);
        }
        
        // Check page/URL
        if (isset($memory['url'])) {
            if (str_contains(strtolower($memory['url']), $queryLower)) {
                $score += 20;
            }
        }
        
        // Check action type match
        if (isset($memory['step']['tool'])) {
            if (str_contains(strtolower($memory['step']['tool']), $queryLower)) {
                $score += 10;
            }
        }
        
        // Recency boost (more recent = higher score)
        if (isset($memory['_stored_at'])) {
            $age = time() - $memory['_stored_at'];
            if ($age < 3600) { // Within 1 hour
                $score += 30;
            } elseif ($age < 86400) { // Within 1 day
                $score += 15;
            }
        }
        
        return $score;
    }
    
    /**
     * Get all memories (for simple search)
     * In production, use a proper database or search index
     */
    private function getAllMemories(): array
    {
        // This is a simplified implementation
        // In production, use Redis, Elasticsearch, or database
        return [];
    }
    
    /**
     * Get memory statistics
     */
    public function getStats(): array
    {
        return [
            'namespace' => $this->namespace,
            'cache_adapter' => get_class($this->cache)
        ];
    }
    
    /**
     * Clear all memories
     */
    public function clearAll(): void
    {
        if (method_exists($this->cache, 'clear')) {
            $this->cache->clear();
        }
    }
}
