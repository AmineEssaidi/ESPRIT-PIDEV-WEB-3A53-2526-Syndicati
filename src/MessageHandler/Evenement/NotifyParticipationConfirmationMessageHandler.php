<?php

namespace App\MessageHandler\Evenement;

use App\Message\Evenement\NotifyParticipationConfirmationMessage;
use App\Repository\Evenement\ParticipationRepository;
use App\Service\Evenement\EvenementNotificationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class NotifyParticipationConfirmationMessageHandler
{
    public function __construct(
        private readonly ParticipationRepository $participationRepository,
        private readonly EvenementNotificationService $evenementNotificationService
    ) {
    }

    public function __invoke(NotifyParticipationConfirmationMessage $message): void
    {
        $participation = $this->participationRepository->find($message->getParticipationId());
        if (!$participation) {
            return;
        }

        $this->evenementNotificationService->notifyParticipationConfirmation($participation);
    }
}
