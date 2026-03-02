<?php

namespace App\Controller\Forum;

use App\Entity\Forum\Publication;
use App\Repository\Forum\PublicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/forum/publication')]
class PublicationApiController extends AbstractController
{
    #[Route('/update/{id}', name: 'api_forum_publication_update', methods: ['POST'])]
    public function update(
        int $id,
        Request $request,
        PublicationRepository $publicationRepository,
        EntityManagerInterface $entityManager,
        \Symfony\Component\Validator\Validator\ValidatorInterface $validator
    ): JsonResponse {
        $publication = $publicationRepository->find($id);
        $userSession = $request->getSession()->get('user');

        if (!$publication || !$userSession) {
            return new JsonResponse(['success' => false, 'message' => 'Publication not found or not logged in'], 404);
        }

        $userRole = $userSession['role'] ?? null;
        $moderatorRoles = ['OWNER', 'ADMIN', 'SUPERADMIN', 'SYNDIC'];
        $isModerator = $userRole && in_array($userRole, $moderatorRoles, true);

        if (!$isModerator) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized. Admin access required.'], 403);
        }

        $title = $request->request->get('titre_pub');
        $description = $request->request->get('description_pub');
        $category = $request->request->get('categorie_pub');

        if (empty($title) || empty($description) || empty($category)) {
            return new JsonResponse(['success' => false, 'message' => 'All fields (titre_pub, description_pub, categorie_pub) are required.'], 400);
        }

        if (!in_array($category, Publication::CATEGORIES, true)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid category.'], 400);
        }

        $publication->setTitrePub($title);
        $publication->setDescriptionPub($description);
        $publication->setCategoriePub($category);

        $errors = $validator->validate($publication);
        if (count($errors) > 0) {
            $errArray = [];
            foreach ($errors as $error) {
                $prop = $error->getPropertyPath();
                $propLabel = $prop === 'titre_pub' ? 'Title' : ($prop === 'description_pub' ? 'Description' : ucfirst($prop));
                $errArray[] = $propLabel . ': ' . $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'errors' => $errArray], 400);
        }

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Publication updated successfully'
        ]);
    }
}
