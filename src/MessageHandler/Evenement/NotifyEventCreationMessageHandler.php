<?php

namespace App\MessageHandler\Evenement;

use App\Message\Evenement\NotifyEventCreationMessage;
use App\Repository\Evenement\EvenementRepository;
use App\Service\Evenement\EvenementNotificationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class NotifyEventCreationMessageHandler
{
    public function __construct(
        private readonly EvenementRepository $evenementRepository,
        private readonly EvenementNotificationService $evenementNotificationService
    ) {
    }

    public function __invoke(NotifyEventCreationMessage $message): void
    {
        $evenement = $this->evenementRepository->find($message->getEventId());
        if (!$evenement) {
            return;
        }

        $this->evenementNotificationService->notifyEventCreation($evenement);
    }
}
