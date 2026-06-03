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
use App\Service\WeatherService;
use App\Service\UserStanding\UserStandingService;
use App\Service\Media\ImageKitStorageService;
use App\Service\Media\ImagePathResolver;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use App\Service\Evenement\EventRecommendationService;
use App\Service\Evenement\EvenementNotificationService;
use App\Message\Evenement\NotifyEventCreationMessage;
use Symfony\Component\Messenger\MessageBusInterface;

use App\Service\FormErrorHelperTrait;

#[Route('/evenement')]
class EvenementController extends AbstractController
{
    use FormErrorHelperTrait;
    public function __construct(
        private readonly UserStandingService $userStandingService,
        private readonly \App\Service\User\NotificationService $notifService,
        private readonly ImageKitStorageService $imageStorage,
        private readonly ImagePathResolver $imagePathResolver
    ) {
    }
    #[Route('/weather/preview', name: 'app_evenement_weather_preview', methods: ['GET'])]
    public function weatherPreview(Request $request, WeatherService $weatherService): JsonResponse
    {
        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');
        $dateStr = $request->query->get('date');

        if (!$lat || !$lng || !$dateStr) {
            return new JsonResponse(['success' => false, 'message' => 'Missing parameters.'], 400);
        }

        try {
            $date = new \DateTime($dateStr);
            $forecast = $weatherService->getForecast((float) $lat, (float) $lng, $date);

            if ($forecast) {
                return new JsonResponse(['success' => true, 'data' => $forecast]);
            }

            return new JsonResponse(['success' => false, 'message' => 'No forecast data available.'], 404);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid date format.'], 400);
        }
    }

    #[Route('/', name: 'app_evenement_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EvenementRepository $evenementRepository,
        ParticipationRepository $participationRepository,
        \App\Repository\User\UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        EventRecommendationService $recommendationService,
        EvenementNotificationService $evenementNotificationService,
        MessageBusInterface $messageBus
    ): Response
    {
        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $request->isXmlHttpRequest()) {
            try {
                if ($form->isValid()) {
                    $user = $this->resolveUser($request, $userRepository);
                    if ($user) {
                        $evenement->setUser($user);
                    } else {
                        return new JsonResponse(['success' => false, 'message' => 'You must be logged in to create an event.'], 401);
                    }

                    $this->normalizeCreatedEventCapacity($evenement);
                    if ($this->isDuplicateEventCreate($request, $evenement, $user)) {
                        return new JsonResponse(['success' => true, 'message' => 'Event created successfully!', 'duplicate' => true]);
                    }

                    $imageFile = $form->get('image_event')->getData();
                    if ($imageFile) {
                        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                        $safeFilename = $slugger->slug($originalFilename);
                        $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                        try {
                            $evenement->setImageEvent($this->imageStorage->storeUploadedFile(
                                $imageFile,
                                $this->getParameter('event_images_directory'),
                                'event_images',
                                '/syndicati/event_images',
                                $newFilename
                            ));
                        } catch (\Exception $e) {
                            // Fallback if move fails (e.g. permission or dir missing)
                        }
                    }

                    $entityManager->persist($evenement);
                    $entityManager->flush();
                    $this->rememberEventCreate($request, $evenement, $user);
                    $this->userStandingService->awardForAction($user, 'CREATE_EVENT');
                    $messageBus->dispatch(new NotifyEventCreationMessage($evenement->getId()));

                    $this->notifService->notify(
                        $user,
                        'SUCCESS',
                        'EVENT',
                        $evenement->getId(),
                        'Événement créé',
                        'Votre événement "' . $evenement->getTitreEvent() . '" a été publié.'
                    );

                    return new JsonResponse(['success' => true, 'message' => 'Event created successfully!']);
                }

                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $fieldName = $error->getOrigin()->getName();
                    $errors[$fieldName] = $error->getMessage();
                }
                return new JsonResponse(['success' => false, 'errors' => $errors], 400);
            } catch (\Exception $e) {
                return new JsonResponse(['success' => false, 'message' => 'A server error occurred: ' . $e->getMessage()], 500);
            }
        }

        $participatedEventIds = [];
        $participationIdsByEvent = [];
        $participationHistory = [];
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
            $participationHistory = $participationRepository->findByUserWithEvent($userForCheck);
            foreach ($participationHistory as $participation) {
                $event = $participation->getEvenement();
                if (!$event || $participation->getStatutParticipation() === 'annule') {
                    continue;
                }
                $participatedEventIds[] = $event->getId();
                $participationIdsByEvent[$event->getId()] = $participation->getId();
            }
        }

        /** @var User|null $user */
        $user = $userForCheck;
        $currentUserId = ($user instanceof User) ? $user->getIdUser() : null;
        $currentUserRole = ($user instanceof User) ? $user->getRoleUser() : null;
        $adminRoles = ['ADMIN', 'ROLE_ADMIN', 'SUPERADMIN', 'ROLE_SUPER_ADMIN', 'OWNER', 'SYNDIC'];
        $isAdmin = $currentUserRole && in_array($currentUserRole, $adminRoles, true);

        $events = $evenementRepository->findAllWithUser();

        return $this->render('frontend/evenement/index.html.twig', [
            'evenements' => $events,
            'recommendedEvents' => $recommendationService->recommend($events, $participationHistory),
            'form' => $form->createView(),
            'participationForm' => $this->createForm(ParticipationType::class)->createView(),
            'participatedEventIds' => $participatedEventIds,
            'participationIdsByEvent' => $participationIdsByEvent,
            'currentUserId' => $currentUserId,
            'currentUserRole' => $currentUserRole,
            'isAdmin' => $isAdmin,
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
    public function adminNewEvenement(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        \App\Repository\User\UserRepository $userRepository,
        EvenementNotificationService $evenementNotificationService,
        MessageBusInterface $messageBus
    ): JsonResponse {
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
            $this->normalizeCreatedEventCapacity($evenement);
            if ($this->isDuplicateEventCreate($request, $evenement, $user instanceof User ? $user : null)) {
                return new JsonResponse(['success' => true, 'message' => 'Event created successfully!', 'duplicate' => true]);
            }

            $imageFile = $form->get('image_event')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $evenement->setImageEvent($this->imageStorage->storeUploadedFile(
                        $imageFile,
                        $this->getParameter('event_images_directory'),
                        'event_images',
                        '/syndicati/event_images',
                        $newFilename
                    ));
                } catch (\Exception $e) {
                }
            }

            $entityManager->persist($evenement);
            $entityManager->flush();
            $this->rememberEventCreate($request, $evenement, $user instanceof User ? $user : null);
            if ($user instanceof User) {
                $this->userStandingService->awardForAction($user, 'CREATE_EVENT');
            }
            $messageBus->dispatch(new NotifyEventCreationMessage($evenement->getId()));

            if ($user instanceof User) {
                $this->notifService->notify(
                    $user,
                    'SUCCESS',
                    'EVENT',
                    $evenement->getId(),
                    'Événement créé',
                    'L\'événement a été créé avec succès par l\'administration.'
                );
            }

            return new JsonResponse(['success' => true, 'message' => 'Event created successfully!']);
        }

        return new JsonResponse(['success' => false, 'errors' => $this->getFormErrors($form)], 400);
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

            $user = $this->getUser();
            if ($user) {
                $this->notifService->notify(
                    $user,
                    'SUCCESS',
                    'EVENT',
                    $evenement->getId(),
                    'Événement modifié',
                    'Les modifications de l\'événement ont été enregistrées.'
                );
            }

            return new JsonResponse(['success' => true, 'message' => 'Event updated successfully.']);
        }

        return new JsonResponse(['success' => false, 'errors' => $this->getFormErrors($form)], 400);
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

            $deletedParticipations = $this->deleteEventAndParticipations($evenement, $participationRepository, $em);

            $user = $this->getUser();
            if ($user) {
                $this->notifService->notify(
                    $user,
                    'SUCCESS',
                    'EVENT',
                    $id,
                    'Événement supprimé',
                    'L\'événement a été supprimé ainsi que ses participations.'
                );
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Event and its participations deleted successfully.',
                'deletedParticipations' => $deletedParticipations,
            ]);
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

            $participantUser = $participation->getUser();
            if ($participantUser) {
                $this->notifService->notify(
                    $participantUser,
                    'EVENT_UPDATE',
                    'PARTICIPATION',
                    $participation->getId(),
                    'Statut de participation mis à jour',
                    'Votre participation à l\'événement "' . $evenement->getTitreEvent() . '" est maintenant: ' . $newStatus
                );
            }

            return new JsonResponse(['success' => true, 'message' => 'Participation updated successfully.']);
        }

        return new JsonResponse(['success' => false, 'errors' => $this->getFormErrors($form)], 400);
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

        $participantUser = $participation->getUser();
        if ($participantUser) {
            $this->notifService->notify(
                $participantUser,
                'WARNING',
                'PARTICIPATION',
                $id,
                'Participation supprimée',
                'Votre participation à l\'événement a été annulée par l\'administration.'
            );
        }

        return new JsonResponse(['success' => true, 'message' => 'Participation deleted successfully.']);
    }

    #[Route('/new', name: 'app_evenement_new', methods: ['POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        \App\Repository\User\UserRepository $userRepository,
        EvenementNotificationService $evenementNotificationService,
        MessageBusInterface $messageBus
    ): JsonResponse {
        try {
            $evenement = new Evenement();
            $form = $this->createForm(EvenementType::class, $evenement);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                if ($form->isValid()) {
                    $user = $this->resolveUser($request, $userRepository);
                    if ($user) {
                        $evenement->setUser($user);
                    } else {
                        return new JsonResponse(['success' => false, 'message' => 'Session expired. Please log in again.'], 401);
                    }

                    $this->normalizeCreatedEventCapacity($evenement);
                    if ($this->isDuplicateEventCreate($request, $evenement, $user)) {
                        return new JsonResponse(['success' => true, 'message' => 'Event created successfully!', 'duplicate' => true]);
                    }

                    /** @var UploadedFile $imageFile */
                    $imageFile = $form->get('image_event')->getData();

                    if ($imageFile) {
                        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                        $safeFilename = $slugger->slug($originalFilename);
                        $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                        try {
                            $evenement->setImageEvent($this->imageStorage->storeUploadedFile(
                                $imageFile,
                                $this->getParameter('event_images_directory'),
                                'event_images',
                                '/syndicati/event_images',
                                $newFilename
                            ));
                        } catch (\Exception $e) {
                        }
                    }

                    $entityManager->persist($evenement);
                    $entityManager->flush();
                    $this->rememberEventCreate($request, $evenement, $user);
                    $this->userStandingService->awardForAction($user, 'CREATE_EVENT');
                    $messageBus->dispatch(new NotifyEventCreationMessage($evenement->getId()));

                    if ($user instanceof User) {
                        $this->notifService->notify(
                            $user,
                            'SUCCESS',
                            'EVENT',
                            $evenement->getId(),
                            'Événement créé',
                            'Votre événement "' . $evenement->getTitreEvent() . '" est en ligne.'
                        );
                    }

                    return new JsonResponse(['success' => true, 'message' => 'Event created successfully!']);
                }

                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $fieldName = $error->getOrigin()->getName();
                    $errors[$fieldName] = $error->getMessage();
                }
                return new JsonResponse(['success' => false, 'errors' => $errors], 400);
            }

            return new JsonResponse(['success' => false, 'message' => 'Form not submitted correctly.'], 400);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{id}', name: 'app_evenement_show', methods: ['GET'])]
    public function show(Evenement $evenement): Response
    {
        return $this->redirectToRoute('app_evenement_index');
    }

    #[Route('/{id}/edit', name: 'app_evenement_edit', methods: ['POST'])]
    public function edit(Request $request, Evenement $evenement, EntityManagerInterface $entityManager, SluggerInterface $slugger, \App\Repository\User\UserRepository $userRepository): JsonResponse
    {
        try {
            $user = $this->resolveUser($request, $userRepository);
            if (!$user) {
                return new JsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
            }

            if (!$this->canManageEvent($user, $evenement)) {
                return new JsonResponse(['success' => false, 'message' => 'Unauthorized. Owner or admin access required.'], 403);
            }

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
                        $evenement->setImageEvent($this->imageStorage->storeUploadedFile(
                            $imageFile,
                            $this->getParameter('event_images_directory'),
                            'event_images',
                            '/syndicati/event_images',
                            $newFilename
                        ));
                    } catch (\Exception $e) {
                    }
                }

                $entityManager->flush();

                if ($user instanceof User) {
                    $this->notifService->notify(
                        $user,
                        'SUCCESS',
                        'EVENT',
                        $evenement->getId(),
                        'Événement modifié',
                        'Votre événement a été mis à jour avec succès.'
                    );
                }

                return new JsonResponse(['success' => true, 'message' => 'Event updated successfully!']);
            }

            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $fieldName = $error->getOrigin()->getName();
                $errors[$fieldName] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'errors' => $errors], 400);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{id}', name: 'app_evenement_delete', methods: ['POST'])]
    public function delete(Request $request, Evenement $evenement, EntityManagerInterface $entityManager, \App\Repository\User\UserRepository $userRepository, ParticipationRepository $participationRepository): JsonResponse
    {
        try {
            if ($this->isCsrfTokenValid('delete' . $evenement->getId(), $request->request->get('_token'))) {
                $eventId = $evenement->getId();
                $user = $this->resolveUser($request, $userRepository);
                if (!$user) {
                    return new JsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
                }
                if (!$this->canManageEvent($user, $evenement)) {
                    return new JsonResponse(['success' => false, 'message' => 'Unauthorized. Owner or admin access required.'], 403);
                }

                $deletedParticipations = $this->deleteEventAndParticipations($evenement, $participationRepository, $entityManager);

                if ($user) {
                    $this->notifService->notify(
                        $user,
                        'SUCCESS',
                        'EVENT',
                        $eventId,
                        'Événement supprimé',
                        'L\'événement a été retiré.'
                    );
                }

                return new JsonResponse([
                    'success' => true,
                    'message' => 'Event and its participations deleted successfully!',
                    'deletedParticipations' => $deletedParticipations,
                ]);
            }

            return new JsonResponse(['success' => false, 'message' => 'Invalid security token.'], 403);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()], 500);
        }
    }

    private function resolveUser(Request $request, \App\Repository\User\UserRepository $userRepository): ?User
    {
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
        return $user instanceof User ? $user : null;
    }

    private function canManageEvent(User $user, Evenement $evenement): bool
    {
        $owner = $evenement->getUser();
        if ($owner && $owner->getIdUser() === $user->getIdUser()) {
            return true;
        }

        return $this->isEventAdmin($user);
    }

    private function isEventAdmin(User $user): bool
    {
        return in_array($user->getRoleUser(), ['ADMIN', 'ROLE_ADMIN', 'SUPERADMIN', 'ROLE_SUPER_ADMIN', 'OWNER', 'SYNDIC'], true);
    }

    private function deleteEventAndParticipations(
        Evenement $evenement,
        ParticipationRepository $participationRepository,
        EntityManagerInterface $entityManager
    ): int {
        $deletedParticipations = $participationRepository->deleteByEvenement($evenement);
        $entityManager->remove($evenement);
        $entityManager->flush();

        return (int) $deletedParticipations;
    }

    private function normalizeCreatedEventCapacity(Evenement $evenement): void
    {
        $places = max(0, (int) $evenement->getNbPlaces());
        $remaining = $evenement->getNbRestants();

        if ($remaining === null || $remaining > $places) {
            $evenement->setNbRestants($places);
        }

        if ($evenement->getStatutEvent() === null) {
            $evenement->setStatutEvent('planifie');
        }
    }

    private function isDuplicateEventCreate(Request $request, Evenement $evenement, ?User $user): bool
    {
        $last = $request->getSession()->get('last_event_create');
        if (!is_array($last)) {
            return false;
        }

        return ($last['fingerprint'] ?? null) === $this->eventCreateFingerprint($evenement, $user)
            && ((time() - (int) ($last['time'] ?? 0)) < 12);
    }

    private function rememberEventCreate(Request $request, Evenement $evenement, ?User $user): void
    {
        $request->getSession()->set('last_event_create', [
            'fingerprint' => $this->eventCreateFingerprint($evenement, $user),
            'time' => time(),
        ]);
    }

    private function eventCreateFingerprint(Evenement $evenement, ?User $user): string
    {
        return hash('sha256', implode('|', [
            $user?->getIdUser() ?? 0,
            mb_strtolower(trim((string) $evenement->getTitreEvent())),
            $evenement->getDateEvent()?->format('c') ?? '',
            mb_strtolower(trim((string) $evenement->getLieuEvent())),
            (string) $evenement->getNbPlaces(),
            (string) $evenement->getTypeEvent(),
        ]));
    }
}

