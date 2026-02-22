<?php

namespace App\Controller\Forum;

use App\Entity\Forum\Publication;
use App\Entity\Forum\PublicationReaction;
use App\Entity\Forum\PublicationBookmark;
use App\Entity\Forum\PublicationReport;
use App\Entity\Forum\Commentaire;
use App\Entity\Forum\CommentReaction;
use App\Entity\Forum\CommentReport;
use App\Entity\User\User;
use App\Repository\Forum\PublicationRepository;
use App\Repository\Forum\PublicationReactionRepository;
use App\Repository\Forum\PublicationBookmarkRepository;
use App\Repository\Forum\PublicationReportRepository;
use App\Repository\Forum\CommentaireRepository;
use App\Repository\Forum\CommentReactionRepository;
use App\Repository\Forum\CommentReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/forum/action')]
class ForumActionController extends AbstractController
{
    #[Route('/react/{id}/{type}', name: 'forum_publication_react', methods: ['POST'])]
    public function react(
        int $id,
        string $type,
        Request $request,
        PublicationRepository $publicationRepository,
        PublicationReactionRepository $reactionRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $publication = $publicationRepository->find($id);
        $userSession = $request->getSession()->get('user');
        $userId = $this->getUserIdFromSession($userSession);

        if (!$publication || !$userId) {
            return new JsonResponse(['success' => false, 'message' => 'Publication not found or not logged in'], 404);
        }

        if (!in_array($type, ['like', 'dislike'])) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid reaction type'], 400);
        }

        $user = $entityManager->getRepository(User::class)->find($userId);
        $existingReaction = $reactionRepository->findOneBy([
            'publication' => $publication,
            'user' => $user
        ]);

        if ($existingReaction) {
            if ($existingReaction->getReactionType() === $type) {
                // Remove reaction if clicking same type
                $entityManager->remove($existingReaction);
                $message = 'Reaction removed';
            } else {
                // Update reaction type
                $existingReaction->setReactionType($type);
                $message = 'Reaction updated';
            }
        } else {
            // Create new reaction
            $reaction = new PublicationReaction();
            $reaction->setPublication($publication);
            $reaction->setUser($user);
            $reaction->setReactionType($type);
            $entityManager->persist($reaction);
            $message = 'Reaction added';
        }

        $entityManager->flush();

        // Calculate New Totals for live sync
        $likes = $reactionRepository->count(['publication' => $publication, 'reaction_type' => 'like']);
        $dislikes = $reactionRepository->count(['publication' => $publication, 'reaction_type' => 'dislike']);

        return new JsonResponse([
            'success' => true, 
            'message' => $message,
            'counts' => [
                'likes' => $likes,
                'dislikes' => $dislikes
            ]
        ]);
    }

    #[Route('/bookmark/{id}', name: 'forum_publication_bookmark', methods: ['POST'])]
    public function bookmark(
        int $id,
        Request $request,
        PublicationRepository $publicationRepository,
        PublicationBookmarkRepository $bookmarkRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $publication = $publicationRepository->find($id);
        $userSession = $request->getSession()->get('user');
        $userId = $this->getUserIdFromSession($userSession);

        if (!$publication || !$userId) {
            return new JsonResponse(['success' => false, 'message' => 'Publication not found or not logged in'], 404);
        }

        $user = $entityManager->getRepository(User::class)->find($userId);
        $existingBookmark = $bookmarkRepository->findOneBy([
            'publication' => $publication,
            'user' => $user
        ]);

        if ($existingBookmark) {
            $entityManager->remove($existingBookmark);
            $message = 'Bookmark removed';
        } else {
            $bookmark = new PublicationBookmark();
            $bookmark->setPublication($publication);
            $bookmark->setUser($user);
            $bookmark->setBookmark(true);
            $entityManager->persist($bookmark);
            $message = 'Bookmark added';
        }

        $entityManager->flush();

        return new JsonResponse(['success' => true, 'message' => $message]);
    }

    #[Route('/report/{id}', name: 'forum_publication_report', methods: ['POST'])]
    public function report(
        int $id,
        Request $request,
        PublicationRepository $publicationRepository,
        PublicationReportRepository $reportRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $publication = $publicationRepository->find($id);
        $userSession = $request->getSession()->get('user');
        $userId = $this->getUserIdFromSession($userSession);

        if (!$publication || !$userId) {
            return new JsonResponse(['success' => false, 'message' => 'Publication not found or not logged in'], 404);
        }

        $user = $entityManager->getRepository(User::class)->find($userId);
        $existingReport = $reportRepository->findOneBy([
            'publication' => $publication,
            'user' => $user
        ]);

        if ($existingReport) {
            $entityManager->remove($existingReport);
            $entityManager->flush();
            $reports = $reportRepository->count(['publication' => $publication]);
            return new JsonResponse([
                'success' => true, 
                'message' => 'Report removed',
                'counts' => [
                    'reports' => $reports
                ]
            ]);
        }

        $report = new PublicationReport();
        $report->setPublication($publication);
        $report->setUser($user);
        $report->setSignal(true);
        $entityManager->persist($report);
        $entityManager->flush();

        // Calculate New Totals for live sync
        $reports = $reportRepository->count(['publication' => $publication]);

        return new JsonResponse([
            'success' => true, 
            'message' => 'Publication reported successfully',
            'counts' => [
                'reports' => $reports
            ]
        ]);
    }

    #[Route('/comment/react/{id}/{type}', name: 'forum_comment_react', methods: ['POST'])]
    public function reactComment(
        int $id,
        string $type,
        Request $request,
        CommentaireRepository $commentRepository,
        CommentReactionRepository $reactionRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $comment = $commentRepository->find($id);
        $userSession = $request->getSession()->get('user');
        $userId = $this->getUserIdFromSession($userSession);

        if (!$comment || !$userId) {
            return new JsonResponse(['success' => false, 'message' => 'Comment not found or not logged in'], 404);
        }

        if (!in_array($type, ['like', 'dislike'])) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid reaction type'], 400);
        }

        $user = $entityManager->getRepository(User::class)->find($userId);
        $existingReaction = $reactionRepository->findOneBy([
            'comment' => $comment,
            'user' => $user
        ]);

        if ($existingReaction) {
            if ($existingReaction->getReactionType() === $type) {
                $entityManager->remove($existingReaction);
                $message = 'Reaction removed';
            } else {
                $existingReaction->setReactionType($type);
                $message = 'Reaction updated';
            }
        } else {
            $reaction = new CommentReaction();
            $reaction->setComment($comment);
            $reaction->setUser($user);
            $reaction->setReactionType($type);
            $entityManager->persist($reaction);
            $message = 'Reaction added';
        }

        $entityManager->flush();

        $likes = $reactionRepository->count(['comment' => $comment, 'reaction_type' => 'like']);
        $dislikes = $reactionRepository->count(['comment' => $comment, 'reaction_type' => 'dislike']);

        return new JsonResponse([
            'success' => true, 
            'message' => $message,
            'counts' => [
                'likes' => $likes,
                'dislikes' => $dislikes
            ]
        ]);
    }

    #[Route('/comment/report/{id}', name: 'forum_comment_report', methods: ['POST'])]
    public function reportComment(
        int $id,
        Request $request,
        CommentaireRepository $commentRepository,
        CommentReportRepository $reportRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $comment = $commentRepository->find($id);
        $userSession = $request->getSession()->get('user');
        $userId = $this->getUserIdFromSession($userSession);

        if (!$comment || !$userId) {
            return new JsonResponse(['success' => false, 'message' => 'Comment not found or not logged in'], 404);
        }

        $user = $entityManager->getRepository(User::class)->find($userId);
        $existingReport = $reportRepository->findOneBy([
            'comment' => $comment,
            'user' => $user
        ]);

        if ($existingReport) {
            $entityManager->remove($existingReport);
            $entityManager->flush();
            $reports = $reportRepository->count(['comment' => $comment]);
            return new JsonResponse([
                'success' => true, 
                'message' => 'Report removed',
                'counts' => [
                    'reports' => $reports
                ]
            ]);
        }

        $report = new CommentReport();
        $report->setComment($comment);
        $report->setUser($user);
        $report->setSignal(true);
        $entityManager->persist($report);
        $entityManager->flush();

        $reports = $reportRepository->count(['comment' => $comment]);

        return new JsonResponse([
            'success' => true, 
            'message' => 'Comment reported successfully',
            'counts' => [
                'reports' => $reports
            ]
        ]);
    }

    private function getUserIdFromSession($userSession): ?int
    {
        if (is_object($userSession) && method_exists($userSession, 'getIdUser')) {
            return $userSession->getIdUser();
        } elseif (is_object($userSession) && method_exists($userSession, 'getId')) {
            return $userSession->getId();
        } elseif (is_array($userSession)) {
            return $userSession['id_user'] ?? $userSession['id'] ?? null;
        }
        return null;
    }
}
