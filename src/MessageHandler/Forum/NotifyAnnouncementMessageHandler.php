<?php

namespace App\MessageHandler\Forum;

use App\Message\Forum\NotifyAnnouncementMessage;
use App\Repository\Forum\PublicationRepository;
use App\Service\Forum\ForumNotificationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class NotifyAnnouncementMessageHandler
{
    public function __construct(
        private readonly PublicationRepository $publicationRepository,
        private readonly ForumNotificationService $forumNotificationService
    ) {
    }

    public function __invoke(NotifyAnnouncementMessage $message): void
    {
        $publication = $this->publicationRepository->find($message->getPublicationId());
        if (!$publication) {
            return;
        }

        $this->forumNotificationService->notifyNewAnnouncement($publication);
    }
}
