<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Symfony-6.4-native agent runtime that mimics an agentic LangGraph loop.
 *
 * - Uses Phi-4 via Ollama for reasoning.
 * - Uses LangGraph server when available for advanced workflows.
 * - Falls back to local tool-loop planner.
 */
class SyndicatiNativeAgentRuntime
{
    private const DEFAULT_MAX_STEPS = 8;
    private const ROUTE_CHOICE_MAX_OPTIONS = 5;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly OllamaClient $ollama,
        private readonly LangGraphClient $langGraph,
        private readonly PlaywrightService $browser,
        private readonly WebSearchService $web,
        private readonly ?InternalKnowledgeService $internal,
        private readonly AgentMemoryService $memory,
        private readonly ?SmartRouteMappingService $routes = null,
    ) {
    }

    public function execute(string $goal, array $context = [], ?string $sessionId = null): array
    {
        $contextNorm = $this->normalizeContext($context);

        // Multi-step: "A then B" / "A, then B" – execute first part and mention second in reply
        $secondGoal = $this->splitMultiStepGoal($goal);
        $goalToRun = $secondGoal === null ? $goal : $secondGoal['first'];

        // When second step is "submit", include it so form-fill knows to submit
        $goalForFormFill = $goalToRun;
        if ($secondGoal !== null && strtolower(trim($secondGoal['second'])) === 'submit') {
            $goalForFormFill = $goalToRun . ' then submit';
        }
        // Try click intent first: navigate + click by text (never fall back to LLM for click goals)
        $pageUrl = $contextNorm['url'] ?? $contextNorm['currentUrl'] ?? null;
        $clickResult = $this->tryClickFromGoal($goalToRun, $contextNorm, $pageUrl);
        if ($clickResult !== null && isset($clickResult['reply'])) {
            // Success
            $reply = $clickResult['reply'];
            if ($secondGoal !== null) {
                $reply .= ' When you\'re ready, say "' . $secondGoal['second'] . '" and I\'ll do it.';
            }
            return $this->shapeResponse(
                $goal,
                $contextNorm,
                $reply,
                $clickResult['actions'] ?? [],
                ['intent_type' => 'click'],
                $sessionId,
                ['click' => true, 'playwright' => true]
            );
        }

        // --- NEW: Smart Navigation Fallback ---
        // If clicking failed but it looks like a navigation goal, try SmartRouteMapping before giving up
        if ($this->isNavigationGoal($goalToRun)) {
            $navResult = $this->attemptSmartNavigation($goalToRun);
            if ($navResult !== null) {
                $reply = $navResult['reply'];
                if ($secondGoal !== null) {
                    $reply .= ' When you\'re ready, say "' . $secondGoal['second'] . '" and I\'ll take you there.';
                }
                return $this->shapeResponse(
                    $goal,
                    $contextNorm,
                    $reply,
                    $navResult['actions'],
                    $navResult['intent'] ?? null,
                    $sessionId,
                    ['smart_nav' => true, 'resolver' => $navResult['resolver']]
                );
            }
        }

        // If goal is clearly "click X" but we couldn't do it locally AND it's not a global route, return error
        if ($pageUrl !== '' && $pageUrl !== null && $this->isClickGoal($goalToRun)) {
            $clickTarget = trim(preg_replace('/^(click|press|tap|open|select|choose)\s+/i', '', $goalToRun));
            $clickTarget = trim(preg_replace('/\s+(within|on|in)\s+(this|the)\s+(page|site|website).*$/i', '', $clickTarget));

            // Get error from clickResult if available
            $clickError = $clickResult['error'] ?? null;

            // Try to test Playwright connection
            $playwrightAvailable = false;
            $playwrightError = null;
            try {
                $playwrightAvailable = $this->browser->isAvailable();
            } catch (\Throwable $e) {
                $playwrightError = $e->getMessage();
            }

            // Also try a test call to see what happens
            if ($playwrightAvailable) {
                try {
                    $testResult = $this->browser->getPageInfo();
                    if (empty($testResult)) {
                        $playwrightAvailable = false;
                        $playwrightError = 'getPageInfo returned empty';
                    }
                } catch (\Throwable $e) {
                    $playwrightAvailable = false;
                    $playwrightError = $e->getMessage();
                }
            }

            if (!$playwrightAvailable) {
                $reply = 'I couldn\'t click that on the page. The Playwright agent server is not responding. Error: ' . ($playwrightError ?? 'Connection failed') . '. Make sure it\'s started (run: node playwright/agent-server.js in the project folder, or start it from the dashboard). Then try again.';
            } else {
                // Playwright is running but couldn't find the element - use error from clickOnPage if available
                if ($clickError) {
                    $reply = 'I couldn\'t click "' . htmlspecialchars($clickTarget) . '". Error: ' . htmlspecialchars($clickError) . '. The button or link might not be visible, or the text might be slightly different.';
                } else {
                    $reply = 'I couldn\'t find "' . htmlspecialchars($clickTarget) . '" on the page. The button or link might not be visible, or the text might be slightly different. Try being more specific (e.g. "click View Details button") or check the page manually. Check the server console logs for details.';
                }
            }

            return $this->shapeResponse(
                $goal,
                $contextNorm,
                $reply,
                [],
                ['intent_type' => 'click_unavailable'],
                $sessionId,
                ['click' => true, 'playwright' => $playwrightAvailable, 'error' => $playwrightError ?? $clickError]
            );
        }

        // Perplexity-style: if no pageForms but we have URL and Playwright, scan the page first
        $goalLower = strtolower($goalForFormFill);
        $looksLikeFill = preg_match('/\b(fill|type|enter|put|write|add|submit|send)\b/i', $goalLower)
            || preg_match('/\b(reclamation|complaint|form)\b/i', $goalLower);
        if ($looksLikeFill && $pageUrl !== '' && $pageUrl !== null && $this->browser->isAvailable()) {
            $existingForms = $contextNorm['pageForms'] ?? null;
            if (empty($existingForms) || !is_array($existingForms)) {
                $scan = $this->browser->scanPage(['url' => $pageUrl]);
                if (!empty($scan['success']) && !empty($scan['forms'])) {
                    $contextNorm['pageForms'] = $scan['forms'];
                }
            }
        }
        // Try form-fill on current page first (if context has pageForms and goal is about filling/submitting)
        $formFillResult = $this->tryFormFillFromGoal($goalForFormFill, $contextNorm);
        if ($formFillResult !== null) {
            $pageUrl = $contextNorm['url'] ?? $contextNorm['currentUrl'] ?? null;
            $wantsSubmit = false;
            $formId = null;
            $fillsForPlaywright = [];
            foreach ($formFillResult['actions'] as $a) {
                if (isset($a['type'])) {
                    if ($a['type'] === 'fill_field' && isset($a['labelOrName'])) {
                        $fillsForPlaywright[] = ['labelOrName' => $a['labelOrName'], 'value' => $a['value'] ?? ''];
                    }
                    if ($a['type'] === 'submit_form') {
                        $wantsSubmit = true;
                        $formId = $a['formId'] ?? null;
                    }
                }
            }
            // Perplexity-style: DISABLED Playwright takeover for current page to avoid session loss.
            // We only return client-side fill/submit actions.
            $reply = $formFillResult['reply'];
            if ($secondGoal !== null) {
                $reply .= ' When you\'re ready, say "' . $secondGoal['second'] . '" and I\'ll do it.';
            }
            return $this->shapeResponse(
                $goal,
                $contextNorm,
                $reply,
                $formFillResult['actions'],
                ['intent_type' => 'form_fill'],
                $sessionId,
                ['form_fill' => true]
            );
        }

        // PRIORITIZE NAVIGATION: Resolve routes BEFORE any LLM/Planner logic.
        $navResult = $this->attemptSmartNavigation($goalToRun);
        if ($navResult !== null) {
            $reply = $navResult['reply'];
            if ($secondGoal !== null) {
                $reply .= ' When you\'re ready, say "' . $secondGoal['second'] . '" and I\'ll take you there.';
            }
            return $this->shapeResponse(
                $goal,
                $contextNorm,
                $reply,
                $navResult['actions'],
                $navResult['intent'] ?? null,
                $sessionId,
                ['smart_nav' => true, 'resolver' => $navResult['resolver']]
            );
        }

        // Restore LangGraph session if provided
        if ($sessionId) {
            $this->langGraph->setSessionId($sessionId);
        }

        // 1) Try LangGraph server first
        $lg = $this->langGraph->processMessage($goalToRun, $contextNorm, [
            'baseUrl' => $_ENV['OLLAMA_BASE_URL'] ?? null,
            'model' => $_ENV['OLLAMA_MODEL'] ?? null,
        ]);

        if (($lg['success'] ?? false) === true) {
            $reply = $lg['reply'] ?? '';
            if ($secondGoal !== null) {
                $reply .= ' When you\'re ready, say "' . $secondGoal['second'] . '" and I\'ll do it.';
            }
            return $this->shapeResponse(
                $goal,
                $contextNorm,
                $reply,
                $lg['actions'] ?? [],
                $lg['intent'] ?? null,
                $lg['session_id'] ?? $this->langGraph->getSessionId(),
                $lg
            );
        }

        // 2) Fallback: local agentic loop
        $result = $this->localToolLoop($goalToRun, $contextNorm);
        if ($secondGoal !== null && isset($result['reply'])) {
            $result['reply'] .= ' When you\'re ready, say "' . $secondGoal['second'] . '" and I\'ll do it.';
        }
        return $result;
    }

    private function isClickGoal(string $goal): bool
    {
        $g = strtolower(trim($goal));
        // Must have an action verb — short phrases alone are NOT enough (prevents "how are you" being treated as click)
        return preg_match('/\b(click|press|tap|open|select|choose|view|show|host|create|add|toggle|switch|expand|collapse|delete|remove|edit|modify|submit|send|cancel|close|dismiss)\b/i', $g) === 1;
    }

    private function isNavigationGoal(string $goal): bool
    {
        $g = strtolower(trim($goal));
        // Navigation verbs
        return preg_match('/\b(navigate|go to|take me to|visit|open|show|home|profile|forum|dashboard)\b/i', $g) === 1;
    }

    /**
     * Try to click a button/link on the page using Playwright (single call: navigate + click by text).
     */
    private function tryClickFromGoal(string $goal, array $context, ?string $pageUrl): ?array
    {
        if (!$this->isClickGoal($goal)) {
            return null;
        }

        // Extract what to click — smart stopword stripping
        $clickTarget = trim(preg_replace('/^(click|press|tap|open|select|choose|go to|view|show)\s+/i', '', $goal));
        // Strip trailing context phrases
        $clickTarget = trim(preg_replace('/\s+(within|on|in)\s+(this|the)\s+(page|site|website|section|panel).*$/i', '', $clickTarget));
        // Strip leading articles: "the", "a", "an"
        $clickTarget = trim(preg_replace('/^(the|a|an)\s+/i', '', $clickTarget));
        // Handle "X button" / "X link" / "X tab" pattern — strip trailing element-type words
        $clickTarget = trim(preg_replace('/\s+(button|btn|link|tab|pill|card|icon)$/i', '', $clickTarget));

        if ($clickTarget === '') {
            return null;
        }

        // --- NEW: Local Interactive Optimization ---
        // If the frontend sent us elements from the CURRENT tab, check them first.
        // This ensures we stay in the authenticated session.
        $targetLower = strtolower($clickTarget);
        $localElements = $context['interactiveElements'] ?? [];
        error_log('[SyndicatiNativeAgentRuntime] Searching ' . count($localElements) . ' local elements for: "' . $targetLower . '"');

        if (!empty($localElements) && is_array($localElements)) {
            $scoredElements = [];
            foreach ($localElements as $el) {
                $score = $this->calculateElementScore($el, $clickTarget);
                if ($score > 0) {
                    $el['precisionScore'] = $score;
                    $scoredElements[] = $el;
                }
            }

            // Sort by score descending
            usort($scoredElements, fn($a, $b) => ($b['precisionScore'] ?? 0) <=> ($a['precisionScore'] ?? 0));

            $bestMatch = null;
            if (!empty($scoredElements)) {
                $topScore = $scoredElements[0]['precisionScore'];

                // If we have a single clear winner (score >= 100), take it instantly.
                // Or if the distance to the second best is > 40.
                if (count($scoredElements) === 1 || ($topScore >= 100 && ($topScore - ($scoredElements[1]['precisionScore'] ?? 0)) > 40)) {
                    $bestMatch = $scoredElements[0];
                } else {
                    // AMBIGUITY: Multiple high-scoring candidates. Use Ranker LLM.
                    error_log('[SyndicatiNativeAgentRuntime] Ambiguity detected (' . count($scoredElements) . ' candidates). Forcing Ranker LLM...');
                    $bestMatch = $this->intelligentContextMatch($goal, array_slice($scoredElements, 0, 10));
                }
            }

            if ($bestMatch) {
                error_log('[SyndicatiNativeAgentRuntime] Found unique local match: ' . ($bestMatch['text'] ?? 'unnamed'));
                return [
                    'reply' => 'I found "' . ($bestMatch['text'] ?? $clickTarget) . '" right here in your tab. I\'m clicking it now.',
                    'actions' => [
                        [
                            'type' => 'local_click',
                            'selector' => $bestMatch['selector'] ?? null,
                            'text' => $bestMatch['text'] ?? $clickTarget
                        ]
                    ]
                ];
            } else {
                error_log('[SyndicatiNativeAgentRuntime] No local fuzzy match. Using Smart Vision...');
                $smartMatch = $this->intelligentContextMatch($goal, $localElements);
                if ($smartMatch) {
                    error_log('[SyndicatiNativeAgentRuntime] Smart Vision found match: ' . ($smartMatch['text'] ?? 'unnamed'));
                    return [
                        'reply' => 'My vision system identified the correct button: "' . ($smartMatch['text'] ?? $clickTarget) . '". I\'m clicking it now.',
                        'actions' => [
                            [
                                'type' => 'local_click',
                                'selector' => $smartMatch['selector'] ?? null,
                                'text' => $smartMatch['text'] ?? $clickTarget
                            ]
                        ]
                    ];
                }
            }
        }

        // All attempts failed - return error info
        $lastError = 'Button or link "' . $clickTarget . '" not found in your current tab. Try scrolling it into view or checking if the text matches exactly.';
        error_log('[SyndicatiNativeAgentRuntime] Click failed: ' . $clickTarget);

        return ['error' => $lastError];
    }

    /** Split "A then B" / "A, then B" into first and second goal. Returns null if single goal. */
    private function splitMultiStepGoal(string $goal): ?array
    {
        $g = trim($goal);
        if (preg_match('/\s+then\s+(.+)$/i', $g, $m)) {
            $first = trim(preg_replace('/\s+then\s+.+$/i', '', $g));
            $second = trim($m[1]);
            if ($first !== '' && $second !== '') {
                return ['first' => $first, 'second' => $second];
            }
        }
        if (preg_match('/^(.+),\s*then\s+(.+)$/i', $g, $m)) {
            $first = trim($m[1]);
            $second = trim($m[2]);
            if ($first !== '' && $second !== '') {
                return ['first' => $first, 'second' => $second];
            }
        }
        return null;
    }

    /**
     * If goal is about filling/submitting a form and context has pageForms, return fill_field + submit_form actions.
     */
    private function tryFormFillFromGoal(string $goal, array $context): ?array
    {
        $forms = $context['pageForms'] ?? null;
        if (!is_array($forms) || empty($forms)) {
            return null;
        }

        $goalLower = strtolower($goal);
        $wantsFill = preg_match('/\b(fill|type|enter|put|write|add|submit|send)\b/i', $goalLower)
            || preg_match('/\b(reclamation|complaint|form)\b/i', $goalLower);
        if (!$wantsFill) {
            return null;
        }

        $fieldsForLlm = [];
        $formId = null;
        foreach ($forms as $form) {
            $formId = $form['id'] ?? $form['formId'] ?? 'form';
            foreach ($form['fields'] ?? [] as $f) {
                $label = $f['label'] ?? $f['name'] ?? '';
                $name = $f['name'] ?? $label;
                $fieldsForLlm[] = ['label' => $label, 'name' => $name];
            }
            break;
        }
        if (empty($fieldsForLlm)) {
            return null;
        }

        $recent = '';
        if (!empty($context['previousMessages'])) {
            $recent = "\nRecent conversation (for context): " . json_encode(array_slice($context['previousMessages'], -6));
        }
        $prompt = "The user said: \"" . $goal . "\"." . $recent . "\n\nForm fields on the page: " . json_encode($fieldsForLlm) . ".\n\n"
            . "Reply with ONLY a JSON object. No other text.\n"
            . "Schema: {\"fills\":[{\"field\":\"field name or label\",\"value\":\"user-provided value\"}],\"submit\":true|false}.\n"
            . "Match 'field' to one of the field names/labels above. Use case-insensitive matching. Use empty string for value if not specified. Set submit true only if user asked to submit/send.";

        try {
            $llm = $this->ollama->chat([
                ['role' => 'system', 'content' => 'You output only valid JSON, no markdown.'],
                ['role' => 'user', 'content' => $prompt],
            ]);
            $raw = $llm['message']['content'] ?? $llm['response'] ?? '';
            if (!is_string($raw)) {
                return $this->formFillRegexFallback($goal, $forms, $formId, $fieldsForLlm);
            }
            $json = $this->extractJson($raw);
            $data = json_decode($json, true);
            if (!is_array($data) || empty($data['fills'])) {
                return $this->formFillRegexFallback($goal, $forms, $formId, $fieldsForLlm);
            }

            $actions = [];
            foreach ($data['fills'] as $fill) {
                $field = $fill['field'] ?? $fill['name'] ?? '';
                $value = $fill['value'] ?? '';
                if ($field !== '' && (string) $value !== '') {
                    $actions[] = ['type' => 'fill_field', 'labelOrName' => $field, 'value' => $value];
                }
            }
            if (!empty($data['submit'])) {
                $actions[] = ['type' => 'submit_form', 'formId' => $formId];
            }

            if (empty($actions)) {
                return $this->formFillRegexFallback($goal, $forms, $formId, $fieldsForLlm);
            }

            return [
                'reply' => 'I\'ve filled the form for you.' . (!empty($data['submit']) ? ' Submitting now.' : ''),
                'actions' => $actions,
            ];
        } catch (\Throwable $e) {
            return $this->formFillRegexFallback($goal, $forms, $formId, $fieldsForLlm);
        }
    }

    /**
     * Regex-based fallback when Ollama doesn't return valid fills. Extract "subject with X", "description with Y", and "submit".
     */
    private function formFillRegexFallback(string $goal, array $forms, string $formId, array $fieldsForLlm): ?array
    {
        $goalNorm = ' ' . strtolower($goal) . ' ';
        $wantsSubmit = preg_match('/\bsubmit\b/', $goalNorm);

        $fieldLabels = [];
        foreach ($fieldsForLlm as $f) {
            $label = trim($f['label'] ?? $f['name'] ?? '');
            $name = trim($f['name'] ?? $label);
            if ($label !== '') {
                $fieldLabels[strtolower($label)] = $name ?: $label;
            }
            if ($name !== '' && $name !== $label) {
                $fieldLabels[strtolower($name)] = $name;
            }
        }
        if (empty($fieldLabels)) {
            return null;
        }

        $actions = [];
        foreach ($fieldLabels as $label => $useName) {
            $esc = preg_quote($label, '/');
            if (preg_match('/\b' . $esc . '\s+with\s+["\']?([^,"\']+)["\']?(?:\s*[,.]|\s+and\s+|\s+then\s+|\s*$)/iu', $goal, $m)) {
                $value = trim($m[1]);
                if ($value !== '') {
                    $actions[] = ['type' => 'fill_field', 'labelOrName' => $useName, 'value' => $value];
                }
            } elseif (preg_match('/\b' . $esc . '\s+with\s+(.+?)(?=\s+(?:and|then|,|$))/ius', $goal, $m)) {
                $value = trim($m[1], " \t\n\r\",.");
                if ($value !== '') {
                    $actions[] = ['type' => 'fill_field', 'labelOrName' => $useName, 'value' => $value];
                }
            }
        }
        if ($wantsSubmit) {
            $actions[] = ['type' => 'submit_form', 'formId' => $formId];
        }

        if (empty($actions)) {
            return null;
        }

        return [
            'reply' => 'I\'ve filled the form for you.' . ($wantsSubmit ? ' Submitting now.' : ''),
            'actions' => $actions,
        ];
    }

    private function attemptSmartNavigation(string $goal): ?array
    {
        $g = strtolower(trim($goal));

        // 1. Strict Intent Inference
        $hint = $this->inferDestinationHint($g);
        if (!$hint)
            return null;

        // 2. Resolve candidates via DeepCodebaseScanner ONLY (validated routes)
        $resolved = $this->resolveRouteCandidates($hint);
        $bestUrl = $resolved['bestUrl'] ?? null;
        $candidates = $resolved['candidates'] ?? [];

        // CRITICAL: If no validated routes exist, return null - NEVER invent URLs
        if (!$bestUrl || empty($candidates)) {
            return null; // Let LLM/planner handle it instead of creating fake routes
        }

        $label = $candidates[0]['label'] ?? $hint;
        $actions = [];
        if ($this->shouldOfferRouteChoice($candidates)) {
            $actions[] = [
                'type' => 'choose_route',
                'options' => array_slice($candidates, 0, self::ROUTE_CHOICE_MAX_OPTIONS),
            ];
            $reply = 'I found multiple matching pages. Please choose one:';
        } else {
            $actions[] = ['type' => 'navigate', 'url' => $bestUrl];
            $reply = $this->replyForNavigation($goal, $hint, $label);
        }

        return [
            'actions' => $actions,
            'reply' => $reply,
            'resolver' => $resolved['resolver'] ?? 'deep_codebase_scanner_validated',
            'intent' => [
                'intent_type' => 'navigation',
                'destination' => $hint,
                'candidates' => $candidates
            ]
        ];
    }

    /**
     * Reply text for navigation. For modal-based intents, tell user they’ll use the form on that page.
     */
    private function replyForNavigation(string $goal, string $hint, string $pageLabel): string
    {
        $g = strtolower($goal);
        foreach (array_keys(self::MODAL_INTENT_TO_PAGE) as $modalKeyword) {
            if (str_contains($g, $modalKeyword)) {
                return 'Taking you to the ' . ucfirst($pageLabel) . ' page. You can file a complaint or submit a reclamation using the form there.';
            }
        }
        return 'Navigating to ' . $pageLabel . '...';
    }

    private function shouldOfferRouteChoice(array $candidates): bool
    {
        if (count($candidates) < 2) {
            return false;
        }

        $top = $candidates[0]['score'] ?? 0;
        $second = $candidates[1]['score'] ?? 0;

        // Relaxed threshold: offer choice if top candidates are relatively close.
        // Was 10, now 30 to ensure we catch more relevant tabs/sections.
        return ($top > 0 && ($top - $second) <= 30);
    }

    private function resolveRouteCandidates(string $destination): array
    {
        $destination = strtolower(trim($destination));
        $destination = preg_replace('/\s+/', ' ', $destination) ?? $destination;

        $candidates = [];

        // 1. First, try SmartRouteMappingService (Fast, validated, high-confidence Symfony routes)
        if ($this->routes) {
            $url = $this->routes->getRouteUrl($destination, true);
            if ($url) {
                $candidates[] = [
                    'label' => $destination,
                    'url' => $url,
                    'score' => 100,
                    'type' => 'symfony_route_mapping'
                ];
            }

            // Try to find if destination hints at an available route
            foreach ($this->routes->getAvailableRoutes() as $alias => $info) {
                if (str_contains($alias, $destination) || str_contains($destination, $alias)) {
                    $candidates[] = [
                        'label' => $alias,
                        'url' => $info['url'],
                        'route_name' => $info['route_name'],
                        'score' => (str_contains($destination, $alias) ? 95 : 85),
                        'type' => 'symfony_route_alias'
                    ];
                }
            }
        }

        // 2. Fallback to DeepCodebaseScanner via InternalKnowledgeService
        if ($this->internal) {
            try {
                // Use deep codebase search
                $r = $this->internal->search($destination, 'route candidates');
                $routes = $r['results']['routes'] ?? [];

                if (is_array($routes) && !empty($routes)) {
                    foreach ($routes as $rr) {
                        $url = $rr['url'] ?? null;
                        $routeName = $rr['route_name'] ?? null;

                        if (!$routeName || !$this->internal->validateRouteExists($routeName)) {
                            continue;
                        }

                        if (!is_string($url) || $url === '' || $url === '/path') {
                            continue;
                        }

                        $label = $rr['alias'] ?? ($rr['route_name'] ?? $destination);
                        $score = (int) ($rr['score'] ?? 0);

                        if ($score > 0) {
                            $candidates[] = [
                                'label' => $label,
                                'url' => $url,
                                'route_name' => $routeName,
                                'score' => $score,
                                'type' => $rr['type'] ?? 'validated_route'
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {
                error_log("[SyndicatiNativeAgentRuntime] Route search error: " . $e->getMessage());
            }
        }

        // De-dup by url (keep highest score)
        $byUrl = [];
        foreach ($candidates as $c) {
            $u = (string) ($c['url'] ?? '');
            if ($u === '' || $u === '/path') {
                continue;
            }

            $s = (int) ($c['score'] ?? 0);
            if (!isset($byUrl[$u]) || $s > (int) ($byUrl[$u]['score'] ?? 0)) {
                $byUrl[$u] = $c;
            }
        }
        $candidates = array_values($byUrl);

        // Sort by score descending
        usort($candidates, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        return [
            'bestUrl' => $candidates[0]['url'] ?? null,
            'resolver' => !empty($candidates) ? 'deep_codebase_scanner_validated' : null,
            'candidates' => $candidates,
        ];
    }

    /**
     * Modal-only actions: your app uses modals for CRUD (no separate Twig pages).
     * Map "do X" intents to the PAGE that hosts the modal, not to a fake CRUD URL.
     */
    private const MODAL_INTENT_TO_PAGE = [
        'write a reclamation' => 'syndicat',
        'file a reclamation' => 'syndicat',
        'submit a reclamation' => 'syndicat',
        'reclamation' => 'syndicat',
        'complaint' => 'syndicat',
        'complaints' => 'syndicat',
        'claim' => 'syndicat',
        'claims' => 'syndicat',
    ];

    private function inferDestinationHint(string $goalLower): ?string
    {
        // 1) Modal-based intents: map to the PAGE that contains the modal (never to a CRUD route)
        foreach (self::MODAL_INTENT_TO_PAGE as $needle => $page) {
            if (str_contains($goalLower, $needle)) {
                return $page;
            }
        }

        // 2) Other implicit navigation (real pages only)
        $implicitMap = [
            'forum' => 'forum',
            'publication' => 'forum',
            'post' => 'forum',
            'residence' => 'residence',
            'residences' => 'residence',
            'apartment' => 'residence',
            'event' => 'event',
            'events' => 'event',
            'evenement' => 'event',
            'profile' => 'profile',
            'settings' => 'settings',
            'dashboard' => 'home',
            'main page' => 'home',
            'start page' => 'home',
            'home' => 'home',
            'syndicat' => 'syndicat',
        ];
        foreach ($implicitMap as $needle => $dest) {
            if (str_contains($goalLower, $needle)) {
                return $dest;
            }
        }

        // 2) Explicit navigation phrasings
        if (
            preg_match('/^(go|navigate|open|visit)\b/', $goalLower)
            || preg_match('/\b(go\s*to|navigate\s*to|open|visit|take me to|show me|take me)\b/', $goalLower)
        ) {
            return $this->extractDestinationKeyword($goalLower) ?: $goalLower;
        }

        // 3) Fallback: try to extract a destination-like noun phrase from "take me to ..."
        if (preg_match('/\b(take me to|show me|open)\s+(?:the\s+)?(.+)/', $goalLower, $m)) {
            $raw = strtolower(trim($m[2] ?? ''));
            $raw = preg_replace('/\b(page|section|tab)\b/', '', $raw) ?? $raw;
            $raw = trim($raw);
            if ($raw !== '') {
                return $raw;
            }
        }

        return null;
    }

    private function extractDestinationKeyword(string $goal): ?string
    {
        $g = strtolower(trim($goal));
        $g = preg_replace('/\s+/', ' ', $g) ?? $g;
        $g = preg_replace('/^(go|navigate|open|visit)(\s+to)?\s+/', '', $g) ?? $g;
        $g = trim($g);
        if ($g === '' || $g === $goal) {
            // if no change, still try a few common tokens
            $g = strtolower(trim($goal));
        }

        $g = preg_replace('/\b(page|section|tab)\b/', '', $g) ?? $g;
        $g = trim($g);

        if ($g === '') {
            return null;
        }

        // Keep it short for matching
        $parts = explode(' ', $g);
        return trim(implode(' ', array_slice($parts, 0, 3)));
    }

    private function resolveRouteFromSymfony(string $goal): ?string
    {
        // 1) SmartRouteMappingService (dynamic router + alias patterns)
        if ($this->routes) {
            try {
                // First: full intent detection (supports: "take me to ...", "open ...")
                $nav = $this->routes->detectNavigationUrl($goal);
                if (is_array($nav)) {
                    $url = $nav['url'] ?? null;
                    if (is_string($url) && $url !== '') {
                        return $url;
                    }
                }

                // Second: keyword-based route lookup
                $destination = $this->inferDestinationHint(strtolower(trim($goal)));
                if ($destination) {
                    $url = $this->routes->getRouteUrl($destination);
                    if (is_string($url) && $url !== '') {
                        return $url;
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        // 2) Internal knowledge can search route aliases/paths
        if ($this->internal) {
            try {
                $query = $this->extractDestinationKeyword($goal) ?: $goal;
                $r = $this->internal->search($query, 'route lookup');
                $routes = $r['results']['routes'] ?? [];
                if (is_array($routes) && isset($routes[0]['url']) && is_string($routes[0]['url']) && $routes[0]['url'] !== '') {
                    return $routes[0]['url'];
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return null;
    }

    private function localToolLoop(string $goal, array $context): array
    {
        $steps = [];
        $actions = [];
        $reply = '';

        $observation = $this->safeGetPageInfo();

        for ($i = 0; $i < self::DEFAULT_MAX_STEPS; $i++) {
            $plan = $this->planNextAction($goal, $context, $observation, $steps);

            if (($plan['done'] ?? false) === true) {
                $reply = (string) ($plan['reply'] ?? 'Done.');
                break;
            }

            $tool = (string) ($plan['tool'] ?? '');
            $params = is_array($plan['params'] ?? null) ? $plan['params'] : [];

            if ($tool === '') {
                // Safety: if planner fails, try route resolution ONLY if validated routes exist
                $dest = $this->inferDestinationHint(strtolower(trim($goal)));
                if ($dest) {
                    $resolved = $this->resolveRouteCandidates($dest);
                    $bestUrl = $resolved['bestUrl'] ?? null;
                    $candidates = $resolved['candidates'] ?? [];

                    // CRITICAL: Only use validated routes - never invent URLs
                    if (is_string($bestUrl) && $bestUrl !== '' && $bestUrl !== '/path' && !empty($candidates)) {
                        $actions = [];

                        if ($this->shouldOfferRouteChoice($candidates)) {
                            $actions[] = [
                                'type' => 'choose_route',
                                'options' => array_slice($candidates, 0, self::ROUTE_CHOICE_MAX_OPTIONS),
                            ];
                            $reply = 'I found multiple matching pages. Please choose one:';
                        } else {
                            // Only navigate if we have validated route
                            $actions[] = ['type' => 'navigate', 'url' => $bestUrl];

                            // If there are other good candidates (score > 70), add them as suggestions too
                            if (count($candidates) > 1) {
                                $suggestions = [];
                                foreach (array_slice($candidates, 1, 3) as $c) {
                                    if (($c['score'] ?? 0) > 70) {
                                        $suggestions[] = $c;
                                    }
                                }
                                if (!empty($suggestions)) {
                                    $actions[] = [
                                        'type' => 'suggest_routes',
                                        'options' => $suggestions,
                                    ];
                                }
                            }
                            $reply = 'Navigating to ' . ($candidates[0]['label'] ?? $dest) . '...';
                        }
                        break;
                    }
                }

                // No validated routes found - don't invent any, let LLM handle it
                $reply = 'I could not find a matching page for that request. Could you be more specific or try a different approach?';
                break;
            }

            $toolResult = $this->callTool($tool, $params);

            $steps[] = [
                'i' => $i + 1,
                'tool' => $tool,
                'params' => $params,
                'result' => $toolResult,
            ];

            if ($tool === 'navigate') {
                // If browser automation succeeded, use returned absolute URL/path.
                if (isset($toolResult['url']) && is_string($toolResult['url'])) {
                    $actions[] = [
                        'type' => 'navigate',
                        'url' => $this->toPathIfLocalhost($toolResult['url']),
                    ];
                } else {
                    // If browser automation is unavailable, still instruct frontend to navigate.
                    $url = (string) ($params['url'] ?? '/');
                    if ($url !== '') {
                        $actions[] = [
                            'type' => 'navigate',
                            'url' => $url,
                        ];
                    }
                }
            }

            $observation = $this->safeGetPageInfo();
        }

        if ($reply === '') {
            $reply = 'I executed steps but did not reach a verified completion.';
        }

        return $this->shapeResponse($goal, $context, $reply, $actions, null, null, [
            'fallback' => true,
            'steps' => $steps,
        ]);
    }

    private function planNextAction(string $goal, array $context, array $pageInfo, array $steps): array
    {
        $prompt = $this->buildPlannerPrompt($goal, $context, $pageInfo, $steps);

        $llm = $this->ollama->chat([
            ['role' => 'system', 'content' => 'You are Syndicati Agent. Output ONLY valid JSON.'],
            ['role' => 'user', 'content' => $prompt],
        ]);

        $raw = $llm['message']['content'] ?? ($llm['response'] ?? null);
        if (!is_string($raw)) {
            return ['done' => true, 'reply' => 'LLM returned no content.'];
        }

        $json = $this->extractJson($raw);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            return ['done' => true, 'reply' => 'Could not parse planner output.'];
        }

        return $data;
    }

    private function callTool(string $tool, array $params): array
    {
        return match ($tool) {
            'navigate' => $this->safeBrowserCall('navigate', $params),
            'click' => $this->browser->click($params),
            'type' => $this->browser->type($params),
            'select_option' => $this->browser->selectOption($params),
            'scroll' => $this->browser->scroll($params),
            'get_page_info' => $this->browser->getPageInfo(),
            'search_internal_docs' => $this->internal ? $this->internal->search((string) ($params['query'] ?? ''), (string) ($params['context'] ?? '')) : ['success' => false, 'error' => 'Internal knowledge not configured'],
            'web_search' => $this->web->search((string) ($params['query'] ?? ''), (int) ($params['numResults'] ?? 5)),
            'recall_agent_memory' => ['memories' => $this->memory->recall((string) ($params['query'] ?? ''), (int) ($params['limit'] ?? 5))],
            default => ['success' => false, 'error' => 'Unknown tool: ' . $tool],
        };
    }

    private function safeBrowserCall(string $method, array $params): array
    {
        try {
            return $this->browser->$method($params);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'tool' => $method,
                'params' => $params,
                'fallback' => true,
            ];
        }
    }

    private function safeGetPageInfo(): array
    {
        try {
            return $this->browser->getPageInfo();
        } catch (\Throwable) {
            return [
                'url' => null,
                'title' => null,
                'headings' => [],
                'links' => [],
                'buttons' => [],
                'inputs' => [],
                'forms' => [],
                'text_content' => '',
            ];
        }
    }

    private function buildPlannerPrompt(string $goal, array $context, array $pageInfo, array $steps): string
    {
        $ctxUrl = (string) ($context['url'] ?? '');
        $ctxTitle = (string) ($context['title'] ?? '');

        $tools = [
            'navigate({"url":"/path"})',
            'click({"selector":"..."}|{"by_text":"..."})',
            'type({"selector":"...","text":"...","clear":true})',
            'select_option({"selector":"...","value":"..."})',
            'scroll({"direction":"down","amount":500})',
            'get_page_info({})',
            'search_internal_docs({"query":"...","context":"..."})',
            'web_search({"query":"...","numResults":5})',
            'recall_agent_memory({"query":"...","limit":5})',
        ];

        return "GOAL:\n{$goal}\n\n" .
            "CONTEXT:\n- url: {$ctxUrl}\n- title: {$ctxTitle}\n\n" .
            "PAGE_INFO (ground truth):\n" . json_encode($pageInfo, JSON_UNESCAPED_SLASHES) . "\n\n" .
            "PAST_STEPS:\n" . json_encode($steps, JSON_UNESCAPED_SLASHES) . "\n\n" .
            "TOOLS:\n- " . implode("\n- ", $tools) . "\n\n" .
            "OUTPUT JSON SCHEMA (ONLY JSON):\n" .
            "{\"done\":false,\"reply\":\"...\",\"tool\":\"navigate|click|type|select_option|scroll|get_page_info|search_internal_docs|web_search|recall_agent_memory\",\"params\":{...}}\n" .
            "If the goal is achieved, output: {\"done\":true,\"reply\":\"...\"}.";
    }

    private function extractJson(string $raw): string
    {
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            return $m[0];
        }
        return $raw;
    }

    private function normalizeContext(array $context): array
    {
        $out = [];
        if (isset($context['url'])) {
            $out['url'] = $context['url'];
        } elseif (isset($context['currentUrl'])) {
            $out['url'] = $context['currentUrl'];
        }

        if (isset($context['title'])) {
            $out['title'] = $context['title'];
        } elseif (isset($context['pageTitle'])) {
            $out['title'] = $context['pageTitle'];
        }

        if (isset($context['pageForms']) && is_array($context['pageForms'])) {
            $out['pageForms'] = $context['pageForms'];
        }
        if (isset($context['interactiveElements']) && is_array($context['interactiveElements'])) {
            $out['interactiveElements'] = $context['interactiveElements'];
        }
        if (isset($context['previousMessages']) && is_array($context['previousMessages'])) {
            $out['previousMessages'] = array_slice($context['previousMessages'], -20);
        }

        return $out;
    }

    private function toPathIfLocalhost(string $url): string
    {
        // Convert absolute localhost URL into path for frontend navigation
        if (preg_match('#^https?://127\.0\.0\.1:8000(?P<path>/.*)$#', $url, $m)) {
            return $m['path'];
        }
        return $url;
    }

    private function shapeResponse(string $goal, array $context, string $reply, array $actions, mixed $intent, ?string $sessionId, array $debug): array
    {
        // store memory snapshot
        $this->memory->store([
            'type' => 'syndicati_native',
            'goal' => $goal,
            'context' => $context,
            'reply' => $reply,
            'actions' => $actions,
            'session_id' => $sessionId,
            'debug' => $debug,
        ]);

        return [
            'success' => true,
            'goal' => $goal,
            'reply' => $reply,
            'actions' => $actions,
            'intent' => $intent,
            'sessionId' => $sessionId,
            'debug' => $debug,
        ];
    }
    /**
     * Smart Vision: Use Ollama to pick the best button based on high-fidelity context.
     */
    private function intelligentContextMatch(string $goal, array $elements): ?array
    {
        error_log('[SyndicatiNativeAgentRuntime] Running intelligentContextMatch (Precision Ranker) for: ' . $goal);

        $elementsList = [];
        foreach ($elements as $idx => $el) {
            $elementsList[] = [
                'id' => $idx,
                'text' => $el['text'] ?? '',
                'aria' => $el['ariaLabel'] ?? '',
                'section' => $el['parentSection'] ?? '',
                'anchor' => $el['semanticAnchor'] ?? '',
                'container' => $el['titleContext'] ?? '',
                'tag' => $el['tag'] ?? '',
                'primary' => $el['isPrimary'] ?? false
            ];
        }

        $prompt = "You are a surgical-grade web interaction expert. Resolve element ambiguity.\n"
            . "Goal: \"$goal\"\n\n"
            . "RANKING RULES:\n"
            . "1. CASE-INSENSITIVE: 'View' matches 'view'.\n"
            . "2. CLUSTER ANALYSICS: Elements are grouped by sections and anchors (headings).\n"
            . "3. SEMANTIC WEIGHT: If the user wants to 'Host' or 'Create', prioritize 'primary' buttons matching those verbs.\n"
            . "4. CONTEXT LOCK: If the user mentions a specific name/place found in 'container' or 'anchor', ONLY return elements from that section.\n"
            . "5. BE PRECISE: If goal is 'Click the second View button', use your logic to find the second candidate.\n\n"
            . "Return ONLY the ID of the chosen element as a single integer. If none match, return -1.\n\n"
            . "Elements:\n" . json_encode($elementsList, JSON_PRETTY_PRINT) . "\n\n"
            . "Best Element ID:";

        try {
            $response = $this->ollama->chat([
                ['role' => 'user', 'content' => $prompt]
            ]);

            $reply = trim($response['message']['content'] ?? $response['response'] ?? '');
            error_log('[SyndicatiNativeAgentRuntime] Vision Brain Ranker Reply: ' . $reply);

            if (preg_match('/(-?\d+)/', $reply, $matches)) {
                $id = (int) $matches[1];
                if ($id >= 0 && isset($elements[$id])) {
                    return $elements[$id];
                }
            }
        } catch (\Throwable $e) {
            error_log('[SyndicatiNativeAgentRuntime] Vision Brain error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Normalize text for comparison: lowercase, strip punctuation, collapse whitespace.
     */
    private function normalizeForMatch(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^\w\s]/u', '', $s);
        return preg_replace('/\s+/', ' ', $s);
    }

    /**
     * Calculate word overlap ratio (Jaccard-like) between two strings.
     * Returns 0.0 to 1.0 — higher means better match.
     */
    private function wordOverlap(string $a, string $b): float
    {
        $wa = array_filter(explode(' ', $a), fn($w) => strlen($w) >= 2);
        $wb = array_filter(explode(' ', $b), fn($w) => strlen($w) >= 2);
        if (empty($wa) || empty($wb)) return 0.0;
        $intersection = count(array_intersect($wa, $wb));
        $union = count(array_unique(array_merge($wa, $wb)));
        return $union > 0 ? $intersection / $union : 0.0;
    }

    private function calculateElementScore(array $el, string $goal): int
    {
        $score = 0;
        $goalNorm = $this->normalizeForMatch($goal);
        $text = $this->normalizeForMatch($el['text'] ?? '');
        $ownText = $this->normalizeForMatch($el['ownText'] ?? $el['text'] ?? '');
        $aria = $this->normalizeForMatch($el['ariaLabel'] ?? '');
        $anchor = $this->normalizeForMatch($el['semanticAnchor'] ?? '');
        $section = $this->normalizeForMatch($el['parentSection'] ?? '');
        $container = $this->normalizeForMatch($el['titleContext'] ?? '');

        // 1. Exact Match (Weight: 100) — own text or ARIA matches exactly
        if (($ownText !== '' && $ownText === $goalNorm) || ($aria !== '' && $aria === $goalNorm)) {
            $score += 100;
        }
        // Also check full text for exact match but lower weight
        elseif ($text !== '' && $text === $goalNorm) {
            $score += 90;
        }

        // 2. Contains Match (Weight: 40-60) — with minimum overlap requirement
        if ($ownText !== '' && str_contains($ownText, $goalNorm)) {
            $score += 60;
        } elseif ($text !== '' && str_contains($text, $goalNorm)) {
            $score += 40;
        }
        // Reverse containment ONLY if goal is significantly longer and text is > 3 chars
        if ($goalNorm !== '' && $ownText !== '' && strlen($ownText) > 3 && str_contains($goalNorm, $ownText)) {
            $overlap = $this->wordOverlap($goalNorm, $ownText);
            if ($overlap >= 0.3) {
                $score += (int)($overlap * 50);
            }
        }

        // 3. Semantic Context (Weight: up to 40) — goal words match heading/section
        $goalWords = array_filter(explode(' ', $goalNorm), fn($w) => strlen($w) >= 3);
        $semanticHits = 0;
        foreach ($goalWords as $word) {
            if (str_contains($anchor, $word)) $semanticHits += 2;
            if (str_contains($container, $word)) $semanticHits += 2;
            if (str_contains($section, $word)) $semanticHits++;
        }
        $score += min($semanticHits * 8, 40); // Cap at 40

        // 4. Visual Intensity (Weight: 10)
        if ($el['isPrimary'] ?? false) {
            $score += 10;
        }

        // 5. Tag bonus (Weight: 5) — buttons & links > divs
        $tag = strtolower($el['tag'] ?? '');
        if (in_array($tag, ['button', 'a', 'input'])) {
            $score += 5;
        }

        return $score;
    }
}
