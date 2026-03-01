<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Web Search Service - Internet Research for Syndicati Agent
 * 
 * Provides web search capabilities using available search APIs.
 * Falls back to DuckDuckGo Lite scraping if no API key available.
 */
class WebSearchService
{
    private HttpClientInterface $client;
    private ?string $serpApiKey;
    private ?string $googleApiKey;
    private ?string $googleCx;
    
    public function __construct(
        HttpClientInterface $client,
        ?string $serpApiKey = null,
        ?string $googleApiKey = null,
        ?string $googleCx = null
    ) {
        $this->client = $client;
        $this->serpApiKey = $serpApiKey ?: $_ENV['SERPAPI_KEY'] ?? null;
        $this->googleApiKey = $googleApiKey ?: $_ENV['GOOGLE_SEARCH_API_KEY'] ?? null;
        $this->googleCx = $googleCx ?: $_ENV['GOOGLE_SEARCH_CX'] ?? null;
    }
    
    /**
     * Search the web
     */
    public function search(string $query, int $numResults = 5): array
    {
        // Try SerpAPI first
        if ($this->serpApiKey) {
            return $this->searchSerpApi($query, $numResults);
        }
        
        // Try Google Custom Search
        if ($this->googleApiKey && $this->googleCx) {
            return $this->searchGoogle($query, $numResults);
        }
        
        // Fallback to DuckDuckGo Lite
        return $this->searchDuckDuckGo($query, $numResults);
    }
    
    /**
     * Search using SerpAPI (Google results)
     */
    private function searchSerpApi(string $query, int $numResults): array
    {
        try {
            $response = $this->client->request('GET', 'https://serpapi.com/search', [
                'query' => [
                    'q' => $query,
                    'api_key' => $this->serpApiKey,
                    'engine' => 'google',
                    'num' => $numResults,
                    'hl' => 'en'
                ],
                'timeout' => 30
            ]);
            
            $data = $response->toArray();
            
            $results = [];
            foreach ($data['organic_results'] ?? [] as $result) {
                $results[] = [
                    'title' => $result['title'] ?? '',
                    'link' => $result['link'] ?? '',
                    'snippet' => $result['snippet'] ?? '',
                    'source' => 'serpapi'
                ];
            }
            
            return [
                'success' => true,
                'query' => $query,
                'results' => $results,
                'total' => count($results),
                'source' => 'Google (SerpAPI)'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'results' => [],
                'source' => null
            ];
        }
    }
    
    /**
     * Search using Google Custom Search API
     */
    private function searchGoogle(string $query, int $numResults): array
    {
        try {
            $response = $this->client->request('GET', 'https://www.googleapis.com/customsearch/v1', [
                'query' => [
                    'q' => $query,
                    'key' => $this->googleApiKey,
                    'cx' => $this->googleCx,
                    'num' => min($numResults, 10)
                ],
                'timeout' => 30
            ]);
            
            $data = $response->toArray();
            
            $results = [];
            foreach ($data['items'] ?? [] as $item) {
                $results[] = [
                    'title' => $item['title'] ?? '',
                    'link' => $item['link'] ?? '',
                    'snippet' => $item['snippet'] ?? '',
                    'source' => 'google'
                ];
            }
            
            return [
                'success' => true,
                'query' => $query,
                'results' => $results,
                'total' => count($results),
                'source' => 'Google'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'results' => [],
                'source' => null
            ];
        }
    }
    
    /**
     * Search using DuckDuckGo Lite (fallback, no API key needed)
     */
    private function searchDuckDuckGo(string $query, int $numResults): array
    {
        try {
            // DuckDuckGo Lite HTML interface
            $response = $this->client->request('GET', 'https://lite.duckduckgo.com/lite/', [
                'query' => ['q' => $query, 'kl' => 'en-us'],
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ],
                'timeout' => 30
            ]);
            
            $html = $response->getContent();
            
            // Parse results from HTML
            $results = $this->parseDuckDuckGoResults($html, $numResults);
            
            return [
                'success' => true,
                'query' => $query,
                'results' => $results,
                'total' => count($results),
                'source' => 'duckduckgo'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'results' => []
            ];
        }
    }
    
    /**
     * Parse DuckDuckGo Lite HTML results
     */
    private function parseDuckDuckGoResults(string $html, int $limit): array
    {
        $results = [];
        
        // Simple regex parsing for DuckDuckGo Lite
        preg_match_all('/<a[^>]+class="[^"]*result-link[^"]*"[^>]+href="([^"]+)"[^>]*>([^<]+)<\/a>/i', $html, $matches, PREG_SET_ORDER);
        
        foreach (array_slice($matches, 0, $limit) as $match) {
            $href = html_entity_decode($match[1] ?? '');
            $title = strip_tags($match[2] ?? '');
            
            // Skip empty or ad results
            if (empty($title) || str_starts_with($href, '/')) {
                continue;
            }
            
            $results[] = [
                'title' => $title,
                'link' => $href,
                'snippet' => '',
                'source' => 'duckduckgo'
            ];
        }
        
        return $results;
    }
    
    /**
     * Fetch and extract content from a URL
     */
    public function fetchUrl(string $url, int $maxLength = 3000): array
    {
        try {
            $response = $this->client->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ],
                'timeout' => 30
            ]);
            
            $html = $response->getContent();
            
            // Extract main content
            $content = $this->extractMainContent($html);
            
            return [
                'success' => true,
                'url' => $url,
                'title' => $this->extractTitle($html),
                'content' => substr($content, 0, $maxLength),
                'truncated' => strlen($content) > $maxLength
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'url' => $url
            ];
        }
    }
    
    /**
     * Extract main content from HTML
     */
    private function extractMainContent(string $html): string
    {
        // Remove scripts and styles
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<nav[^>]*>.*?<\/nav>/is', '', $html);
        $html = preg_replace('/<header[^>]*>.*?<\/header>/is', '', $html);
        $html = preg_replace('/<footer[^>]*>.*?<\/footer>/is', '', $html);
        
        // Try to find main content
        if (preg_match('/<main[^>]*>(.*?)<\/main>/is', $html, $matches)) {
            $content = $matches[1];
        } elseif (preg_match('/<article[^>]*>(.*?)<\/article>/is', $html, $matches)) {
            $content = $matches[1];
        } elseif (preg_match('/<div[^>]+class="[^"]*content[^"]*"[^>]*>(.*?)<\/div>/is', $html, $matches)) {
            $content = $matches[1];
        } else {
            $content = $html;
        }
        
        // Convert to text
        $text = strip_tags($content);
        $text = html_entity_decode($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * Extract title from HTML
     */
    private function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
            return html_entity_decode(trim($matches[1]));
        }
        return null;
    }
    
    /**
     * Quick search for best practices or patterns
     */
    public function searchBestPractices(string $topic, string $context = ''): array
    {
        $query = "best practices $topic";
        if ($context) {
            $query .= " $context";
        }
        
        $results = $this->search($query, 3);
        
        // Fetch top result for detailed content
        if (!empty($results['results'][0]['link'])) {
            $fetched = $this->fetchUrl($results['results'][0]['link'], 2000);
            $results['top_result_content'] = $fetched['content'] ?? null;
        }
        
        return $results;
    }
    
    /**
     * Check if web search is available
     */
    public function isAvailable(): bool
    {
        return $this->serpApiKey !== null || 
               ($this->googleApiKey !== null && $this->googleCx !== null) ||
               true; // DuckDuckGo always available
    }
}
