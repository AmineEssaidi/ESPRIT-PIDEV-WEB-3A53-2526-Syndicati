<?php

namespace App\Controller;

use App\Service\DirectAiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class SyndicatiAgentBootstrapController extends AbstractController
{
    public function __construct(private readonly DirectAiClient $ai)
    {
    }

    #[Route('/syndicati/agent/bootstrap', name: 'syndicati_agent_bootstrap', methods: ['GET'])]
    public function bootstrap(): JsonResponse
    {
        $status = $this->ai->status();
        $ready = ($status['running'] ?? false) === true;

        return $this->json([
            'success' => true,
            'ready' => $ready,
            'directApi' => $status,
            'ollama' => ['running' => $ready, 'model' => $status['model'] ?? null, 'baseUrl' => $status['baseUrl'] ?? null],
            'langgraph' => ['running' => true, 'mode' => 'removed'],
            'playwright' => ['running' => true, 'mode' => 'browser-local-actions'],
        ]);
    }
}
