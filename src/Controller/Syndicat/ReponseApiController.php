<?php

namespace App\Controller\Syndicat;

use App\Entity\Syndicat\Reponse;
use App\Repository\Syndicat\ReclamationRepository;
use App\Repository\Syndicat\ReponseRepository;
use App\Repository\User\UserRepository;
use App\Service\Media\ImageKitStorageService;
use App\Service\Media\ImagePathResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/syndicat/reponse')]
class ReponseApiController extends AbstractController
{
    #[Route('/add/{id}', name: 'api_reponse_add', methods: ['POST'])]
    public function add(
        int $id,
        Request $request,
        \App\Repository\Syndicat\ReclamationRepository $reclamationRepository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        \App\Service\Syndicat\SyndicatNotificationService $notificationService,
        \App\Service\Log\UserActivityLogger $activityLogger,
        ImageKitStorageService $imageStorage
    ): JsonResponse {
        $reclamation = $reclamationRepository->find($id);
        $userSession = $request->getSession()->get('user');

        if (!$reclamation || !$userSession) {
            return new JsonResponse(['success' => false, 'message' => 'Reclamation not found or not logged in'], 404);
        }

        $user = $entityManager->getRepository(\App\Entity\User\User::class)->find($userSession['id_user'] ?? $userSession['id']);
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'User not found'], 404);
        }

        $message = $request->request->get('message');
        if (!$message) {
            return new JsonResponse(['success' => false, 'message' => 'Message is required'], 400);
        }

        $reponse = new Reponse();
        $reponse->setReclamation($reclamation);
        $reponse->setUser($user);
        $reponse->setMessagereponse($message);
        $reponse->setTitrereponse($request->request->get('title'));

        // Handle Image Upload
        $imageFile = $request->files->get('image'); // Single image from profile form
        if ($imageFile) {
            $now = new \DateTime();
            $fullName = trim($user->getFirstName() . ' ' . $user->getLastName());
            $folderName = $slugger->slug($fullName) . '_' . $now->format('ymdHis');
            $targetDirectory = $this->getParameter('reponse_images_directory') . '/' . $folderName;

            $newFilename = bin2hex(random_bytes(4)) . '-' . uniqid() . '.' . $imageFile->guessExtension();
            try {
                if (!is_dir($targetDirectory)) {
                    mkdir($targetDirectory, 0777, true);
                }
                $reponse->setImagereponse(json_encode([
                    $imageStorage->storeUploadedFile(
                        $imageFile,
                        $this->getParameter('reponse_images_directory'),
                        'reponse_images',
                        '/syndicati/reponse_images',
                        $folderName . '/' . $newFilename
                    )
                ]));
            } catch (\Exception $e) {
                error_log("API Reponse Add Upload Error: " . $e->getMessage());
            }
        }

        $entityManager->persist($reponse);
        $entityManager->flush();

        // Send Reply Notification
        $notificationService->notifyReclamationReply($reponse);
        $activityLogger->log('RECLAMATION_REPLY_CREATED', 'REPONSE', $reponse->getId(), [
            'category' => 'SYNDICAT',
            'action' => 'CREATE_REPONSE',
            'outcome' => 'SUCCESS',
            'message' => 'Response added to a reclamation.',
            'reclamation_id' => $reclamation->getId(),
        ], $user);

        return new JsonResponse(['success' => true, 'message' => 'Response added successfully.']);
    }

    #[Route('/list/{id}', name: 'api_reponse_list', methods: ['GET'])]
    public function list(
        int $id,
        ReclamationRepository $reclamationRepository,
        ReponseRepository $reponseRepository,
        Request $request,
        EntityManagerInterface $entityManager,
        ImagePathResolver $imagePathResolver
    ): JsonResponse {
        $reclamation = $reclamationRepository->find($id);
        if (!$reclamation) {
            return new JsonResponse(['success' => false, 'message' => 'Reclamation not found'], 404);
        }

        $userSession = $request->getSession()->get('user');
        $currentUserId = $userSession ? ($userSession['id_user'] ?? $userSession['id']) : null;

        $reponses = $reponseRepository->findBy(['reclamation' => $reclamation], ['created_at' => 'ASC']);
        $data = [];

        // Batch fetch profiles to get avatars
        $userIds = array_unique(array_map(fn($r) => $r->getUser()->getIdUser(), $reponses));
        $profiles = [];
        if (!empty($userIds)) {
            $profileEntities = $entityManager->getRepository(\App\Entity\Profile\Profile::class)
                ->createQueryBuilder('p')
                ->where('p.user IN (:ids)')
                ->setParameter('ids', $userIds)
                ->getQuery()
                ->getResult();
            foreach ($profileEntities as $p) {
                $profiles[$p->getUser()->getIdUser()] = $p;
            }
        }

        foreach ($reponses as $r) {
            $user = $r->getUser();
            $profile = $profiles[$user->getIdUser()] ?? null;

            // Avatar logic similar to CommentaireController
            $avatar = 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=200&h=200&fit=crop&crop=face';
            $avatarVal = $profile ? $profile->getAvatar() : null;
            if ($avatarVal) {
                if (strpos($avatarVal, 'http') === 0) {
                    $avatar = $avatarVal;
                } elseif (strpos($avatarVal, 'profile_images/') !== false) {
                    $avatar = '/' . ltrim($avatarVal, '/');
                } else {
                    $avatar = '/profile_images/' . $avatarVal;
                }
            }

            $images = json_decode($r->getImagereponse() ?? '[]', true) ?: [];
            $imageUrls = $imagePathResolver->publicUrls($images, 'reponse_images');

            $data[] = [
                'id' => $r->getId(),
                'title' => $r->getTitrereponse(),
                'message' => $r->getMessagereponse(),
                'images' => $imageUrls,
                'createdAt' => $r->getCreatedAt() ? $r->getCreatedAt()->format('d M Y, H:i') : null,
                'isMe' => $currentUserId && $user->getIdUser() == $currentUserId,
                'author' => [
                    'name' => $user->getFirstName() . ' ' . $user->getLastName(),
                    'avatar' => $avatar
                ]
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/update/{id}', name: 'api_reponse_update', methods: ['POST'])]
    public function update(
        int $id,
        Request $request,
        ReponseRepository $reponseRepository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        \App\Service\Log\UserActivityLogger $activityLogger,
        ImageKitStorageService $imageStorage
    ): JsonResponse {
        $reponse = $reponseRepository->find($id);
        $userSession = $request->getSession()->get('user');

        if (!$reponse || !$userSession) {
            return new JsonResponse(['success' => false, 'message' => 'Not found or not logged in'], 404);
        }

        // Project pattern for single user entity retrieval
        $user = $entityManager->getRepository(\App\Entity\User\User::class)->find($userSession['id_user'] ?? $userSession['id']);

        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'User not found'], 404);
        }

        // Check Admin Roles
        $isAdmin = in_array($user->getRoleUser(), ['OWNER', 'ADMIN', 'SUPERADMIN', 'SYNDIC']);
        if (!$isAdmin) {
            return new JsonResponse(['success' => false, 'message' => 'Access denied'], 403);
        }

        $title = $request->request->get('title');
        $message = $request->request->get('message');

        if ($title) {
            $reponse->setTitrereponse($title);
        }
        if ($message) {
            $reponse->setMessagereponse($message);
        }

        // Handle Image Upload
        $imageFiles = $request->files->get('images'); // Multiple images
        if ($imageFiles) {
            $now = new \DateTime();
            $fullName = trim($user->getFirstName() . ' ' . $user->getLastName());
            $folderName = $slugger->slug($fullName) . '_' . $now->format('ymdHis');
            $targetDirectory = $this->getParameter('reponse_images_directory') . '/' . $folderName;

            // Existing images? 
            $existingImages = json_decode($reponse->getImagereponse() ?? '[]', true) ?: [];
            $savedImagePaths = $existingImages;

            foreach ($imageFiles as $imageFile) {
                if ($imageFile) {
                    $newFilename = bin2hex(random_bytes(4)) . '-' . uniqid() . '.' . $imageFile->guessExtension();
                    try {
                        if (!is_dir($targetDirectory)) {
                            mkdir($targetDirectory, 0777, true);
                        }
                        $savedImagePaths[] = $imageStorage->storeUploadedFile(
                            $imageFile,
                            $this->getParameter('reponse_images_directory'),
                            'reponse_images',
                            '/syndicati/reponse_images',
                            $folderName . '/' . $newFilename
                        );
                    } catch (\Exception $e) {
                        error_log("API Reponse Upload Error: " . $e->getMessage());
                    }
                }
            }
            $reponse->setImagereponse(json_encode($savedImagePaths));
        }

        $entityManager->flush();
        $activityLogger->log('RECLAMATION_REPLY_UPDATED', 'REPONSE', $reponse->getId(), [
            'category' => 'SYNDICAT',
            'action' => 'UPDATE_REPONSE',
            'outcome' => 'SUCCESS',
            'message' => 'Response updated from the Syndicat API.',
            'reclamation_id' => $reponse->getReclamation()?->getId(),
        ], $user);

        return new JsonResponse(['success' => true, 'message' => 'Response updated successfully.']);
    }
}
