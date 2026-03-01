<?php

namespace App\Controller;

use App\Service\LangGraphClient;
use App\Service\OllamaClient;
use App\Service\PlaywrightToolServerManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class SyndicatiAgentBootstrapController extends AbstractController
{
    public function __construct(
        private readonly OllamaClient $ollama,
        private readonly LangGraphClient $langGraph,
        private readonly PlaywrightToolServerManager $playwrightManager,
    ) {
    }

    #[Route('/syndicati/agent/bootstrap', name: 'syndicati_agent_bootstrap', methods: ['GET'])]
    public function bootstrap(): JsonResponse
    {
        $ollama = $this->checkOllama();
        $langgraph = $this->langGraph->checkStatus(true);
        $playwright = $this->playwrightManager->checkStatus(true);

        $ready = (
            ($ollama['running'] ?? false) === true
            && ($langgraph['running'] ?? false) === true
            && ($playwright['running'] ?? false) === true
        );

        return $this->json([
            'success' => true,
            'ready' => $ready,
            'ollama' => $ollama,
            'langgraph' => $langgraph,
            'playwright' => $playwright,
        ]);
    }

    private function checkOllama(): array
    {
        try {
            $data = $this->ollama->chat([
                ['role' => 'system', 'content' => 'You are a health check. Reply with OK.'],
                ['role' => 'user', 'content' => 'OK'],
            ]);

            return [
                'running' => true,
                'model' => $_ENV['OLLAMA_MODEL'] ?? null,
                'baseUrl' => $_ENV['OLLAMA_BASE_URL'] ?? null,
                'raw' => $data,
            ];
        } catch (\Throwable $e) {
            return [
                'running' => false,
                'model' => $_ENV['OLLAMA_MODEL'] ?? null,
                'baseUrl' => $_ENV['OLLAMA_BASE_URL'] ?? null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
