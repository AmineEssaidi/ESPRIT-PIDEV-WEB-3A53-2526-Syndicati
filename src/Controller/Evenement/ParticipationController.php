<?php

namespace App\Controller\Evenement;

use App\Entity\Evenement\Evenement;
use App\Entity\Evenement\Participation;
use App\Form\Evenement\ParticipationType;
use App\Repository\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/participation')]
class ParticipationController extends AbstractController
{
    #[Route('/new/{id}', name: 'app_participation_new', methods: ['POST'])]
    public function new(Request $request, Evenement $evenement, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
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
        } else {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->redirectToRoute('app_evenement_index');
    }
}
