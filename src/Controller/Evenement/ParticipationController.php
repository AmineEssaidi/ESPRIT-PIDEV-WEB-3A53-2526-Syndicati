<?php

namespace App\Controller\Evenement;

use App\Entity\Evenement\Evenement;
use App\Entity\Evenement\Participation;
use App\Form\Evenement\ParticipationType;
use App\Repository\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Service\FormErrorHelperTrait;
use App\Service\User\NotificationService;
use App\Message\Evenement\NotifyParticipationConfirmationMessage;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Symfony\Component\Messenger\MessageBusInterface;

#[Route('/participation')]
class ParticipationController extends AbstractController
{
    use FormErrorHelperTrait;

    #[Route('/new/{id}', name: 'app_participation_new', methods: ['POST'])]
    public function new(
        Request $request,
        Evenement $evenement,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        \App\Repository\Evenement\ParticipationRepository $participationRepository,
        \App\Service\Evenement\EvenementNotificationService $notificationService,
        NotificationService $notifService,
        MessageBusInterface $messageBus
    ): Response {
        try {
            $isAjax = $request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

            // Get user from Security or Session fallback
            $user = $this->getUser();
            if (!$user) {
                $sessionUser = $request->getSession()->get('user');
                if ($sessionUser) {
                    $userId = null;
                    if (is_array($sessionUser)) {
                        $userId = $sessionUser['id_user'] ?? $sessionUser['id'] ?? null;
                    } elseif (is_object($sessionUser)) {
                        if (method_exists($sessionUser, 'getIdUser')) {
                            $userId = $sessionUser->getIdUser();
                        } elseif (method_exists($sessionUser, 'getId')) {
                            $userId = $sessionUser->getId();
                        }
                    }

                    if ($userId) {
                        $user = $userRepository->find($userId);
                    }
                }
            }

            if (!$user) {
                if ($isAjax) {
                    return new JsonResponse(['success' => false, 'message' => 'You must be logged in to participate.'], 403);
                }
                $this->addFlash('error', 'You must be logged in to participate.');
                return $this->redirectToRoute('app_evenement_index');
            }

            /** @var \App\Entity\User\User $user */

            // SERVER-SIDE GUARD: Check if already registered
            $existing = $participationRepository->findOneBy([
                'user' => $user,
                'evenement' => $evenement
            ]);

            if ($existing) {
                if ($isAjax) {
                    return new JsonResponse([
                        'success' => true,
                        'message' => 'You are already registered for this event. No action needed.'
                    ]);
                }
                $this->addFlash('info', 'You are already registered for this event.');
                return $this->redirectToRoute('app_evenement_index');
            }

            $participation = new Participation();
            $participation->setEvenement($evenement);
            $participation->setUser($user);
            $participation->setStatutParticipation('en_attente');
            $participation->setDateParticipation(new \DateTime());

            $form = $this->createForm(ParticipationType::class, $participation);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // Fill formulaire_data
                $formulaireData = [
                    'user_name' => $user->getFirstName() . ' ' . $user->getLastName(),
                    'email' => $user->getEmailUser(),
                    'nb_accompagnants' => $participation->getNbAccompagnants(),
                    'commentaire' => $participation->getCommentaireParticipation(),
                    'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'edited_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'event_title' => $evenement->getTitreEvent()
                ];
                $participation->setFormulaireData($formulaireData);

                $entityManager->persist($participation);
                $entityManager->flush();

                // Send Confirmation Email
                $messageBus->dispatch(new NotifyParticipationConfirmationMessage($participation->getId()));

                // 1. Notify Participant (Confirmation)
                $notifService->notify(
                    $user,
                    'SUCCESS',
                    'PARTICIPATION',
                    $participation->getId(),
                    'Inscription envoyée',
                    'Votre demande de participation à "' . $evenement->getTitreEvent() . '" est en attente.'
                );

                // 2. Notify Event Owner
                $eventOwner = $evenement->getUser();
                if ($eventOwner && $eventOwner->getIdUser() !== $user->getIdUser()) {
                    $notifService->notify(
                        $eventOwner,
                        'EVENT_PARTICIPANT',
                        'PARTICIPATION',
                        $participation->getId(),
                        'Nouvelle participation',
                        $user->getFirstName() . ' souhaite participer à votre événement: ' . $evenement->getTitreEvent()
                    );
                }

                if ($isAjax) {
                    return new JsonResponse([
                        'success' => true,
                        'message' => 'Your participation request has been sent!',
                        'ticketUrl' => $this->generateUrl('app_participation_ticket', ['id' => $participation->getId()]),
                    ]);
                }
                return $this->redirectToRoute('app_evenement_index');
            }

            if ($isAjax) {
                return new JsonResponse(['success' => false, 'errors' => $this->getFormErrors($form)], 400);
            }

            $errors = $this->getFormErrors($form);
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }

            return $this->redirectToRoute('app_evenement_index');
        } catch (\Exception $e) {
            if ($request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
                return new JsonResponse(['success' => false, 'message' => 'Participation Error: ' . $e->getMessage()], 500);
            }
            throw $e;
        }
    }

    #[Route('/ticket/{id}', name: 'app_participation_ticket', methods: ['GET'])]
    public function ticket(
        int $id,
        Request $request,
        \App\Repository\Evenement\ParticipationRepository $participationRepository,
        UserRepository $userRepository
    ): Response {
        $participation = $participationRepository->find($id);
        if (!$participation) {
            throw $this->createNotFoundException('Participation not found.');
        }

        $currentUser = $this->resolveUser($request, $userRepository);
        $owner = $participation->getUser();
        $sessionUser = $request->getSession()->get('user');
        $sessionRole = is_array($sessionUser) ? ($sessionUser['role'] ?? $sessionUser['role_user'] ?? null) : null;
        if (!$sessionRole && $currentUser && method_exists($currentUser, 'getRoleUser')) {
            $sessionRole = $currentUser->getRoleUser();
        }
        $moderatorRoles = ['OWNER', 'ADMIN', 'SUPERADMIN', 'SYNDIC', 'ROLE_ADMIN'];
        $isOwner = $currentUser && $owner && $currentUser->getIdUser() === $owner->getIdUser();
        $isModerator = $sessionRole && in_array($sessionRole, $moderatorRoles, true);

        if (!$isOwner && !$isModerator) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized ticket access.'], 403);
        }

        $event = $participation->getEvenement();
        $qrPayload = sprintf(
            'SYNDICATI-PART-%s-%s',
            $participation->getId(),
            $owner ? $owner->getIdUser() : 'GUEST'
        );
        $qrDataUri = (new Builder())->build(data: $qrPayload, size: 160, margin: 8)->getDataUri();

        $html = $this->renderView('frontend/evenement/ticket_pdf.html.twig', [
            'participation' => $participation,
            'evenement' => $event,
            'user' => $owner,
            'qrDataUri' => $qrDataUri,
            'qrPayload' => $qrPayload,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A6', 'portrait');
        $dompdf->render();

        $fileName = sprintf('Ticket_%s_%d.pdf', preg_replace('/[^A-Za-z0-9]+/', '_', $event?->getTitreEvent() ?? 'event'), $participation->getId());

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    private function resolveUser(Request $request, UserRepository $userRepository): ?\App\Entity\User\User
    {
        $user = $this->getUser();
        if ($user instanceof \App\Entity\User\User) {
            return $user;
        }

        $sessionUser = $request->getSession()->get('user');
        $userId = is_array($sessionUser) ? ($sessionUser['id_user'] ?? $sessionUser['id'] ?? null) : null;

        return $userId ? $userRepository->find($userId) : null;
    }
}
