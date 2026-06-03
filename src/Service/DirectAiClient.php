<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class DirectAiClient
{
    private string $geminiKey;
    private string $geminiModel;
    private string $groqKey;
    private string $groqModel;
    /** @var string[] */
    private array $lastProviderErrors = [];

    public function __construct(private readonly HttpClientInterface $client)
    {
        $this->geminiKey = $this->envValue('GEMINI_API_KEY');
        $this->geminiModel = $this->envValue('GEMINI_MODEL', 'gemini-1.5-flash');
        $this->groqKey = $this->envValue('GROQ_API_KEY');
        $this->groqModel = $this->envValue('GROQ_MODEL', 'llama-3.1-8b-instant');
    }

    public function chat(array $messages, array $pageContext = []): array
    {
        $conversation = $this->formatConversation($messages);
        $prompt = <<<PROMPT
You are Syndicati AI inside the Syndicati web app.
Be concise, useful, and friendly.
Current page context:
{$this->jsonForPrompt($pageContext)}

Conversation:
{$conversation}

Return ONLY JSON:
{"reply":"short helpful answer","actions":[],"intent":{"intent_type":"chat"}}
PROMPT;

        $json = $this->completeJson($prompt, 0.35);

        return [
            'reply' => (string) ($json['reply'] ?? 'I am ready to help.'),
            'actions' => is_array($json['actions'] ?? null) ? $json['actions'] : [],
            'intent' => is_array($json['intent'] ?? null) ? $json['intent'] : ['intent_type' => 'chat'],
            'provider' => $json['_provider'] ?? 'fallback',
        ];
    }

    public function executeAgent(string $goal, array $context = [], ?string $sessionId = null): array
    {
        $goal = trim($goal);
        $sessionId = $sessionId ?: bin2hex(random_bytes(8));

        if ($goal === '') {
            return ['success' => false, 'error' => 'No goal provided', 'sessionId' => $sessionId];
        }

        $quickRoute = $this->quickRoute($goal);
        if ($quickRoute) {
            return [
                'success' => true,
                'reply' => 'Opening ' . $quickRoute['label'] . ' for you.',
                'actions' => [['type' => 'navigate', 'url' => $quickRoute['url']]],
                'sessionId' => $sessionId,
                'intent' => ['intent_type' => 'navigate', 'requires_navigation' => true, 'destination_route' => $quickRoute['url']],
                'provider' => 'local-route-map',
            ];
        }

        $prompt = <<<PROMPT
You are the Syndicati browser agent. The browser already knows how to execute JSON actions.
Use ONLY these action types:
- {"type":"navigate","url":"/path"}
- {"type":"local_click","text":"visible button or link text"}
- {"type":"fill_field","labelOrName":"field label/name/placeholder","value":"text"}
- {"type":"submit_form","formId":"optional-form-id"}

Never invent unsupported actions. Prefer same-page fill_field/submit_form when form fields are visible in context.
Current browser context:
{$this->jsonForPrompt($context)}

User goal:
{$goal}

Return ONLY JSON:
{"success":true,"reply":"what I will do","actions":[],"sessionId":"{$sessionId}","intent":{"intent_type":"agent"}}
PROMPT;

        $json = $this->completeJson($prompt, 0.15);
        $actions = is_array($json['actions'] ?? null) ? $this->sanitizeActions($json['actions']) : [];

        return [
            'success' => (bool) ($json['success'] ?? true),
            'reply' => (string) ($json['reply'] ?? 'I prepared the next step.'),
            'actions' => $actions,
            'sessionId' => (string) ($json['sessionId'] ?? $sessionId),
            'intent' => is_array($json['intent'] ?? null) ? $json['intent'] : ['intent_type' => 'agent'],
            'provider' => $json['_provider'] ?? 'fallback',
        ];
    }

    public function analyzeFeeling(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return $this->fallbackFeeling($text);
        }

        $previousGeminiModel = $this->geminiModel;
        $previousGroqModel = $this->groqModel;
        $this->geminiModel = $this->envValue('GEMINI_SENTIMENT_MODEL', $this->geminiModel);
        $this->groqModel = $this->envValue('GROQ_SENTIMENT_MODEL', $this->groqModel);

        $prompt = <<<PROMPT
Analyze the emotional feeling of this forum content for a community moderation/user insight UI.
Keep the explanation short and non-judgmental.

Content:
{$text}

Return ONLY JSON:
{"sentiment":"positive|neutral|negative|mixed","primaryEmotion":"joy|anger|sadness|fear|surprise|trust|neutral","confidence":0,"explanation":"one sentence","signals":["short cue","short cue"]}
PROMPT;

        try {
            $json = $this->completeJson($prompt, 0.2);
            if (!isset($json['sentiment'], $json['primaryEmotion'])) {
                return $this->fallbackFeeling($text);
            }

            return [
                'sentiment' => $this->safeChoice((string) $json['sentiment'], ['positive', 'neutral', 'negative', 'mixed'], 'neutral'),
                'primaryEmotion' => $this->safeChoice((string) $json['primaryEmotion'], ['joy', 'anger', 'sadness', 'fear', 'surprise', 'trust', 'neutral'], 'neutral'),
                'confidence' => max(0, min(100, (int) ($json['confidence'] ?? 60))),
                'explanation' => (string) ($json['explanation'] ?? 'The tone reads as mostly neutral.'),
                'signals' => array_values(array_slice(array_filter((array) ($json['signals'] ?? []), 'is_string'), 0, 3)),
                'provider' => $json['_provider'] ?? 'fallback',
            ];
        } finally {
            $this->geminiModel = $previousGeminiModel;
            $this->groqModel = $previousGroqModel;
        }
    }

    public function status(): array
    {
        return [
            'running' => $this->geminiKey !== '' || $this->groqKey !== '',
            'model' => $this->geminiKey !== '' ? $this->geminiModel : $this->groqModel,
            'model_available' => $this->geminiKey !== '' || $this->groqKey !== '',
            'baseUrl' => $this->geminiKey !== '' ? 'Gemini API' : ($this->groqKey !== '' ? 'Groq API' : 'not configured'),
        ];
    }

    private function completeJson(string $prompt, float $temperature): array
    {
        $this->lastProviderErrors = [];

        if ($this->geminiKey !== '') {
            try {
                $text = $this->callGemini($prompt, $temperature);
                $json = $this->extractJson($text);
                if ($json) {
                    $json['_provider'] = 'gemini';
                    return $json;
                }
                $this->lastProviderErrors[] = 'Gemini returned a non-JSON response.';
            } catch (\Throwable $e) {
                $this->lastProviderErrors[] = 'Gemini: ' . $e->getMessage();
                error_log('[DirectAiClient] Gemini failed: ' . $e->getMessage());
            }
        }

        if ($this->groqKey !== '') {
            try {
                $text = $this->callGroq($prompt, $temperature);
                $json = $this->extractJson($text);
                if ($json) {
                    $json['_provider'] = 'groq';
                    return $json;
                }
                $this->lastProviderErrors[] = 'Groq returned a non-JSON response.';
            } catch (\Throwable $e) {
                $this->lastProviderErrors[] = 'Groq: ' . $e->getMessage();
                error_log('[DirectAiClient] Groq failed: ' . $e->getMessage());
            }
        }

        if ($this->geminiKey === '' && $this->groqKey === '') {
            return ['reply' => 'Direct AI is ready once GEMINI_API_KEY or GROQ_API_KEY is configured.', 'actions' => [], '_provider' => 'fallback'];
        }

        return [
            'reply' => 'Direct AI keys are configured, but the provider call failed: ' . implode(' | ', $this->lastProviderErrors),
            'actions' => [],
            '_provider' => 'fallback',
            '_provider_errors' => $this->lastProviderErrors,
        ];
    }

    private function callGemini(string $prompt, float $temperature): string
    {
        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            rawurlencode($this->geminiModel),
            rawurlencode($this->geminiKey)
        );

        $response = $this->client->request('POST', $url, [
            'json' => [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'temperature' => $temperature,
                    'maxOutputTokens' => 1200,
                ],
            ],
            'timeout' => 30,
        ]);

        $data = $response->toArray(false);
        if (isset($data['error'])) {
            $message = is_array($data['error']) ? (string) ($data['error']['message'] ?? json_encode($data['error'])) : (string) $data['error'];
            throw new \RuntimeException($message !== '' ? $message : 'Gemini API returned an error.');
        }

        return (string) ($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
    }

    private function callGroq(string $prompt, float $temperature): string
    {
        $response = $this->client->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->groqKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->groqModel,
                'messages' => [
                    ['role' => 'system', 'content' => 'Return strict JSON only.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $temperature,
                'max_tokens' => 1200,
                'response_format' => ['type' => 'json_object'],
            ],
            'timeout' => 30,
        ]);

        $data = $response->toArray(false);
        if (isset($data['error'])) {
            $message = is_array($data['error']) ? (string) ($data['error']['message'] ?? json_encode($data['error'])) : (string) $data['error'];
            throw new \RuntimeException($message !== '' ? $message : 'Groq API returned an error.');
        }

        return (string) ($data['choices'][0]['message']['content'] ?? '');
    }

    private function extractJson(string $text): ?array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text) ?? $text;
        if (preg_match('/\{.*\}/s', $text, $match)) {
            $decoded = json_decode($match[0], true);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }

    private function sanitizeActions(array $actions): array
    {
        $allowed = ['navigate', 'local_click', 'fill_field', 'submit_form', 'choose_route', 'suggest_routes'];
        return array_values(array_filter($actions, static function ($action) use ($allowed): bool {
            return is_array($action) && in_array((string) ($action['type'] ?? ''), $allowed, true);
        }));
    }

    private function quickRoute(string $goal): ?array
    {
        $goal = strtolower($goal);
        $routes = [
            'forum' => ['/forum', 'Forum'],
            'discussion' => ['/forum', 'Forum'],
            'profile' => ['/profile', 'Profile'],
            'dashboard' => ['/admin', 'Dashboard'],
            'settings' => ['/settings', 'Settings'],
            'residence' => ['/residence', 'Residence'],
            'syndicat' => ['/syndicat', 'Syndicat'],
            'event' => ['/evenement', 'Events'],
            'evenement' => ['/evenement', 'Events'],
            'home' => ['/', 'Home'],
        ];

        foreach ($routes as $needle => [$url, $label]) {
            if (str_contains($goal, $needle) || str_contains($goal, 'go to ' . $needle) || str_contains($goal, 'open ' . $needle)) {
                return ['url' => $url, 'label' => $label];
            }
        }

        return null;
    }

    private function fallbackFeeling(string $text): array
    {
        $lower = strtolower($text);
        $positive = preg_match_all('/\b(good|great|love|happy|thanks|excellent|nice|amazing|perfect)\b/', $lower);
        $negative = preg_match_all('/\b(bad|hate|angry|sad|problem|issue|broken|terrible|slow|urgent)\b/', $lower);

        $sentiment = 'neutral';
        $emotion = 'neutral';
        if ($positive > $negative) {
            $sentiment = 'positive';
            $emotion = 'joy';
        } elseif ($negative > $positive) {
            $sentiment = 'negative';
            $emotion = str_contains($lower, 'angry') || str_contains($lower, 'hate') ? 'anger' : 'sadness';
        } elseif ($positive > 0 && $negative > 0) {
            $sentiment = 'mixed';
            $emotion = 'surprise';
        }

        return [
            'sentiment' => $sentiment,
            'primaryEmotion' => $emotion,
            'confidence' => 55,
            'explanation' => 'This quick local read is based on visible emotional keywords.',
            'signals' => ['keyword tone', 'community context'],
            'provider' => 'local-fallback',
        ];
    }

    private function safeChoice(string $value, array $allowed, string $fallback): string
    {
        $value = strtolower(trim($value));
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function envValue(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        foreach (['.env.local', '.env'] as $file) {
            $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $file;
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_starts_with($line, $key . '=')) {
                    continue;
                }

                $raw = trim(substr($line, strlen($key) + 1));
                return trim($raw, "\"'");
            }
        }

        return $default;
    }

    private function formatConversation(array $messages): string
    {
        return implode("\n", array_map(static function ($message): string {
            $role = (string) ($message['role'] ?? 'user');
            $content = (string) ($message['content'] ?? '');
            return strtoupper($role) . ': ' . $content;
        }, array_slice($messages, -10)));
    }

    private function jsonForPrompt(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
