<?php

namespace App\Controller\Evenement;

use App\Entity\Evenement\Evenement;
use App\Form\Evenement\EvenementType;
use App\Repository\Evenement\EvenementRepository;
use App\Entity\Evenement\Participation;
use App\Form\Evenement\ParticipationType;
use App\Repository\Evenement\ParticipationRepository;
use App\Service\PageStatusService;
use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/evenement')]
class EvenementController extends AbstractController
{
    #[Route('/', name: 'app_evenement_index', methods: ['GET', 'POST'])]
    public function index(Request $request, EvenementRepository $evenementRepository, \App\Repository\User\UserRepository $userRepository, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $user = $this->getUser();
                if (!$user) {
                    $sessionUser = $request->getSession()->get('user');
                    if ($sessionUser) {
                        $userId = is_array($sessionUser) ? ($sessionUser['id_user'] ?? $sessionUser['id'] ?? null) : null;
                        if ($userId) {
                            $user = $userRepository->find($userId);
                        }
                    }
                }

                if ($user) {
                    $evenement->setUser($user);
                }

                $imageFile = $form->get('image_event')->getData();
                if ($imageFile) {
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                    try {
                        $imageFile->move($this->getParameter('event_images_directory'), $newFilename);
                        $evenement->setImageEvent($newFilename);
                    } catch (\Exception $e) {
                    }
                }

                $entityManager->persist($evenement);
                $entityManager->flush();

                return new JsonResponse(['success' => true, 'message' => 'Event created successfully!']);
            }

            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(' ', $errors)], 400);
        }

        $participatedEventIds = [];
        $userForCheck = $this->getUser();
        if (!$userForCheck) {
            $sessionUser = $request->getSession()->get('user');
            if ($sessionUser) {
                $userId = is_array($sessionUser) ? ($sessionUser['id'] ?? $sessionUser['id_user'] ?? null) : null;
                if ($userId)
                    $userForCheck = $userRepository->find($userId);
            }
        }

        if ($userForCheck) {
            $participatedEventIds = $entityManager->createQueryBuilder()
                ->select('e.id')
                ->from(Participation::class, 'p')
                ->join('p.evenement', 'e')
                ->where('p.user = :user')
                ->andWhere('p.statut_participation != :status')
                ->setParameter('user', $userForCheck)
                ->setParameter('status', 'annule')
                ->getQuery()
                ->getSingleColumnResult();
        }

        /** @var User|null $user */
        $user = $userForCheck;
        $currentUserId = ($user instanceof User) ? $user->getIdUser() : null;
        $currentUserRole = ($user instanceof User) ? $user->getRoleUser() : null;

        return $this->render('frontend/evenement/index.html.twig', [
            'evenements' => $evenementRepository->findAllWithUser(),
            'form' => $form->createView(),
            'participationForm' => $this->createForm(ParticipationType::class)->createView(),
            'participatedEventIds' => $participatedEventIds,
            'currentUserId' => $currentUserId,
            'currentUserRole' => $currentUserRole,
        ]);
    }

    #[Route('/admin', name: 'admin_evenement')]
    public function adminIndex(PageStatusService $pageStatusService, Request $request, EvenementRepository $evenementRepository, ParticipationRepository $participationRepository): Response
    {
        $evenements = $evenementRepository->findBy([], ['date_event' => 'DESC']);
        $participations = $participationRepository->findBy([], ['date_participation' => 'DESC']);

        $evenementAddForm = $this->createForm(EvenementType::class, new Evenement());
        $evenementEditForm = $this->createForm(EvenementType::class, new Evenement());
        $participationEditForm = $this->createForm(ParticipationType::class, new Participation(), ['is_admin' => true]);

        return $this->render('admin/Evenement/index.html.twig', [
            'evenements' => $evenements,
            'participations' => $participations,
            'evenementAddForm' => $evenementAddForm->createView(),
            'evenementEditForm' => $evenementEditForm->createView(),
            'participationEditForm' => $participationEditForm->createView(),
        ]);
    }

    #[Route('/admin/add', name: 'admin_evenement_add', methods: ['POST'])]
    public function adminNewEvenement(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, \App\Repository\User\UserRepository $userRepository): JsonResponse
    {
        $evenement = new Evenement();
        $user = $this->getUser();
        if (!$user) {
            $sessionUser = $request->getSession()->get('user');
            if ($sessionUser) {
                $userId = is_array($sessionUser) ? ($sessionUser['id_user'] ?? $sessionUser['id'] ?? null) : null;
                if ($userId) {
                    $user = $userRepository->find($userId);
                }
            }
        }

        if ($user) {
            $evenement->setUser($user);
        }

        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image_event')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $imageFile->move($this->getParameter('event_images_directory'), $newFilename);
                    $evenement->setImageEvent($newFilename);
                } catch (\Exception $e) {
                }
            }

            $entityManager->persist($evenement);
            $entityManager->flush();

            return new JsonResponse(['success' => true, 'message' => 'Event created successfully!']);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }
        return new JsonResponse(['success' => false, 'message' => implode(' ', $errors)], 400);
    }

    #[Route('/admin/{id}/edit', name: 'admin_evenement_edit', methods: ['POST'])]
    public function adminEditEvenement(int $id, Request $request, EvenementRepository $evenementRepository, EntityManagerInterface $em): JsonResponse
    {
        $evenement = $evenementRepository->find($id);
        if (!$evenement) {
            return new JsonResponse(['success' => false, 'message' => 'Event not found.'], 404);
        }

        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return new JsonResponse(['success' => true, 'message' => 'Event updated successfully.']);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        return new JsonResponse(['success' => false, 'message' => implode(', ', $errors)], 400);
    }

    #[Route('/admin/{id}/delete', name: 'admin_evenement_delete', methods: ['POST'])]
    public function adminDeleteEvenement(int $id, Request $request, EvenementRepository $evenementRepository, ParticipationRepository $participationRepository, EntityManagerInterface $em, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        try {
            $evenement = $evenementRepository->find($id);
            if (!$evenement) {
                return new JsonResponse(['success' => false, 'message' => 'Event not found.'], 404);
            }

            $token = $request->request->get('_token');
            if (!$token || !$csrfTokenManager->isTokenValid(new CsrfToken('evenement_delete', $token))) {
                return new JsonResponse(['success' => false, 'message' => 'Invalid security token.'], 403);
            }

            $participations = $participationRepository->findBy(['evenement' => $evenement]);
            foreach ($participations as $participation) {
                $em->remove($participation);
            }

            $em->remove($evenement);
            $em->flush();

            return new JsonResponse(['success' => true, 'message' => 'Event and its participations deleted successfully.']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'An error occurred while deleting the event: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/admin/participation/{id}/edit', name: 'admin_participation_edit', methods: ['POST'])]
    public function adminEditParticipation(int $id, Request $request, ParticipationRepository $participationRepository, EntityManagerInterface $em): JsonResponse
    {
        $participation = $participationRepository->find($id);
        if (!$participation) {
            return new JsonResponse(['success' => false, 'message' => 'Participation not found.'], 404);
        }

        $oldStatus = $participation->getStatutParticipation();
        $oldGuests = $participation->getNbAccompagnants();

        $form = $this->createForm(ParticipationType::class, $participation, ['is_admin' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newStatus = $participation->getStatutParticipation();
            $newGuests = $participation->getNbAccompagnants();
            $evenement = $participation->getEvenement();

            if ($evenement) {
                $placesToDeduct = 0;
                if ($oldStatus !== 'confirme' && $newStatus === 'confirme') {
                    $placesToDeduct = 1 + $newGuests;
                } elseif ($oldStatus === 'confirme' && $newStatus !== 'confirme') {
                    $placesToDeduct = -(1 + $oldGuests);
                } elseif ($oldStatus === 'confirme' && $newStatus === 'confirme' && $oldGuests !== $newGuests) {
                    $placesToDeduct = $newGuests - $oldGuests;
                }

                if ($placesToDeduct !== 0) {
                    if ($placesToDeduct > $evenement->getNbRestants()) {
                        return new JsonResponse([
                            'success' => false,
                            'message' => "Not enough seats available. Remaining: {$evenement->getNbRestants()}, Required additional: {$placesToDeduct}."
                        ], 400);
                    }
                    $evenement->setNbRestants($evenement->getNbRestants() - $placesToDeduct);
                }
            }

            $em->flush();
            return new JsonResponse(['success' => true, 'message' => 'Participation updated successfully.']);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        return new JsonResponse(['success' => false, 'message' => implode(', ', $errors)], 400);
    }

    #[Route('/admin/participation/{id}/delete', name: 'admin_participation_delete', methods: ['POST'])]
    public function adminDeleteParticipation(int $id, Request $request, ParticipationRepository $participationRepository, EntityManagerInterface $em, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        $participation = $participationRepository->find($id);
        if (!$participation) {
            return new JsonResponse(['success' => false, 'message' => 'Participation not found.'], 404);
        }

        $token = $request->request->get('_token');
        if ($token && !$csrfTokenManager->isTokenValid(new CsrfToken('participation_delete', $token))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid security token.'], 403);
        }

        $evenement = $participation->getEvenement();
        if ($evenement && $participation->getStatutParticipation() === 'confirme') {
            $placesToReclaim = 1 + $participation->getNbAccompagnants();
            $evenement->setNbRestants($evenement->getNbRestants() + $placesToReclaim);
        }

        $em->remove($participation);
        $em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Participation deleted successfully.']);
    }

    #[Route('/new', name: 'app_evenement_new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): JsonResponse
    {
        $evenement = new Evenement();
        $user = $this->getUser();
        if ($user) {
            $evenement->setUser($user);
        }

        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('image_event')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move($this->getParameter('event_images_directory'), $newFilename);
                    $evenement->setImageEvent($newFilename);
                } catch (\Exception $e) {
                }
            }

            $entityManager->persist($evenement);
            $entityManager->flush();

            return new JsonResponse(['success' => true, 'message' => 'Event created successfully!']);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $fieldName = $error->getOrigin()->getName();
            $errors[$fieldName] = $error->getMessage();
        }
        return new JsonResponse(['success' => false, 'errors' => $errors], 400);
    }

    #[Route('/{id}', name: 'app_evenement_show', methods: ['GET'])]
    public function show(Evenement $evenement): Response
    {
        return $this->redirectToRoute('app_evenement_index');
    }

    #[Route('/{id}/edit', name: 'app_evenement_edit', methods: ['POST'])]
    public function edit(Request $request, Evenement $evenement, EntityManagerInterface $entityManager, SluggerInterface $slugger): JsonResponse
    {
        $form = $this->createForm(EvenementType::class, $evenement, [
            'is_edit' => true
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('image_event')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move($this->getParameter('event_images_directory'), $newFilename);
                    $evenement->setImageEvent($newFilename);
                } catch (\Exception $e) {
                }
            }

            $entityManager->flush();

            return new JsonResponse(['success' => true, 'message' => 'Event updated successfully!']);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $fieldName = $error->getOrigin()->getName();
            $errors[$fieldName] = $error->getMessage();
        }
        return new JsonResponse(['success' => false, 'errors' => $errors], 400);
    }

    #[Route('/{id}', name: 'app_evenement_delete', methods: ['POST'])]
    public function delete(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): JsonResponse
    {
        if ($this->isCsrfTokenValid('delete' . $evenement->getId(), $request->request->get('_token'))) {
            $entityManager->remove($evenement);
            $entityManager->flush();
            return new JsonResponse(['success' => true, 'message' => 'Event deleted successfully!']);
        }

        return new JsonResponse(['success' => false, 'message' => 'Invalid security token.'], 403);
    }
}

