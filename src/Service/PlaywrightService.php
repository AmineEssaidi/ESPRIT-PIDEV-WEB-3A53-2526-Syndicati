<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Playwright Service - Browser Automation for Syndicati Agent
 * 
 * Manages Playwright browser instances and provides:
 * - Page navigation
 * - DOM interaction (click, type, scroll)
 * - Page state inspection
 * - Screenshot capabilities for visual verification
 */
class PlaywrightService
{
    private HttpClientInterface $client;
    private string $playwrightServerUrl;
    private ?string $currentSessionId = null;
    private ?string $currentUrl = null;

    public function __construct(
        HttpClientInterface $client,
        ?string $playwrightServerUrl = null
    ) {
        $this->client = $client;
        $this->playwrightServerUrl = $playwrightServerUrl ?: ($_ENV['PLAYWRIGHT_SERVER_URL'] ?? 'http://127.0.0.1:3002');
    }

    /**
     * Initialize a new browser session
     */
    public function initSession(): string
    {
        try {
            $response = $this->client->request('POST', $this->playwrightServerUrl . '/session', [
                'json' => [
                    'headless' => false,
                    'viewport' => ['width' => 1280, 'height' => 720]
                ],
                'timeout' => 30
            ]);

            $data = $response->toArray();
            $this->currentSessionId = $data['session_id'];

            return $this->currentSessionId;
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to initialize Playwright session: " . $e->getMessage());
        }
    }

    /**
     * Navigate to URL - uses agent-server /agent/navigate (no session needed)
     */
    public function navigate(array $params): array
    {
        $url = $params['url'] ?? '/';
        $waitForLoad = $params['wait_for_load'] ?? true;

        // Make URL absolute if relative
        if (!str_starts_with($url, 'http')) {
            $url = 'http://localhost:8000' . (str_starts_with($url, '/') ? $url : '/' . $url);
        }

        try {
            // Use agent-server endpoint (no session_id needed)
            $response = $this->client->request('POST', $this->playwrightServerUrl . '/agent/navigate', [
                'json' => [
                    'url' => $url
                ],
                'timeout' => 60
            ]);

            $data = $response->toArray();
            $this->currentUrl = $url;

            return [
                'success' => !empty($data['success']) || !empty($data['url']),
                'url' => $data['url'] ?? $url,
                'title' => $data['title'] ?? null,
                'load_time' => $data['load_time'] ?? null
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException("Navigation failed: " . $e->getMessage());
        }
    }

    /**
     * Click element by selector or text - uses agent-server /agent/click (no session needed)
     */
    public function click(array $params): array
    {
        $selector = $params['selector'] ?? null;
        $byText = $params['by_text'] ?? null;

        if (!$selector && !$byText) {
            throw new \InvalidArgumentException("Either selector or by_text required");
        }

        try {
            $response = $this->client->request('POST', $this->playwrightServerUrl . '/agent/click', [
                'json' => [
                    'selector' => $selector,
                    'by_text' => $byText
                ],
                'timeout' => 45
            ]);

            $data = $response->toArray();

            return [
                'success' => !empty($data['success']) || !empty($data['elementText']),
                'selector' => $selector,
                'by_text' => $byText,
                'action' => 'clicked',
                'new_url' => $data['newUrl'] ?? null
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException("Click failed: " . $e->getMessage());
        }
    }

    /**
     * Type text into input
     */
    public function type(array $params): array
    {
        $selector = $params['selector'] ?? null;
        $text = $params['text'] ?? '';
        $clear = $params['clear'] ?? true;

        if (!$selector) {
            throw new \InvalidArgumentException("Selector required");
        }

        try {
            $response = $this->client->request('POST', $this->playwrightServerUrl . '/type', [
                'json' => [
                    'session_id' => $this->currentSessionId,
                    'selector' => $selector,
                    'text' => $text,
                    'clear' => $clear
                ],
                'timeout' => 45
            ]);

            return [
                'success' => true,
                'selector' => $selector,
                'text_entered' => $text
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException("Type failed: " . $e->getMessage());
        }
    }

    /**
     * Select option from dropdown
     */
    public function selectOption(array $params): array
    {
        $selector = $params['selector'] ?? null;
        $value = $params['value'] ?? null;

        if (!$selector || !$value) {
            throw new \InvalidArgumentException("Selector and value required");
        }

        try {
            $response = $this->client->request('POST', $this->playwrightServerUrl . '/select', [
                'json' => [
                    'session_id' => $this->currentSessionId,
                    'selector' => $selector,
                    'value' => $value
                ],
                'timeout' => 45
            ]);

            return [
                'success' => true,
                'selector' => $selector,
                'value_selected' => $value
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException("Select failed: " . $e->getMessage());
        }
    }

    /**
     * Scroll page
     */
    public function scroll(array $params): array
    {
        $direction = $params['direction'] ?? 'down';
        $amount = $params['amount'] ?? 500;

        try {
            $response = $this->client->request('POST', $this->playwrightServerUrl . '/scroll', [
                'json' => [
                    'session_id' => $this->currentSessionId,
                    'direction' => $direction,
                    'amount' => $amount
                ],
                'timeout' => 30
            ]);

            return [
                'success' => true,
                'direction' => $direction,
                'amount' => $amount
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException("Scroll failed: " . $e->getMessage());
        }
    }

    /**
     * Get structured page info - uses agent-server /agent/page-info (no session needed)
     */
    public function getPageInfo(): array
    {
        try {
            $response = $this->client->request('POST', $this->playwrightServerUrl . '/agent/page-info', [
                'json' => [],
                'timeout' => 45
            ]);

            $data = $response->toArray();

            return [
                'url' => $data['url'] ?? $this->currentUrl,
                'title' => $data['title'] ?? null,
                'headings' => $data['headings'] ?? [],
                'links' => $data['links'] ?? [],
                'buttons' => $data['buttons'] ?? [],
                'inputs' => $data['inputs'] ?? [],
                'forms' => $data['forms'] ?? [],
                'text_content' => substr($data['textContent'] ?? ($data['text'] ?? ''), 0, 2000)
            ];
        } catch (\Exception $e) {
            // Fallback: return empty structure
            return [
                'url' => $this->currentUrl,
                'title' => null,
                'headings' => [],
                'links' => [],
                'buttons' => [],
                'inputs' => [],
                'forms' => [],
                'text_content' => ''
            ];
        }
    }

    /**
     * Wait for element
     */
    public function waitFor(array $params): array
    {
        $selector = $params['selector'] ?? null;
        $timeout = $params['timeout'] ?? 5000;

        if (!$selector) {
            throw new \InvalidArgumentException("Selector required");
        }

        try {
            $response = $this->client->request('POST', $this->playwrightServerUrl . '/wait', [
                'json' => [
                    'session_id' => $this->currentSessionId,
                    'selector' => $selector,
                    'timeout' => $timeout
                ],
                'timeout' => ($timeout / 1000) + 5
            ]);

            return [
                'success' => true,
                'selector' => $selector,
                'found' => true
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException("Wait failed: " . $e->getMessage());
        }
    }

    /**
     * Assert text present
     */
    public function assertText(array $params): array
    {
        $text = $params['text'] ?? null;

        if (!$text) {
            throw new \InvalidArgumentException("Text required");
        }

        try {
            $response = $this->client->request('POST', $this->playwrightServerUrl . '/assert-text', [
                'json' => [
                    'session_id' => $this->currentSessionId,
                    'text' => $text
                ],
                'timeout' => 30
            ]);

            $data = $response->toArray();

            return [
                'success' => $data['found'] ?? false,
                'text' => $text,
                'found' => $data['found'] ?? false
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'text' => $text,
                'found' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Take screenshot for visual verification
     */
    public function screenshot(): ?string
    {
        try {
            $response = $this->client->request('POST', $this->playwrightServerUrl . '/screenshot', [
                'json' => [
                    'session_id' => $this->currentSessionId
                ],
                'timeout' => 30
            ]);

            $data = $response->toArray();
            return $data['screenshot'] ?? null; // Base64 encoded
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Close session
     */
    public function closeSession(): void
    {
        if ($this->currentSessionId) {
            try {
                $this->client->request('DELETE', $this->playwrightServerUrl . '/session/' . $this->currentSessionId);
            } catch (\Exception $e) {
                // Ignore close errors
            }
            $this->currentSessionId = null;
        }
    }

    /**
     * Get current URL
     */
    public function getCurrentUrl(): ?string
    {
        return $this->currentUrl;
    }

    /**
     * Find element by text content
     */
    private function findElementByText(string $text): string
    {
        // Ask Playwright server to find by text
        try {
            $response = $this->client->request('POST', $this->playwrightServerUrl . '/find-by-text', [
                'json' => [
                    'session_id' => $this->currentSessionId,
                    'text' => $text
                ],
                'timeout' => 30
            ]);

            $data = $response->toArray();
            return $data['selector'] ?? "text=$text";
        } catch (\Exception $e) {
            // Fallback: return text-based selector
            return "text=$text";
        }
    }

    /**
     * Check if service is available
     */
    public function isAvailable(): bool
    {
        try {
            $response = $this->client->request('GET', $this->playwrightServerUrl . '/agent/health', [
                'timeout' => 5
            ]);
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Scan page (Perplexity-style): load URL in Playwright and return forms with field labels.
     * Uses agent-server POST /agent/scan-page. No session required.
     */
    public function scanPage(array $params): array
    {
        $url = $params['url'] ?? '';
        if ($url === '') {
            return ['success' => false, 'error' => 'url required', 'forms' => []];
        }
        try {
            $response = $this->client->request('POST', $this->playwrightServerUrl . '/agent/scan-page', [
                'json' => ['url' => $url],
                'timeout' => 30
            ]);
            $data = $response->toArray(false);
            return [
                'success' => ($data['success'] ?? false) === true,
                'url' => $data['url'] ?? $url,
                'title' => $data['title'] ?? null,
                'forms' => $data['forms'] ?? [],
                'error' => $data['error'] ?? null
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'forms' => []];
        }
    }

    /**
     * Single call: navigate to URL and click element by visible text (uses /agent/click-on-page).
     */
    public function clickOnPage(array $params): array
    {
        $url = $params['url'] ?? '';
        $text = $params['text'] ?? '';
        if ($url === '' || $text === '') {
            error_log('[PlaywrightService] clickOnPage: Missing url or text. url=' . $url . ', text=' . $text);
            return ['success' => false, 'error' => 'url and text required'];
        }

        // Ensure URL is absolute
        if (!str_starts_with($url, 'http')) {
            $url = 'http://localhost:8000' . (str_starts_with($url, '/') ? $url : '/' . $url);
        }

        try {
            error_log('[PlaywrightService] Calling /agent/click-on-page: url=' . $url . ', text=' . $text);
            $response = $this->client->request('POST', $this->playwrightServerUrl . '/agent/click-on-page', [
                'json' => ['url' => $url, 'text' => $text],
                'timeout' => 90
            ]);
            $data = $response->toArray(false);
            error_log('[PlaywrightService] Response: ' . json_encode($data));
            return [
                'success' => ($data['success'] ?? false) === true,
                'text' => $data['text'] ?? $text,
                'newUrl' => $data['newUrl'] ?? null,
                'error' => $data['error'] ?? null
            ];
        } catch (\Exception $e) {
            error_log('[PlaywrightService] Exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fill form (Perplexity-style): navigate to URL, fill fields by label/name, optionally submit.
     * Uses agent-server POST /agent/fill-form. No session required.
     */
    public function fillForm(array $params): array
    {
        $url = $params['url'] ?? '';
        $fills = $params['fills'] ?? [];
        $submit = $params['submit'] ?? false;
        $formId = $params['formId'] ?? null;
        if ($url === '' || !is_array($fills)) {
            return ['success' => false, 'error' => 'url and fills required', 'filled' => [], 'submitted' => false];
        }
        try {
            $response = $this->client->request('POST', $this->playwrightServerUrl . '/agent/fill-form', [
                'json' => [
                    'url' => $url,
                    'fills' => $fills,
                    'submit' => $submit,
                    'formId' => $formId
                ],
                'timeout' => 60
            ]);
            $data = $response->toArray(false);
            return [
                'success' => ($data['success'] ?? false) === true,
                'url' => $data['url'] ?? $url,
                'filled' => $data['filled'] ?? [],
                'submitted' => $data['submitted'] ?? false,
                'filledCount' => $data['filledCount'] ?? 0,
                'error' => $data['error'] ?? null
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'filled' => [], 'submitted' => false];
        }
    }
}
