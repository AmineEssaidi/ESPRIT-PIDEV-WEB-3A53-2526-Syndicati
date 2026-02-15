<?php

namespace App\Controller\Evenement;

use App\Entity\Evenement\Evenement;
use App\Repository\Evenement\EvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/evenement')]
class EvenementApiController extends AbstractController
{
    #[Route('/update/{id}', name: 'api_evenement_update', methods: ['POST'])]
    public function update(
        int $id,
        Request $request,
        EvenementRepository $evenementRepository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): JsonResponse {
        $evenement = $evenementRepository->find($id);
        $userSession = $request->getSession()->get('user');

        if (!$evenement || !$userSession) {
            return new JsonResponse(['success' => false, 'message' => 'Event not found or not logged in'], 404);
        }

        $userId = $userSession['id_user'] ?? $userSession['id'] ?? null;
        $userRole = $userSession['role'] ?? null;
        $moderatorRoles = ['OWNER', 'ADMIN', 'SUPERADMIN', 'SYNDIC'];

        $isOwner = $evenement->getUser() && $evenement->getUser()->getIdUser() == $userId;
        $isModerator = $userRole && in_array($userRole, $moderatorRoles, true);

        if (!$isOwner && !$isModerator) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized. Owner or Admin access required.'], 403);
        }

        $title = $request->request->get('titre_event');
        $description = $request->request->get('description_event');
        $location = $request->request->get('lieu_event');
        $dateStr = $request->request->get('date_event');
        $places = $request->request->get('nb_places');
        $status = $request->request->get('statut_event');
        $type = $request->request->get('type_event');

        if (empty($title) || empty($description) || empty($location) || empty($dateStr)) {
            return new JsonResponse(['success' => false, 'message' => 'Required fields missing.'], 400);
        }

        $evenement->setTitreEvent($title);
        $evenement->setDescriptionEvent($description);
        $evenement->setLieuEvent($location);
        $evenement->setDateEvent(new \DateTime($dateStr));
        $evenement->setNbPlaces((int) $places);

        $remaining = $request->request->get('nb_restants');
        if ($remaining !== null) {
            $evenement->setNbRestants((int) $remaining);
        }

        // nb_restants processing (could be more complex, but let's keep it simple)
        // If it's a new event, nb_restants = nb_places. If updating, it depends.
        // For now, let's not touch nb_restants unless specified. 

        if ($status && in_array($status, Evenement::STATUTS)) {
            $evenement->setStatutEvent($status);
        }

        if ($type && in_array($type, Evenement::TYPES)) {
            $evenement->setTypeEvent($type);
        }

        // Image Handling
        $imageFile = $request->files->get('image_event');
        if ($imageFile) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

            try {
                $imageFile->move(
                    $this->getParameter('event_images_directory'),
                    $newFilename
                );
                $evenement->setImageEvent($newFilename);
            } catch (\Exception $e) {
                return new JsonResponse(['success' => false, 'message' => 'Image upload failed.'], 500);
            }
        }

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Event updated successfully',
            'data' => [
                'title' => $evenement->getTitreEvent(),
                'description' => $evenement->getDescriptionEvent(),
                'status' => $evenement->getStatutEvent(),
                'image' => $evenement->getImageEvent() ? '/event_images/' . $evenement->getImageEvent() : null,
                'date' => $evenement->getDateEvent()->format('d M Y, H:i'),
                'remaining' => $evenement->getNbRestants()
            ]
        ]);
    }
}
