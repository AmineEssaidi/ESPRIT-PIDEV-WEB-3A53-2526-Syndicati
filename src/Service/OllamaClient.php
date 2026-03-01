<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class OllamaClient
{
    private string $baseUrl;
    private string $model;

    public function __construct(
        HttpClientInterface $client,
        ?string $baseUrl = null,
        ?string $model = null
    ) {
        $this->client = $client;
        $this->baseUrl = rtrim($baseUrl ?: ($_ENV['OLLAMA_BASE_URL'] ?? 'http://127.0.0.1:11434'), '/');
        $this->model = $model ?: ($_ENV['OLLAMA_MODEL'] ?? 'phi4-mini:3.8b');
    }

    public function chat(array $messages): array
    {
        try {
            $response = $this->client->request('POST', $this->baseUrl . '/api/chat', [
                'json' => [
                    'model' => $this->model,
                    'messages' => $messages,
                    'stream' => false,
                    'temperature' => 0.7, // Higher temperature for more natural, conversational responses
                    'top_p' => 0.95, // More diverse responses
                    'num_predict' => 300, // Balanced length - not too long to prevent overload
                    'repeat_penalty' => 1.1, // Reduce repetition for more natural conversation
                ],
                'timeout' => 120, // Increased timeout for complex queries
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                throw new \RuntimeException("Ollama API returned status code {$statusCode}");
            }

            $data = $response->toArray(false);

            return $data;
        } catch (\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface $e) {
            throw new \RuntimeException("Failed to connect to Ollama at {$this->baseUrl}. Make sure Ollama is running.", 0, $e);
        } catch (\Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface $e) {
            throw new \RuntimeException("Ollama API error: " . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            throw new \RuntimeException("Error communicating with Ollama: " . $e->getMessage(), 0, $e);
        }
    }
}

