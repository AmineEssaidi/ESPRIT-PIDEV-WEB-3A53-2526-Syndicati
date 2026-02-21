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

#[Route('/participation')]
class ParticipationController extends AbstractController
{
    #[Route('/new/{id}', name: 'app_participation_new', methods: ['POST'])]
    public function new(Request $request, Evenement $evenement, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
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

                if ($isAjax) {
                    return new JsonResponse(['success' => true, 'message' => 'Your participation request has been sent!']);
                }
                return $this->redirectToRoute('app_evenement_index');
            }

            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }

            if ($isAjax) {
                return new JsonResponse(['success' => false, 'message' => implode(' ', $errors)], 400);
            }

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
}
