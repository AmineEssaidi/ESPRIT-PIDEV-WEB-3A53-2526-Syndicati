<?php

namespace App\Controller\Api;

use App\Entity\Chat\Conversation;
use App\Entity\Chat\ConversationParticipant;
use App\Entity\Chat\Message;
use App\Entity\User\User;
use App\Repository\Chat\ConversationRepository;
use App\Repository\Chat\MessageRepository;
use App\Repository\Profile\ProfileRepository;
use App\Repository\User\UserRepository;
use App\Entity\Chat\MessageAttachment;
use App\Repository\UserRelationship\UserRelationshipRepository;
use App\Service\UserStanding\UserStandingService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

#[Route('/api/messaging')]
class MessagingController extends AbstractController
{
    public function __construct(
        private ConversationRepository $conversationRepo,
        private MessageRepository $messageRepo,
        private UserRepository $userRepo,
        private UserRelationshipRepository $relationshipRepo,
        private ProfileRepository $profileRepo,
        private EntityManagerInterface $em,
        private \App\Service\User\NotificationService $notifService
    ) {
    }

    #[Route('/me', name: 'api_messaging_me', methods: ['GET'])]
    public function me(Request $request): JsonResponse
    {
        $user = $this->getLoggedInUser($request);
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }
        return $this->json(['id' => $user->getIdUser()]);
    }

    #[Route('/conversations', name: 'api_messaging_conversations', methods: ['GET'])]
    public function getConversations(Request $request): JsonResponse
    {
        $user = $this->getLoggedInUser($request);
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }
        session_write_close();

        $conversations = $this->conversationRepo->findUserConversations($user);
        $otherParticipants = [];
        foreach ($conversations as $conv) {
            if ($conv->isGroup()) {
                continue;
            }
            foreach ($conv->getParticipants() as $participant) {
                $participantUser = $participant->getUser();
                if ($participantUser->getIdUser() !== $user->getIdUser()) {
                    $otherParticipants[$participantUser->getIdUser()] = $participantUser;
                    break;
                }
            }
        }
        $profileMap = $this->buildProfileMap(array_values($otherParticipants));
        $data = [];

        foreach ($conversations as $conv) {
            $otherParticipant = null;
            if (!$conv->isGroup()) {
                foreach ($conv->getParticipants() as $participant) {
                    if ($participant->getUser()->getIdUser() !== $user->getIdUser()) {
                        $otherParticipant = $participant->getUser();
                        break;
                    }
                }
                if (!$otherParticipant)
                    continue;
            }

            $lastMessage = $conv->getMessages()->last();
            $avatar = null;
            $name = $conv->getName();

            if (!$conv->isGroup()) {
                $profile = $otherParticipant ? ($profileMap[$otherParticipant->getIdUser()] ?? null) : null;
                $avatar = $profile ? $profile->getAvatar() : null;
                $name = $otherParticipant->getFirstName() . ' ' . $otherParticipant->getLastName();
            }

            $data[] = [
                'id' => $conv->getId(),
                'name' => $name,
                'avatar' => $avatar,
                'is_group' => $conv->isGroup(),
                'creator_id' => $conv->getCreator() ? $conv->getCreator()->getIdUser() : null,
                'other_user_id' => $otherParticipant ? $otherParticipant->getIdUser() : null,
                'last_message' => $lastMessage ? [
                    'content' => $lastMessage->getContent(),
                    'created_at' => $lastMessage->getCreatedAt() ? $lastMessage->getCreatedAt()->format('Y-m-d H:i:s') : date('Y-m-d H:i:s'),
                    'is_user' => $lastMessage->getSender() ? ($lastMessage->getSender()->getIdUser() === $user->getIdUser()) : false
                ] : null,
                'created_at' => $conv->getCreatedAt() ? $conv->getCreatedAt()->format('Y-m-d H:i:s') : date('Y-m-d H:i:s'),
            ];
        }

        return $this->json($data);
    }

    #[Route('/messages/{id}', name: 'api_messaging_messages', methods: ['GET'])]
    public function getMessages(int $id, Request $request): JsonResponse
    {
        $user = $this->getLoggedInUser($request);
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }
        session_write_close();

        $conversation = $this->conversationRepo->find($id);
        if (!$conversation) {
            return $this->json(['error' => 'Conversation not found'], 404);
        }

        // Security check: is user a participant?
        $isParticipant = false;
        foreach ($conversation->getParticipants() as $p) {
            if ($p->getUser()->getIdUser() === $user->getIdUser()) {
                $isParticipant = true;
                break;
            }
        }

        if (!$isParticipant) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $messages = $this->messageRepo->findConversationMessages($conversation);
        $data = [];

        foreach ($messages as $msg) {
            $attachments = [];
            foreach ($msg->getAttachments() as $att) {
                $attachments[] = [
                    'path' => $this->normalizePublicPath($att->getFilePath()),
                    'name' => $att->getOriginalName(),
                    'kind' => $att->getKind(),
                    'size' => $att->getSizeBytes()
                ];
            }

            $sender = $msg->getSender();
            $data[] = [
                'id' => $msg->getId(),
                'sender_id' => $sender ? $sender->getIdUser() : null,
                'sender_name' => $sender ? ($sender->getFirstName() . ' ' . $sender->getLastName()) : 'Unknown User',
                'content' => $msg->getContent(),
                'attachments' => $attachments,
                'created_at' => $msg->getCreatedAt() ? $msg->getCreatedAt()->format('Y-m-d H:i:s') : date('Y-m-d H:i:s'),
                'is_user' => $sender ? ($sender->getIdUser() === $user->getIdUser()) : false
            ];
        }

        return $this->json($data);
    }

    #[Route('/send', name: 'api_messaging_send', methods: ['POST'])]
    public function sendMessage(Request $request): JsonResponse
    {
        $user = $this->getLoggedInUser($request);
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $content = $request->request->get('content', '');
        $recipientId = $request->request->get('recipient_id');
        $conversationId = $request->request->get('conversation_id');

        if (empty($content) && count($request->files) === 0) {
            // Check for JSON payload if form-data is empty
            $payload = json_decode($request->getContent(), true);
            if ($payload) {
                $content = $payload['content'] ?? '';
                $recipientId = $payload['recipient_id'] ?? null;
                $conversationId = $payload['conversation_id'] ?? null;
            }
        }

        if (empty($content) && count($request->files) === 0) {
            return $this->json(['error' => 'Empty message'], 400);
        }

        $conversation = null;

        if ($conversationId) {
            $conversation = $this->conversationRepo->find($conversationId);
        } elseif ($recipientId) {
            $recipient = $this->userRepo->find($recipientId);
            if (!$recipient) {
                return $this->json(['error' => 'Recipient not found'], 404);
            }

            $conversation = $this->conversationRepo->findExistingConversation($user, $recipient);

            if (!$conversation) {
                // Create new conversation
                $conversation = new Conversation();
                $conversation->setCreatedAt(new \DateTime());
                $this->em->persist($conversation);

                $p1 = new ConversationParticipant();
                $p1->setConversation($conversation);
                $p1->setUser($user);
                $p1->setJoinedAt(new \DateTime());
                $this->em->persist($p1);

                $p2 = new ConversationParticipant();
                $p2->setConversation($conversation);
                $p2->setUser($recipient);
                $p2->setJoinedAt(new \DateTime());
                $this->em->persist($p2);

                $this->em->flush();
            }
        }

        if (!$conversation) {
            return $this->json(['error' => 'Target not found'], 400);
        }

        // Check if user is participant and not banned
        $me = $this->em->getRepository(ConversationParticipant::class)->findOneBy([
            'conversation' => $conversation,
            'user' => $user
        ]);
        if (!$me || $me->isBanned()) {
            return $this->json(['error' => 'Banned or not a participant'], 403);
        }

        $message = new Message();
        $message->setConversation($conversation);
        $message->setSender($user);
        $message->setContent($content);
        $message->setCreatedAt(new \DateTime());
        $message->setIsRead(false);

        $this->em->persist($message);

        // Handle attachments
        foreach ($this->flattenUploadedFiles($request->files->all()) as $file) {
            /** @var UploadedFile $file */
            $originalName = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $size = $file->getSize();

            $kind = 'FILE';
            if (str_starts_with($mimeType, 'image/'))
                $kind = 'IMAGE';
            elseif (str_starts_with($mimeType, 'video/'))
                $kind = 'VIDEO';
            elseif (str_starts_with($mimeType, 'audio/'))
                $kind = 'AUDIO';

            $newFilename = uniqid() . '_' . $originalName;
            $newFilename = preg_replace('/[^A-Z0-9._-]/i', '_', $newFilename);
            $file->move($this->getParameter('kernel.project_dir') . '/public/messaging_attachments', $newFilename);

            $attachment = new MessageAttachment();
            $attachment->setMessage($message);
            $attachment->setUploader($user);
            $attachment->setFilePath('messaging_attachments/' . $newFilename);
            $attachment->setOriginalName($originalName);
            $attachment->setMimeType($mimeType);
            $attachment->setSizeBytes((string) $size);
            $attachment->setKind($kind);
            $this->em->persist($attachment);
        }

        $this->em->flush();

        // Create notifications
        // 1. For the sender (Confirmation)
        $this->notifService->notify(
            $user,
            'SUCCESS',
            'MESSAGE',
            $message->getId(),
            'Message envoyé',
            'Votre message a été transmis avec succès.'
        );

        // 2. For the receiver (Alert)
        foreach ($conversation->getParticipants() as $p) {
            if ($p->getUser()->getIdUser() !== $user->getIdUser()) {
                $this->notifService->notify(
                    $p->getUser(),
                    'MESSAGE_NEW',
                    'MESSAGE',
                    $message->getId(),
                    'Nouveau message',
                    $user->getFirstName() . ': ' . (strlen($content) > 30 ? substr($content, 0, 27) . '...' : $content)
                );
            }
        }

        return $this->json([
            'success' => true,
            'id' => $message->getId(),
            'conversation_id' => $conversation->getId(),
            'created_at' => $message->getCreatedAt()->format('c')
        ]);
    }

    #[Route('/friends', name: 'api_messaging_friends', methods: ['GET'])]
    public function getFriends(Request $request): JsonResponse
    {
        $user = $this->getLoggedInUser($request);
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }
        session_write_close();

        $friends = $this->relationshipRepo->findFriends($user);
        $profileMap = $this->buildProfileMap($friends);
        $data = [];

        foreach ($friends as $friend) {
            $profile = $profileMap[$friend->getIdUser()] ?? null;
            $data[] = [
                'id' => $friend->getIdUser(),
                'name' => $friend->getFirstName() . ' ' . $friend->getLastName(),
                'avatar' => $profile ? $profile->getAvatar() : null,
                'role' => $friend->getRoleUser()
            ];
        }

        return $this->json($data);
    }
    #[Route('/create-group', name: 'api_messaging_create_group', methods: ['POST'])]
    public function createGroup(Request $request): JsonResponse
    {
        $user = $this->getLoggedInUser($request);
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized', 'success' => false], 401);
        }

        $content = $request->getContent();
        $payload = json_decode($content, true);

        if (!$payload) {
            return $this->json(['error' => 'Invalid JSON payload', 'success' => false], 400);
        }

        $name = $payload['name'] ?? 'Group Chat';
        $userIds = $payload['participants'] ?? [];

        if (empty($userIds)) {
            return $this->json(['error' => 'No participants selected', 'success' => false], 400);
        }

        try {
            $conversation = new Conversation();
            $conversation->setName($name);
            $conversation->setIsGroup(true);
            $conversation->setCreator($user);
            $this->em->persist($conversation);

            // Add creator
            $p = new ConversationParticipant();
            $p->setConversation($conversation);
            $p->setUser($user);
            $this->em->persist($p);

            // Add others
            foreach ($userIds as $uid) {
                $other = $this->userRepo->find($uid);
                if ($other && $uid !== $user->getIdUser()) {
                    $cp = new ConversationParticipant();
                    $cp->setConversation($conversation);
                    $cp->setUser($other);
                    $this->em->persist($cp);
                }
            }

            $this->em->flush();
            return $this->json(['success' => true, 'conversation_id' => $conversation->getId()]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('/manage-participant', name: 'api_messaging_manage_participant', methods: ['POST'])]
    public function manageParticipant(Request $request): JsonResponse
    {
        $user = $this->getLoggedInUser($request);
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $payload = json_decode($request->getContent(), true);
        $convId = $payload['conversation_id'] ?? null;
        $targetId = $payload['target_user_id'] ?? null;
        $action = $payload['action'] ?? ''; // kick, ban

        $conversation = $this->conversationRepo->find($convId);
        if (!$conversation || !$conversation->isGroup() || $conversation->getCreator()->getIdUser() !== $user->getIdUser()) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $targetPart = $this->em->getRepository(ConversationParticipant::class)->findOneBy([
            'conversation' => $conversation,
            'user' => $this->userRepo->find($targetId)
        ]);

        if (!$targetPart)
            return $this->json(['error' => 'Not found'], 404);

        if ($action === 'ban') {
            $targetPart->setIsBanned(true);
        } else {
            $this->em->remove($targetPart);
        }

        $this->em->flush();
        return $this->json(['success' => true]);
    }

    #[Route('/participants/{id}', name: 'api_messaging_participants', methods: ['GET'])]
    public function getParticipants(int $id, Request $request): JsonResponse
    {
        $user = $this->getLoggedInUser($request);
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }
        session_write_close();

        $conversation = $this->conversationRepo->find($id);
        if (!$conversation) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $participantUsers = [];
        foreach ($conversation->getParticipants() as $participant) {
            $participantUsers[$participant->getUser()->getIdUser()] = $participant->getUser();
        }
        $profileMap = $this->buildProfileMap(array_values($participantUsers));

        $data = [];
        foreach ($conversation->getParticipants() as $p) {
            $profile = $profileMap[$p->getUser()->getIdUser()] ?? null;
            $data[] = [
                'id' => $p->getUser()->getIdUser(),
                'name' => $p->getUser()->getFirstName() . ' ' . $p->getUser()->getLastName(),
                'avatar' => $profile ? $profile->getAvatar() : null,
                'is_banned' => $p->isBanned(),
                'is_creator' => $conversation->getCreator() && $conversation->getCreator()->getIdUser() === $p->getUser()->getIdUser()
            ];
        }

        return $this->json($data);
    }

    private function getLoggedInUser(Request $request): ?User
    {
        $session = $request->getSession();
        $userData = $session->get('user');
        if (!$userData) {
            return null;
        }
        $userId = is_array($userData)
            ? ($userData['id'] ?? $userData['id_user'] ?? null)
            : (method_exists($userData, 'getIdUser') ? $userData->getIdUser() : (method_exists($userData, 'getId') ? $userData->getId() : null));

        return $userId ? $this->userRepo->find($userId) : null;
    }

    /**
     * @param list<User> $users
     * @return array<int, mixed>
     */
    private function buildProfileMap(array $users): array
    {
        if ($users === []) {
            return [];
        }

        $profiles = $this->profileRepo->findBy(['user' => $users]);
        $map = [];
        foreach ($profiles as $profile) {
            $profileUser = $profile->getUser();
            if ($profileUser) {
                $map[$profileUser->getIdUser()] = $profile;
            }
        }

        return $map;
    }

    private function normalizePublicPath(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (preg_match('#^(https?:)?//#', $path) || str_starts_with($path, 'data:')) {
            return $path;
        }

        return '/' . ltrim($path, '/');
    }

    /**
     * @return list<UploadedFile>
     */
    private function flattenUploadedFiles(array $files): array
    {
        $flat = [];
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $flat[] = $file;
                continue;
            }
            if (is_array($file)) {
                array_push($flat, ...$this->flattenUploadedFiles($file));
            }
        }

        return $flat;
    }
}
