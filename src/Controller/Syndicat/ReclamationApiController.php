<?php

namespace App\Controller\Syndicat;

use App\Entity\Syndicat\Reclamation;
use App\Repository\Syndicat\ReclamationRepository;
use App\Service\Media\ImageKitStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

use App\Service\FormErrorHelperTrait;

#[Route('/api/syndicat/reclamation')]
class ReclamationApiController extends AbstractController
{
    use FormErrorHelperTrait;
    #[Route('/update/{id}', name: 'api_reclamation_update', methods: ['POST'])]
    public function update(
        int $id,
        Request $request,
        ReclamationRepository $reclamationRepository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        \App\Service\Syndicat\SyndicatNotificationService $notificationService,
        \App\Service\Log\UserActivityLogger $activityLogger,
        ImageKitStorageService $imageStorage
    ): JsonResponse {
        $reclamation = $reclamationRepository->find($id);
        $userSession = $request->getSession()->get('user');

        if (!$reclamation || !$userSession) {
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
        $description = $request->request->get('description');
        $status = $request->request->get('status');
        $previousStatus = $reclamation->getStatutreclamation();

        if ($title)
            $reclamation->setTitrereclamations($title);
        if ($description)
            $reclamation->setDescreclamation($description);
        if ($status && in_array($status, Reclamation::STATUTS)) {
            $reclamation->setStatutreclamation($status);
        }

        // Handle Image Upload
        $imageFiles = $request->files->get('images'); // Multiple images
        if ($imageFiles) {
            $now = new \DateTime();
            $fullName = trim($user->getFirstName() . ' ' . $user->getLastName());
            $folderName = $slugger->slug($fullName) . '_' . $now->format('ymdHis');
            $targetDirectory = $this->getParameter('reclamations_directory') . '/' . $folderName;
            $savedImagePaths = [];

            // Existing images? 
            $existingImages = json_decode($reclamation->getImagereclamation() ?? '[]', true) ?: [];
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
                            $this->getParameter('reclamations_directory'),
                            'reclamation_images',
                            '/syndicati/reclamation_images',
                            $folderName . '/' . $newFilename
                        );
                    } catch (\Exception $e) {
                        error_log("API Reclamation Upload Error: " . $e->getMessage());
                    }
                }
            }
            $reclamation->setImagereclamation(json_encode($savedImagePaths));
        }

        $entityManager->flush();
        if ($previousStatus !== $reclamation->getStatutreclamation()) {
            $notificationService->notifyReclamationStatusChanged($reclamation, $user, $previousStatus);
        }
        $activityLogger->log('RECLAMATION_UPDATED', 'RECLAMATION', $reclamation->getId(), [
            'category' => 'SYNDICAT',
            'action' => 'UPDATE_RECLAMATION',
            'outcome' => 'SUCCESS',
            'message' => 'Reclamation updated from the Syndicat API.',
            'previous_status' => $previousStatus,
            'status' => $reclamation->getStatutreclamation(),
        ], $user);

        return new JsonResponse(['success' => true, 'message' => 'Reclamation updated successfully.']);
    }

    #[Route('/delete-image/{id}', name: 'api_reclamation_delete_image', methods: ['POST'])]
    public function deleteImage(
        int $id,
        Request $request,
        ReclamationRepository $reclamationRepository,
        EntityManagerInterface $entityManager,
        \App\Service\Log\UserActivityLogger $activityLogger
    ): JsonResponse {
        $reclamation = $reclamationRepository->find($id);
        $userSession = $request->getSession()->get('user');

        if (!$reclamation || !$userSession) {
            return new JsonResponse(['success' => false, 'message' => 'Not found or not logged in'], 404);
        }

        $user = $entityManager->getRepository(\App\Entity\User\User::class)->find($userSession['id_user'] ?? $userSession['id']);
        $isAdmin = in_array($user->getRoleUser(), ['OWNER', 'ADMIN', 'SUPERADMIN', 'SYNDIC']);
        if (!$isAdmin) {
            return new JsonResponse(['success' => false, 'message' => 'Access denied'], 403);
        }

        $imagePath = $request->request->get('imagePath');
        if (!$imagePath) {
            return new JsonResponse(['success' => false, 'message' => 'Missing image path'], 400);
        }

        $images = json_decode($reclamation->getImagereclamation() ?? '[]', true) ?: [];
        $key = array_search($imagePath, $images);

        if ($key !== false) {
            unset($images[$key]);
            $reclamation->setImagereclamation(json_encode(array_values($images)));
            $entityManager->flush();
            $activityLogger->log('RECLAMATION_ATTACHMENT_DELETED', 'RECLAMATION', $reclamation->getId(), [
                'category' => 'SYNDICAT',
                'action' => 'DELETE_RECLAMATION_ATTACHMENT',
                'outcome' => 'SUCCESS',
                'message' => 'A reclamation attachment was removed.',
                'image_path' => $imagePath,
            ], $user);
            return new JsonResponse(['success' => true, 'message' => 'Image deleted successfully.']);
        }

        return new JsonResponse(['success' => false, 'message' => 'Image not found in reclamation'], 404);
    }
}
