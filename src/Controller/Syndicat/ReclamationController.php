<?php

namespace App\Controller\Syndicat;

use App\Entity\Syndicat\Reclamation;
use App\Form\Syndicat\ReclamationType;
use App\Repository\Syndicat\ReclamationRepository;
use App\Repository\Syndicat\ReponseRepository;
use App\Form\Syndicat\ReponseType;
use App\Entity\Syndicat\Reponse;
use App\Service\PageStatusService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

use App\Service\FormErrorHelperTrait;

#[Route('/syndicat/reclamation')]
class ReclamationController extends AbstractController
{
    use FormErrorHelperTrait;
    #[Route('/', name: 'app_reclamation_index', methods: ['GET'])]
    public function index(ReclamationRepository $reclamationRepository): Response
    {
        return $this->render('syndicat/reclamation/index.html.twig', [
            'reclamations' => $reclamationRepository->findAll(),
        ]);
    }

    #[Route('/admin', name: 'admin_syndicat')]
    public function adminIndex(PageStatusService $pageStatusService, Request $request, ReclamationRepository $reclamationRepository, ReponseRepository $reponseRepository, FormFactoryInterface $formFactory): Response
    {
        $reclamations = $reclamationRepository->findBy([], ['created_at' => 'DESC']);
        $reponses = $reponseRepository->findAll();

        $recEditForm = $formFactory->createNamed('reclamation_edit', ReclamationType::class, new Reclamation());
        $repEditForm = $formFactory->createNamed('reponse_edit', ReponseType::class, new Reponse());

        return $this->render('admin/Syndicat/index.html.twig', [
            'reclamations' => $reclamations,
            'reponses' => $reponses,
            'recEditForm' => $recEditForm->createView(),
            'repEditForm' => $repEditForm->createView(),
        ]);
    }

    #[Route('/admin/syndicat/reclamation/{id}/delete', name: 'admin_syndicat_reclamation_delete', methods: ['POST'])]
    public function adminDeleteReclamation(int $id, Request $request, EntityManagerInterface $entityManager, ReclamationRepository $reclamationRepository): JsonResponse
    {
        $reclamation = $reclamationRepository->find($id);
        if (!$reclamation) {
            return new JsonResponse(['success' => false, 'message' => 'Reclamation not found.'], 404);
        }

        $entityManager->remove($reclamation);
        $entityManager->flush();

        return new JsonResponse(['success' => true, 'message' => 'Reclamation deleted successfully.']);
    }

    #[Route('/admin/reponse/{id}/delete', name: 'admin_reponse_delete', methods: ['POST'])]
    public function adminDeleteReponse(Request $request, ReponseRepository $reponseRepository, EntityManagerInterface $entityManager, int $id): JsonResponse
    {
        $reponse = $reponseRepository->find($id);
        if (!$reponse) {
            return $this->json(['success' => false, 'message' => 'Reponse not found'], 404);
        }

        $entityManager->remove($reponse);
        $entityManager->flush();

        return $this->json(['success' => true, 'message' => 'Response deleted successfully.']);
    }

    #[Route('/new', name: 'app_reclamation_new', methods: ['POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        \App\Service\Syndicat\SyndicatNotificationService $notificationService
    ): JsonResponse {
        $reclamation = new Reclamation();
        $form = $this->createForm(ReclamationType::class, $reclamation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userSession = $request->getSession()->get('user');
            if ($userSession) {
                $user = $entityManager->getRepository(\App\Entity\User\User::class)->find($userSession['id_user'] ?? $userSession['id']);
                if ($user) {
                    $reclamation->setUser($user);
                }
            }

            $imageFile = $form->get('imagereclamation')->getData();

            if ($imageFile) {
                // ... (existing image logic)
                if (is_array($imageFile)) {
                    $savedNames = [];
                    foreach ($imageFile as $file) {
                        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                        $safeFilename = $slugger->slug($originalFilename);
                        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
                        try {
                            $file->move($this->getParameter('reclamations_directory'), $newFilename);
                            $savedNames[] = $newFilename;
                        } catch (FileException $e) {
                        }
                    }
                    $reclamation->setImagereclamation(json_encode($savedNames));
                } else {
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                    try {
                        $imageFile->move($this->getParameter('reclamations_directory'), $newFilename);
                        $reclamation->setImagereclamation($newFilename);
                    } catch (FileException $e) {
                    }
                }
            }

            $reclamation->setCreatedAt(new \DateTime());
            $entityManager->persist($reclamation);
            $entityManager->flush();

            // Send Confirmation Email
            $notificationService->notifyReclamationConfirmation($reclamation);

            return new JsonResponse(['success' => true, 'message' => 'Reclamation submitted successfully.']);
        }

        return new JsonResponse(['success' => false, 'errors' => $this->getFormErrors($form)], 400);
    }

    #[Route('/{id}', name: 'app_reclamation_show', methods: ['GET'])]
    public function show(Reclamation $reclamation): Response
    {
        return $this->render('syndicat/reclamation/show.html.twig', [
            'reclamation' => $reclamation,
        ]);
    }

    #[Route('/admin/reclamation/{id}/edit', name: 'admin_reclamation_edit_ajax', methods: ['POST'])]
    public function adminEdit(int $id, Request $request, ReclamationRepository $reclamationRepository, EntityManagerInterface $em, SluggerInterface $slugger, FormFactoryInterface $formFactory): JsonResponse
    {
        $reclamation = $reclamationRepository->find($id);
        if (!$reclamation) {
            return new JsonResponse(['success' => false, 'message' => 'Reclamation not found.'], 404);
        }

        $form = $formFactory->createNamed('reclamation_edit', ReclamationType::class, $reclamation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imagereclamation')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $imageFile->move($this->getParameter('reclamations_directory'), $newFilename);
                    $reclamation->setImagereclamation($newFilename);
                } catch (FileException $e) {
                }
            }
            $em->flush();
            return new JsonResponse(['success' => true, 'message' => 'Reclamation updated successfully.']);
        }

        return new JsonResponse(['success' => false, 'errors' => $this->getFormErrors($form)], 400);
    }

    #[Route('/{id}', name: 'app_reclamation_delete', methods: ['POST'])]
    public function delete(Request $request, Reclamation $reclamation, EntityManagerInterface $entityManager): JsonResponse
    {
        if ($this->isCsrfTokenValid('delete' . $reclamation->getId(), $request->request->get('_token'))) {
            $entityManager->remove($reclamation);
            $entityManager->flush();
            return new JsonResponse(['success' => true, 'message' => 'Reclamation deleted successfully.']);
        }

        return new JsonResponse(['success' => false, 'message' => 'Invalid security token.'], 403);
    }
}

