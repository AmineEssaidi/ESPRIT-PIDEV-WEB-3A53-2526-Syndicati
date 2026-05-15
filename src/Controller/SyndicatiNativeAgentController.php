<?php

namespace App\Controller;

use App\Service\DirectAiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SyndicatiNativeAgentController extends AbstractController
{
    public function __construct(private readonly DirectAiClient $agent)
    {
    }

    #[Route('/syndicati/agent/execute', name: 'syndicati_agent_execute', methods: ['POST'])]
    public function execute(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $goal = (string) ($data['goal'] ?? '');
        $context = is_array($data['context'] ?? null) ? $data['context'] : [];
        $sessionId = is_string($data['sessionId'] ?? null) ? $data['sessionId'] : null;

        // If context doesn't have URL but frontend sent it, ensure it's set
        if (empty($context['url']) && !empty($data['context']['url'])) {
            $context['url'] = $data['context']['url'];
        }

        if ($goal === '') {
            return $this->json(['success' => false, 'error' => 'No goal provided'], 400);
        }

        try {
            $result = $this->agent->executeAgent($goal, $context, $sessionId);
            return $this->json($result);
        } catch (\Throwable $e) {
            error_log('[SyndicatiNativeAgentController] Execute error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
