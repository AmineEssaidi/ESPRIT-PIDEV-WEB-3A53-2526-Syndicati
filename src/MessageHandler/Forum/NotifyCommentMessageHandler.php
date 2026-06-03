<?php

namespace App\MessageHandler\Forum;

use App\Message\Forum\NotifyCommentMessage;
use App\Repository\Forum\CommentaireRepository;
use App\Service\Forum\ForumNotificationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class NotifyCommentMessageHandler
{
    public function __construct(
        private readonly CommentaireRepository $commentaireRepository,
        private readonly ForumNotificationService $forumNotificationService
    ) {
    }

    public function __invoke(NotifyCommentMessage $message): void
    {
        $commentaire = $this->commentaireRepository->find($message->getCommentId());
        if (!$commentaire) {
            return;
        }

        $this->forumNotificationService->notifyNewComment($commentaire);
    }
}
