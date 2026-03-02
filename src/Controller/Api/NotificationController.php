<?php

namespace App\Controller\Api;

use App\Entity\User\User;
use App\Repository\User\NotificationRepository;
use App\Repository\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/notifications')]
class NotificationController extends AbstractController
{
    public function __construct(
        private NotificationRepository $notifRepo,
        private UserRepository $userRepo,
        private EntityManagerInterface $em
    ) {
    }

    #[Route('', name: 'api_notifications_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getLoggedInUser($request);
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $notifications = $this->notifRepo->findLatestByUser($user, 20);
        $unreadCount = $this->notifRepo->countUnreadByUser($user);

        $data = [];
        foreach ($notifications as $notif) {
            $data[] = [
                'id' => $notif->getId(),
                'type' => $notif->getType(),
                'source_type' => $notif->getSourceType(),
                'source_id' => $notif->getSourceId(),
                'title' => $notif->getTitle(),
                'content' => $notif->getContent(),
                'is_read' => $notif->isRead(),
                'created_at' => $notif->getCreatedAt()->format('c'),
            ];
        }

        return $this->json([
            'notifications' => $data,
            'unread_count' => $unreadCount
        ]);
    }

    #[Route('/{id}/read', name: 'api_notifications_read', methods: ['POST'])]
    public function markAsRead(int $id, Request $request): JsonResponse
    {
        $user = $this->getLoggedInUser($request);
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $notification = $this->notifRepo->find($id);
        if (!$notification || $notification->getUser()->getIdUser() !== $user->getIdUser()) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $notification->setIsRead(true);
        $notification->setReadAt(new \DateTime());
        $this->em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/read-all', name: 'api_notifications_read_all', methods: ['POST'])]
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $this->getLoggedInUser($request);
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $unreadNotifs = $this->notifRepo->findUnreadByUser($user);
        foreach ($unreadNotifs as $notif) {
            $notif->setIsRead(true);
            $notif->setReadAt(new \DateTime());
        }
        $this->em->flush();

        return $this->json(['success' => true]);
    }

    private function getLoggedInUser(Request $request): ?User
    {
        $session = $request->getSession();
        $userData = $session->get('user');
        if (!$userData || !isset($userData['id'])) {
            return null;
        }
        return $this->userRepo->find($userData['id']);
    }
}
