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
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class SyndicatController extends AbstractController
{
    #[Route('/syndicat', name: 'frontend_syndicat', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        \App\Repository\User\UserRepository $userRepository,
        \App\Service\Syndicat\SyndicatNotificationService $notificationService
    ): Response {
        $reclamation = new Reclamation();
        $form = $this->createForm(ReclamationType::class, $reclamation);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            error_log("--- Reclamation Submission Attempt ---");
            $session = $request->getSession();
            $userSession = $session->get('user');

            if (!$form->isValid()) {
                if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                    $errors = [];
                    foreach ($form->getErrors(true) as $error) {
                        $fieldName = $error->getOrigin()->getName();
                        $errors[$fieldName] = $error->getMessage();
                    }
                    return new JsonResponse(['success' => false, 'errors' => $errors], 400);
                }
                $errorsStr = (string) $form->getErrors(true, false);
                error_log("Form Validation Failed: " . $errorsStr);
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
                            $imageFile->move($targetDirectory, $newFilename);
                            $savedImagePaths[] = $folderName . '/' . $newFilename;
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
        \App\Service\Syndicat\SyndicatNotificationService $notificationService
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

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imagereponse')->getData();
            if ($imageFile) {
                $newFilename = bin2hex(random_bytes(6)) . '.' . $imageFile->guessExtension();
                try {
                    $targetDir = $this->getParameter('reclamations_directory');
                    if (!is_dir($targetDir))
                        mkdir($targetDir, 0777, true);
                    $imageFile->move($targetDir, $newFilename);
                    $reponse->setImagereponse($newFilename);
                } catch (\Exception $e) {
                }
            }

            $entityManager->persist($reponse);
            $entityManager->flush();

            // Send Reply Notification
            $notificationService->notifyReclamationReply($reponse);

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => true, 'message' => 'Response added!']);
            }
            return $this->redirectToRoute('frontend_reclamation_details', ['id' => $reclamation->getId()]);
        }

        if ($request->isXmlHttpRequest() && $request->getMethod() === 'POST' && !$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(' ', $errors)], 400);
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
