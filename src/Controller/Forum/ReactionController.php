<?php

namespace App\Controller\Forum;

use App\Controller\Concerns\SessionUserAwareTrait;
use App\Entity\Forum\Reaction;
use App\Form\Forum\ReactionType;
use App\Repository\Forum\ReactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/forum/reaction')]
class ReactionController extends AbstractController
{
    use SessionUserAwareTrait;

    public function __construct(
        private readonly \App\Service\User\NotificationService $notifService
    ) {
    }
    #[Route('/toggle/{publicationId}/{kind}', name: 'app_forum_reaction_toggle', methods: ['POST'])]
    public function toggle(
        int $publicationId,
        string $kind,
        Request $request,
        ReactionRepository $reactionRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $userSession = $request->getSession()->get('user');
        if (!$userSession) {
            return new JsonResponse(['success' => false, 'message' => 'You must be logged in to react.'], 401);
        }

        $userId = is_object($userSession) ? $userSession->getIdUser() : ($userSession['id_user'] ?? $userSession['id'] ?? null);
        if (!$userId) {
            return new JsonResponse(['success' => false, 'message' => 'User ID not found in session.'], 401);
        }

        $user = $entityManager->getRepository(\App\Entity\User\User::class)->find($userId);
        $publication = $entityManager->getRepository(\App\Entity\Forum\Publication::class)->find($publicationId);

        if (!$user || !$publication) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid user or publication.'], 404);
        }

        // Check if reaction already exists
        $existing = $reactionRepository->findOneBy([
            'user' => $user,
            'publication' => $publication,
            'kind' => $kind
        ]);

        if ($existing) {
            $entityManager->remove($existing);
            $entityManager->flush();
            return new JsonResponse(['success' => true, 'action' => 'removed']);
        }

        // Remove opposite if Like/Dislike, AND remove Emoji if Like/Dislike
        if (in_array($kind, ['Like', 'Dislike'])) {
            // Remove opposite
            $opposite = $kind === 'Like' ? 'Dislike' : 'Like';
            $oppositeReaction = $reactionRepository->findOneBy([
                'user' => $user,
                'publication' => $publication,
                'kind' => $opposite
            ]);
            if ($oppositeReaction) {
                $entityManager->remove($oppositeReaction);
            }

            // Remove Emoji if exists (Mutual Exclusive with Like/Dislike)
            $emojiReaction = $reactionRepository->findOneBy([
                'user' => $user,
                'publication' => $publication,
                'kind' => 'Emoji'
            ]);
            if ($emojiReaction) {
                $entityManager->remove($emojiReaction);
            }
        }

        $reaction = new Reaction();
        $reaction->setUser($user);
        $reaction->setPublication($publication);
        $reaction->setKind($kind);
        $reaction->setCreatedAt(new \DateTime());
        $reaction->setUpdatedAt(new \DateTime());

        $entityManager->persist($reaction);
        $entityManager->flush();

        // Notify content owner
        $author = $publication->getUser();
        if ($author && $author->getIdUser() !== $user->getIdUser()) {
            $this->notifService->notify(
                $author,
                'REACTION_NEW',
                'REACTION',
                $reaction->getIdReaction(),
                'Nouvelle réaction',
                $user->getFirstName() . ' a réagi (' . $kind . ') à votre publication.'
            );
        }

        return new JsonResponse([
            'success' => true,
            'action' => 'added',
            'counts' => [
                'Like' => $reactionRepository->countByPublicationAndKind($publicationId, 'Like'),
                'Dislike' => $reactionRepository->countByPublicationAndKind($publicationId, 'Dislike')
            ]
        ]);
    }

    #[Route('/emoji/{publicationId}', name: 'app_forum_reaction_emoji', methods: ['POST'])]
    public function reactEmoji(
        int $publicationId,
        Request $request,
        ReactionRepository $reactionRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $userSession = $request->getSession()->get('user');
        $emoji = $request->request->get('emoji');

        if (!$userSession || !$emoji) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request.'], 400);
        }

        $userId = is_object($userSession) ? $userSession->getIdUser() : ($userSession['id_user'] ?? $userSession['id'] ?? null);
        if (!$userId) {
            return new JsonResponse(['success' => false, 'message' => 'User ID not found in session.'], 401);
        }

        $user = $entityManager->getRepository(\App\Entity\User\User::class)->find($userId);
        $publication = $entityManager->getRepository(\App\Entity\Forum\Publication::class)->find($publicationId);

        if (!$user || !$publication) {
            return new JsonResponse(['success' => false, 'message' => 'Not found.'], 404);
        }

        // For emojis, we typically allow one emoji reaction per user per post.
        // Emojis are mutually exclusive with Like and Dislike.
        $conflicts = $reactionRepository->findBy([
            'user' => $user,
            'publication' => $publication,
            'kind' => ['Like', 'Dislike']
        ]);
        foreach ($conflicts as $conf) {
            $entityManager->remove($conf);
        }

        $existing = $reactionRepository->findOneBy([
            'user' => $user,
            'publication' => $publication,
            'kind' => 'Emoji'
        ]);

        if ($existing) {
            if ($existing->getEmoji() === $emoji) {
                $entityManager->remove($existing);
                $entityManager->flush();
                return new JsonResponse(['success' => true, 'action' => 'removed']);
            }
            $existing->setEmoji($emoji);
            $existing->setUpdatedAt(new \DateTime());
        } else {
            $reaction = new Reaction();
            $reaction->setUser($user);
            $reaction->setPublication($publication);
            $reaction->setKind('Emoji');
            $reaction->setEmoji($emoji);
            $reaction->setCreatedAt(new \DateTime());
            $reaction->setUpdatedAt(new \DateTime());
            $entityManager->persist($reaction);
        }

        $entityManager->flush();

        // Notify content owner
        $author = $publication->getUser();
        if ($author && $author->getIdUser() !== $user->getIdUser()) {
            $this->notifService->notify(
                $author,
                'REACTION_NEW',
                'REACTION',
                ($existing ? $existing->getIdReaction() : ($reaction->getIdReaction() ?? 0)),
                'Nouvelle réaction',
                $user->getFirstName() . ' a réagi avec ' . $emoji . ' à votre publication.'
            );
        }
        return new JsonResponse([
            'success' => true,
            'action' => 'added',
            'emoji' => $emoji,
            'counts' => [
                'Like' => $reactionRepository->countByPublicationAndKind($publicationId, 'Like'),
                'Dislike' => $reactionRepository->countByPublicationAndKind($publicationId, 'Dislike')
            ]
        ]);
    }

    #[Route('/report/{publicationId}', name: 'app_forum_reaction_report', methods: ['POST'])]
    public function report(
        int $publicationId,
        Request $request,
        EntityManagerInterface $entityManager,
        \App\Service\Forum\ForumNotificationService $notificationService
    ): JsonResponse {
        $userSession = $request->getSession()->get('user');
        $reason = $request->request->get('reason');

        if (!$userSession || !$reason) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid report.'], 400);
        }

        $userId = is_object($userSession) ? $userSession->getIdUser() : ($userSession['id_user'] ?? $userSession['id'] ?? null);
        if (!$userId) {
            return new JsonResponse(['success' => false, 'message' => 'User ID not found in session.'], 401);
        }

        $user = $entityManager->getRepository(\App\Entity\User\User::class)->find($userId);
        $publication = $entityManager->getRepository(\App\Entity\Forum\Publication::class)->find($publicationId);

        if (!$user || !$publication) {
            return new JsonResponse(['success' => false, 'message' => 'Not found.'], 404);
        }

        $reaction = new Reaction();
        $reaction->setUser($user);
        $reaction->setPublication($publication);
        $reaction->setKind('Report');
        $reaction->setReportReason($reason);
        $reaction->setCreatedAt(new \DateTime());
        $reaction->setUpdatedAt(new \DateTime());

        $entityManager->persist($reaction);
        $entityManager->flush();

        // Send Confirmation Email
        $notificationService->notifyReportConfirmation($publication, $user, $reason);

        return new JsonResponse(['success' => true, 'message' => 'Report submitted. Thank you.']);
    }

    #[Route('/status/{publicationId}', name: 'app_forum_reaction_status', methods: ['GET'])]
    public function status(int $publicationId, Request $request, ReactionRepository $reactionRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $userSession = $request->getSession()->get('user');
        if (!$userSession) {
            return new JsonResponse(['reactions' => []]);
        }

        $userId = is_object($userSession) ? $userSession->getIdUser() : ($userSession['id_user'] ?? $userSession['id'] ?? null);
        if (!$userId) {
            return new JsonResponse(['reactions' => [], 'counts' => []]);
        }
        $user = $entityManager->getRepository(\App\Entity\User\User::class)->find($userId);

        $reactions = $reactionRepository->findBy([
            'user' => $user,
            'publication' => $entityManager->getRepository(\App\Entity\Forum\Publication::class)->find($publicationId)
        ]);

        $data = [];
        foreach ($reactions as $r) {
            $data[] = [
                'kind' => $r->getKind(),
                'emoji' => $r->getEmoji()
            ];
        }

        return new JsonResponse([
            'reactions' => $data,
            'counts' => [
                'Like' => $reactionRepository->countByPublicationAndKind($publicationId, 'Like'),
                'Dislike' => $reactionRepository->countByPublicationAndKind($publicationId, 'Dislike')
            ]
        ]);
    }

    // --- COMMENT REACTIONS ---

    #[Route('/comment/toggle/{commentId}/{kind}', name: 'app_forum_reaction_comment_toggle', methods: ['POST'])]
    public function toggleComment(int $commentId, string $kind, Request $request, ReactionRepository $reactionRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $userSession = $request->getSession()->get('user');
        if (!$userSession)
            return new JsonResponse(['success' => false, 'message' => 'Login required.'], 401);

        $userId = is_object($userSession) ? $userSession->getIdUser() : ($userSession['id_user'] ?? $userSession['id'] ?? null);
        $user = $entityManager->getRepository(\App\Entity\User\User::class)->find($userId);
        $comment = $entityManager->getRepository(\App\Entity\Forum\Commentaire::class)->find($commentId);

        if (!$user || !$comment)
            return new JsonResponse(['success' => false, 'message' => 'Not found.'], 404);

        $existing = $reactionRepository->findOneBy(['user' => $user, 'commentaire' => $comment, 'kind' => $kind]);

        if ($existing) {
            $entityManager->remove($existing);
            $entityManager->flush();
            return new JsonResponse(['success' => true, 'action' => 'removed']);
        }

        if (in_array($kind, ['Like', 'Dislike'])) {
            $opposite = $kind === 'Like' ? 'Dislike' : 'Like';
            $oppositeReaction = $reactionRepository->findOneBy(['user' => $user, 'commentaire' => $comment, 'kind' => $opposite]);
            if ($oppositeReaction)
                $entityManager->remove($oppositeReaction);

            $emojiReaction = $reactionRepository->findOneBy(['user' => $user, 'commentaire' => $comment, 'kind' => 'Emoji']);
            if ($emojiReaction)
                $entityManager->remove($emojiReaction);
        }

        $reaction = new Reaction();
        $reaction->setUser($user);
        $reaction->setCommentaire($comment);
        $reaction->setKind($kind);
        $entityManager->persist($reaction);
        $entityManager->flush();

        // Notify comment owner
        $commentAuthor = $comment->getUser();
        if ($commentAuthor && $commentAuthor->getIdUser() !== $user->getIdUser()) {
            $this->notifService->notify(
                $commentAuthor,
                'REACTION_NEW',
                'REACTION',
                $reaction->getIdReaction(),
                'Nouvelle réaction',
                $user->getFirstName() . ' a réagi (' . $kind . ') à votre commentaire.'
            );
        }

        return new JsonResponse([
            'success' => true,
            'action' => 'added',
            'counts' => [
                'Like' => $reactionRepository->countByCommentAndKind($commentId, 'Like'),
                'Dislike' => $reactionRepository->countByCommentAndKind($commentId, 'Dislike')
            ]
        ]);
    }

    #[Route('/comment/emoji/{commentId}', name: 'app_forum_reaction_comment_emoji', methods: ['POST'])]
    public function reactEmojiComment(int $commentId, Request $request, ReactionRepository $reactionRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $userSession = $request->getSession()->get('user');
        $emoji = $request->request->get('emoji');
        if (!$userSession || !$emoji)
            return new JsonResponse(['success' => false], 400);

        $userId = is_object($userSession) ? $userSession->getIdUser() : ($userSession['id_user'] ?? $userSession['id'] ?? null);
        $user = $entityManager->getRepository(\App\Entity\User\User::class)->find($userId);
        $comment = $entityManager->getRepository(\App\Entity\Forum\Commentaire::class)->find($commentId);

        if (!$user || !$comment)
            return new JsonResponse(['success' => false], 404);

        $conflicts = $reactionRepository->findBy(['user' => $user, 'commentaire' => $comment, 'kind' => ['Like', 'Dislike']]);
        foreach ($conflicts as $conf)
            $entityManager->remove($conf);

        $existing = $reactionRepository->findOneBy(['user' => $user, 'commentaire' => $comment, 'kind' => 'Emoji']);
        if ($existing) {
            if ($existing->getEmoji() === $emoji) {
                $entityManager->remove($existing);
                $entityManager->flush();
                return new JsonResponse(['success' => true, 'action' => 'removed']);
            }
            $existing->setEmoji($emoji);
        } else {
            $reaction = (new Reaction())->setUser($user)->setCommentaire($comment)->setKind('Emoji')->setEmoji($emoji);
            $entityManager->persist($reaction);
        }

        $entityManager->flush();

        // Notify comment owner
        $commentAuthor = $comment->getUser();
        if ($commentAuthor && $commentAuthor->getIdUser() !== $user->getIdUser()) {
            $this->notifService->notify(
                $commentAuthor,
                'REACTION_NEW',
                'REACTION',
                ($existing ? $existing->getIdReaction() : ($reaction->getIdReaction() ?? 0)),
                'Nouvelle réaction',
                $user->getFirstName() . ' a réagi avec ' . $emoji . ' à votre commentaire.'
            );
        }

        return new JsonResponse([
            'success' => true,
            'action' => 'added',
            'emoji' => $emoji,
            'counts' => [
                'Like' => $reactionRepository->countByCommentAndKind($commentId, 'Like'),
                'Dislike' => $reactionRepository->countByCommentAndKind($commentId, 'Dislike')
            ]
        ]);
    }

    #[Route('/comment/report/{commentId}', name: 'app_forum_reaction_comment_report', methods: ['POST'])]
    public function reportComment(
        int $commentId,
        Request $request,
        EntityManagerInterface $entityManager,
        \App\Service\Forum\ForumNotificationService $notificationService
    ): JsonResponse {
        $userSession = $request->getSession()->get('user');
        $reason = $request->request->get('reason');
        if (!$userSession || !$reason)
            return new JsonResponse(['success' => false], 400);

        $userId = is_object($userSession) ? $userSession->getIdUser() : ($userSession['id_user'] ?? $userSession['id'] ?? null);
        $user = $entityManager->getRepository(\App\Entity\User\User::class)->find($userId);
        $comment = $entityManager->getRepository(\App\Entity\Forum\Commentaire::class)->find($commentId);

        if (!$user || !$comment)
            return new JsonResponse(['success' => false], 404);

        $reaction = (new Reaction())->setUser($user)->setCommentaire($comment)->setKind('Report')->setReportReason($reason);
        $entityManager->persist($reaction);
        $entityManager->flush();

        // Send Confirmation Email
        $notificationService->notifyReportConfirmation($comment, $user, $reason);

        return new JsonResponse(['success' => true, 'message' => 'Comment reported.']);
    }

    #[Route('/comment/status/{commentId}', name: 'app_forum_reaction_comment_status', methods: ['GET'])]
    public function statusComment(int $commentId, Request $request, ReactionRepository $reactionRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $userSession = $request->getSession()->get('user');
        if (!$userSession)
            return new JsonResponse(['reactions' => []]);

        $userId = is_object($userSession) ? $userSession->getIdUser() : ($userSession['id_user'] ?? $userSession['id'] ?? null);
        $user = $entityManager->getRepository(\App\Entity\User\User::class)->find($userId);

        $reactions = $reactionRepository->findBy(['user' => $user, 'commentaire' => $entityManager->getRepository(\App\Entity\Forum\Commentaire::class)->find($commentId)]);
        $data = array_map(fn($r) => ['kind' => $r->getKind(), 'emoji' => $r->getEmoji()], $reactions);

        return new JsonResponse([
            'reactions' => $data,
            'counts' => [
                'Like' => $reactionRepository->countByCommentAndKind($commentId, 'Like'),
                'Dislike' => $reactionRepository->countByCommentAndKind($commentId, 'Dislike')
            ]
        ]);
    }
}
