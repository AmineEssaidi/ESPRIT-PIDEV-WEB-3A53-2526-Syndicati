<?php

namespace App\Controller\Api;

use App\Service\Log\UserActivityLogger;
use App\Entity\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\SecurityBundle\Security;

#[Route('/api/log')]
class ActivityLogController extends AbstractController
{
    public function __construct(
        private UserActivityLogger $logger,
        private Security $security
    ) {
    }

    #[Route('/event', name: 'api_log_event', methods: ['POST'])]
    public function logEvent(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['event_type'])) {
            return $this->json(['success' => false, 'message' => 'Invalid data'], 400);
        }

        $user = $this->security->getUser();

        $this->logger->log(
            $data['event_type'],
            $data['entity_type'] ?? 'UI_ELEMENT',
            $data['entity_id'] ?? null,
            $data['metadata'] ?? [],
            $user instanceof User ? $user : null
        );

        return $this->json(['success' => true]);
    }
}
