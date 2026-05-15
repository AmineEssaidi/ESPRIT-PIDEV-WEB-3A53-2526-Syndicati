<?php
namespace App\Controller\Syndicat;

use App\Entity\Syndicat\Reclamation;
use App\Form\Syndicat\ReclamationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Sensiolabs\GotenbergBundle\GotenbergPdfInterface;
use App\Entity\Syndicat\Reponse;
use App\Form\Syndicat\ReponseType;
use App\Service\Media\ImageKitStorageService;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

use App\Service\FormErrorHelperTrait;

class SyndicatController extends AbstractController
{
    use FormErrorHelperTrait;
    #[Route('/syndicat', name: 'frontend_syndicat', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        \App\Repository\User\UserRepository $userRepository,
        \App\Service\Syndicat\SyndicatNotificationService $notificationService,
        \App\Service\Log\UserActivityLogger $activityLogger,
        ImageKitStorageService $imageStorage
    ): Response {
        $reclamation = new Reclamation();
        $form = $this->createForm(ReclamationType::class, $reclamation);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            error_log("--- Reclamation Submission Attempt ---");
            $session = $request->getSession();
            $userSession = $session->get('user');

            if (!$form->isValid()) {
                return new JsonResponse(['success' => false, 'errors' => $this->getFormErrors($form)], 400);
            }

            if (!$userSession) {
                error_log("Submission Failed: No user session found.");
                if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                    return new JsonResponse(['success' => false, 'message' => 'Not logged in. Please sign in to submit a reclamation.'], 401);
                }
                return $this->redirectToRoute('auth_sign_in');
            }

            // Project pattern for user ID from session
            $userId = $userSession['id'] ?? $userSession['id_user'] ?? null;
            error_log("User ID from session: " . ($userId ?? 'NULL'));

            $user = $userId ? $userRepository->find((int) $userId) : null;

            if (!$user) {
                error_log("Submission Failed: User entity not found for ID $userId");
                if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                    return new JsonResponse(['success' => false, 'message' => 'User account not found.'], 404);
                }
                return $this->redirectToRoute('auth_sign_in');
            }
            $reclamation->setUser($user);

            $now = new \DateTime();
            if (!$reclamation->getDatereclamation()) {
                $reclamation->setDatereclamation($now);
            }

            if (!$reclamation->getStatutreclamation()) {
                $reclamation->setStatutreclamation('en_attente');
            }

            $imageFiles = $form->get('imagereclamation')->getData();

            if ($imageFiles) {
                $fullName = trim($user->getFirstName() . ' ' . $user->getLastName());
                $folderName = $slugger->slug($fullName) . '_' . $now->format('ymdHis');
                $targetDirectory = $this->getParameter('reclamations_directory') . '/' . $folderName;
                $savedImagePaths = [];

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
                            error_log("File upload error: " . $e->getMessage());
                            if ($request->isXmlHttpRequest()) {
                                return new JsonResponse(['success' => false, 'message' => 'Image upload failed.'], 500);
                            }
                        }
                    }
                }
                // Save as JSON array of paths: ["Folder/Image1.jpg", "Folder/Image2.jpg"]
                $reclamation->setImagereclamation(json_encode($savedImagePaths));
            }

            try {
                $entityManager->persist($reclamation);
                $entityManager->flush();
                error_log("Reclamation successfully persisted. ID: " . $reclamation->getId());

                // Send Confirmation Email
                $notificationService->notifyReclamationConfirmation($reclamation);
                $notificationService->notifyReclamationBroadcastToStaff($reclamation);
                $activityLogger->log('RECLAMATION_CREATED', 'RECLAMATION', $reclamation->getId(), [
                    'category' => 'SYNDICAT',
                    'action' => 'CREATE_RECLAMATION',
                    'outcome' => 'SUCCESS',
                    'message' => 'Reclamation submitted from the Syndicat portal.',
                    'status' => $reclamation->getStatutreclamation(),
                ], $user);
            } catch (\Exception $e) {
                error_log("Reclamation Persistence Failed: " . $e->getMessage());
                if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                    return new JsonResponse(['success' => false, 'message' => 'We could not save your reclamation. Please try again.'], 500);
                }
                $this->addFlash('error', 'An error occurred while saving: ' . $e->getMessage());
                return $this->render('frontend/syndicat/index.html.twig', [
                    'form' => $form->createView()
                ]);
            }

            if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                return new JsonResponse(['success' => true, 'message' => 'Your reclamation has been sent successfully.']);
            }

            $this->addFlash('success', 'Your reclamation has been submitted successfully.');
            return $this->redirectToRoute('frontend_syndicat');
        }

        return $this->render('frontend/syndicat/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/syndicat/{id}/details', name: 'frontend_reclamation_details', methods: ['GET', 'POST'])]
    public function details(
        Request $request,
        Reclamation $reclamation,
        EntityManagerInterface $entityManager,
        \App\Repository\User\UserRepository $userRepository,
        SluggerInterface $slugger,
        \App\Service\Syndicat\SyndicatNotificationService $notificationService,
        \App\Service\Log\UserActivityLogger $activityLogger,
        ImageKitStorageService $imageStorage
    ): Response {
        $session = $request->getSession();
        $userSession = $session->get('user');

        if (!$userSession) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'Session expired. Please log in again.'], 401);
            }
            return $this->redirectToRoute('auth_sign_in');
        }

        $userId = $userSession['id'] ?? $userSession['id_user'];
        $user = $userRepository->find($userId);

        $reponse = new Reponse();
        $reponse->setReclamation($reclamation);
        $reponse->setUser($user);
        $reponse->setCreatedAt(new \DateTime());

        $form = $this->createForm(ReponseType::class, $reponse);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $imageFile = $form->get('imagereponse')->getData();
                if ($imageFile) {
                    $newFilename = bin2hex(random_bytes(6)) . '.' . $imageFile->guessExtension();
                    try {
                        $targetDir = $this->getParameter('reponse_images_directory');
                        if (!is_dir($targetDir))
                            mkdir($targetDir, 0777, true);
                        $reponse->setImagereponse(json_encode([
                            $imageStorage->storeUploadedFile(
                                $imageFile,
                                $targetDir,
                                'reponse_images',
                                '/syndicati/reponse_images',
                                $newFilename
                            )
                        ]));
                    } catch (\Exception $e) {
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

                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse(['success' => true, 'message' => 'Response added!']);
                }
                return $this->redirectToRoute('frontend_reclamation_details', ['id' => $reclamation->getId()]);
            } else {
                return new JsonResponse(['success' => false, 'errors' => $this->getFormErrors($form)], 400);
            }
        }

        // Image processing for the modal view
        $imgStr = $reclamation->getImagereclamation();
        $images = [];
        if ($imgStr) {
            $jsonDecoded = json_decode($imgStr, true);
            if (is_array($jsonDecoded)) {
                $images = $jsonDecoded;
            }
        }

        // Check Admin Roles
        $isAdmin = in_array($user->getRoleUser(), ['OWNER', 'ADMIN', 'SUPERADMIN', 'SYNDIC']);

        return $this->render('frontend/syndicat/show_modal.html.twig', [
            'reclamation' => $reclamation,
            'images' => $images,
            'form' => $form->createView(),
            'isAdmin' => $isAdmin,
        ]);
    }

    #[Route('/syndicat/{id}/pdf', name: 'frontend_reclamation_pdf')]
    public function pdf(Reclamation $reclamation, GotenbergPdfInterface $gotenbergPdf): Response
    {
        $images = [];
        $imgStr = $reclamation->getImagereclamation();
        if (is_string($imgStr) && !empty($imgStr)) {
            $jsonDecoded = json_decode($imgStr, true);
            if (is_array($jsonDecoded)) {
                $images = $jsonDecoded;
            } elseif (!str_contains($imgStr, '[')) {
                $images = [$imgStr];
            }
        }

        $response = $gotenbergPdf->html()
            ->printBackground()
            ->content('frontend/syndicat/pdf.html.twig', [
                'reclamation' => $reclamation,
                'images' => $images,
            ])->generate()->stream();

        // Force download
        $response->headers->set('Content-Disposition', "attachment; filename=\"reclamation_{$reclamation->getId()}.pdf\"");

        return $response;
    }
}
