<?php

namespace App\Controller\Frontend\Forum;

use App\Entity\Forum\Commentaire;
use App\Entity\Forum\Publication;
use App\Repository\Forum\CommentaireRepository;
use App\Repository\Forum\PublicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/forum/comment')]
class CommentaireController extends AbstractController
{
    #[Route('/list/{id}', name: 'forum_comment_list', methods: ['GET'])]
    public function list(
        int $id,
        Request $request,
        PublicationRepository $publicationRepository,
        CommentaireRepository $commentaireRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $publication = $publicationRepository->find($id);

        if (!$publication) {
            return new JsonResponse(['error' => 'Publication not found'], 404);
        }

        // 1. Fetch Comments (Joins User, but not Profile)
        $commentaires = $commentaireRepository->findByPublicationId($id);

        // 2. Collect User IDs for Batch Profile Fetch
        $userIds = [];
        foreach ($commentaires as $comment) {
            $userIds[] = $comment->getUser()->getIdUser();
        }
        $userIds = array_unique($userIds);

        // 3. Batch Fetch Profiles
        $profiles = [];
        if (!empty($userIds)) {
            $profileEntities = $entityManager->getRepository(\App\Entity\Profile\Profile::class)
                ->createQueryBuilder('p')
                ->where('p.user IN (:ids)')
                ->setParameter('ids', $userIds)
                ->getQuery()
                ->getResult();

            foreach ($profileEntities as $profile) {
                // Ensure mapping by User ID matches how Profile stores it
                $profiles[$profile->getUser()->getIdUser()] = $profile;
            }
        }

        $data = [];
        $currentUser = $request->getSession()->get('user');
        $currentUserId = $currentUser ? ($currentUser['id_user'] ?? $currentUser['id']) : null;
        $currentUserRole = $currentUser ? ($currentUser['role'] ?? null) : null;
        $moderatorRoles = ['OWNER', 'ADMIN', 'SUPERADMIN', 'SYNDIC'];
        $isUserModerator = $currentUserRole && in_array($currentUserRole, $moderatorRoles, true);

        foreach ($commentaires as $comment) {
            $user = $comment->getUser();
            $profile = $profiles[$user->getIdUser()] ?? null;

            // Handle Visibility
            $isAnonymous = !$comment->isVisibility();
            $displayName = $isAnonymous ? 'Anonymous' : $user->getFirstName() . ' ' . $user->getLastName();

            // Handle Avatar
            $avatar = null;
            if (!$isAnonymous) {
                $avatarVal = $profile ? $profile->getAvatar() : null;
                if ($avatarVal) {
                    if (strpos($avatarVal, 'http') === 0) {
                        $avatar = $avatarVal;
                    } elseif (strpos($avatarVal, 'profile_images/') !== false) {
                        // Ensure it starts with / for subdirectory hosting compatibility
                        $avatar = '/' . ltrim($avatarVal, '/');
                    } else {
                        $avatar = '/profile_images/' . $avatarVal;
                    }
                } else {
                    // MANDATORY Fallback exactly as in _main_navbar.html.twig
                    $avatar = 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=200&h=200&fit=crop&crop=face';
                }
            }

            $data[] = [
                'id' => $comment->getIdCommentaire(),
                'description' => $comment->getDescriptionCommentaire(),
                'createdAt' => $comment->getCreatedAt()->format('M d, Y H:i'),
                'updatedAt' => $comment->getUpdatedAt() != $comment->getCreatedAt() ? $comment->getUpdatedAt()->format('M d, Y H:i') : null,
                'author' => [
                    'id' => $user->getIdUser(),
                    'name' => $displayName,
                    'avatar' => $avatar,
                    'isAnonymous' => $isAnonymous
                ],
                'isOwner' => ($currentUserId == $user->getIdUser()),
                'canEdit' => ($currentUserId == $user->getIdUser() || $isUserModerator),
                'canDelete' => ($currentUserId == $user->getIdUser() || $isUserModerator),
                'image' => $comment->getImageCommentaire() ? '/commentaire_images/' . $comment->getImageCommentaire() : null
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/add/{id}', name: 'forum_comment_add', methods: ['POST'])]
    public function add(
        int $id,
        Request $request,
        PublicationRepository $publicationRepository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): JsonResponse {
        $publication = $publicationRepository->find($id);
        $userSession = $request->getSession()->get('user');

        if (!$publication || !$userSession) {
            return new JsonResponse(['success' => false, 'message' => 'Not found or not logged in'], 404);
        }

        // Prevent comments on Announcements (Additional Security)
        if ($publication->getCategoriePub() === 'Announcement') {
            return new JsonResponse(['success' => false, 'message' => 'Comments disabled for announcements'], 403);
        }

        $user = $entityManager->getRepository(\App\Entity\User\User::class)->find($userSession['id_user'] ?? $userSession['id']);

        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'User not found'], 404);
        }

        $description = $request->request->get('description');
        $visibility = $request->request->get('visibility') === 'on'; // Checkbox

        if (!empty($description)) {
            $commentaire = new Commentaire();
            $commentaire->setDescriptionCommentaire($description);
            $commentaire->setVisibility($visibility);
            $commentaire->setPublication($publication);
            $commentaire->setUser($user);

            // Handle Image Upload
            $imageFile = $request->files->get('image_commentaire');
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('commentaire_images_directory'),
                        $newFilename
                    );
                    $commentaire->setImageCommentaire($newFilename);
                } catch (\Exception $e) {
                    // Log or handle error if needed
                }
            }

            $entityManager->persist($commentaire);
            $entityManager->flush();

            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(['success' => false, 'message' => 'Empty description'], 400);
    }

    #[Route('/delete/{id}', name: 'forum_comment_delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        CommentaireRepository $commentaireRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $commentaire = $commentaireRepository->find($id);
        $userSession = $request->getSession()->get('user');

        if (!$commentaire || !$userSession) {
            return new JsonResponse(['success' => false, 'message' => 'Not found or not logged in'], 404);
        }

        $currentUserId = $userSession['id_user'] ?? $userSession['id'];
        $userRole = $userSession['role'] ?? null;
        $moderatorRoles = ['OWNER', 'ADMIN', 'SUPERADMIN', 'SYNDIC'];

        $isAuthor = ($commentaire->getUser()->getIdUser() == $currentUserId);
        $isModerator = $userRole && in_array($userRole, $moderatorRoles, true);

        if (!$isAuthor && !$isModerator) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $entityManager->remove($commentaire);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/edit/{id}', name: 'forum_comment_edit', methods: ['POST'])]
    public function edit(
        int $id,
        Request $request,
        CommentaireRepository $commentaireRepository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): JsonResponse {
        $commentaire = $commentaireRepository->find($id);
        $userSession = $request->getSession()->get('user');

        if (!$commentaire || !$userSession) {
            return new JsonResponse(['success' => false, 'message' => 'Not found or not logged in'], 404);
        }

        $currentUserId = $userSession['id_user'] ?? $userSession['id'];
        $userRole = $userSession['role'] ?? null;
        $moderatorRoles = ['OWNER', 'ADMIN', 'SUPERADMIN', 'SYNDIC'];

        $isAuthor = ($commentaire->getUser()->getIdUser() == $currentUserId);
        $isModerator = $userRole && in_array($userRole, $moderatorRoles, true);

        if (!$isAuthor && !$isModerator) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $description = $request->request->get('description');
        if (!empty($description)) {
            $commentaire->setDescriptionCommentaire($description);

            // Handle Image Edit (Optional: replace or keep)
            $imageFile = $request->files->get('image_commentaire');
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('commentaire_images_directory'),
                        $newFilename
                    );
                    // Optional: Delete old image here if you want
                    $commentaire->setImageCommentaire($newFilename);
                } catch (\Exception $e) {
                }
            }

            $entityManager->flush();
            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(['success' => false, 'message' => 'Empty description'], 400);
    }
}
