<?php

namespace App\Controller\Forum;

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
        EntityManagerInterface $entityManager,
        \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface $csrfTokenManager,
        \App\Repository\Forum\CommentReactionRepository $commentReactionRepository,
        \App\Repository\Forum\CommentReportRepository $commentReportRepository
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
        $currentUserId = $this->getUserIdFromSession($currentUser);
        $currentUserRole = is_array($currentUser) ? ($currentUser['role'] ?? null) : (is_object($currentUser) && method_exists($currentUser, 'getRole') ? $currentUser->getRole() : (isset($currentUser->roleUser) ? $currentUser->roleUser : null));
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
                'deleteToken' => $csrfTokenManager->getToken('delete_comment' . $comment->getIdCommentaire())->getValue(),
                'editToken' => $csrfTokenManager->getToken('edit_comment' . $comment->getIdCommentaire())->getValue(),
                'image' => $comment->getImageCommentaire() ? '/commentaire_images/' . $comment->getImageCommentaire() : null,
                'counts' => [
                    'likes' => $commentReactionRepository->count(['comment' => $comment, 'reaction_type' => 'like']),
                    'dislikes' => $commentReactionRepository->count(['comment' => $comment, 'reaction_type' => 'dislike']),
                    'reports' => $commentReportRepository->count(['comment' => $comment])
                ],
                'userInteraction' => [
                    'reaction' => $currentUserId ? ($commentReactionRepository->findOneBy(['comment' => $comment, 'user' => $entityManager->getRepository(\App\Entity\User\User::class)->find($currentUserId)])?->getReactionType()) : null,
                    'isReported' => $currentUserId ? ($commentReportRepository->findOneBy(['comment' => $comment, 'user' => $entityManager->getRepository(\App\Entity\User\User::class)->find($currentUserId)]) !== null) : false
                ]
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

        $user = $entityManager->getRepository(\App\Entity\User\User::class)->find($this->getUserIdFromSession($userSession));

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
        EntityManagerInterface $entityManager,
        \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface $csrfTokenManager
    ): JsonResponse {
        $commentaire = $commentaireRepository->find($id);
        $userSession = $request->getSession()->get('user');

        if (!$commentaire || !$userSession) {
            return new JsonResponse(['success' => false, 'message' => 'Not found or not logged in'], 404);
        }

        // CSRF Check
        if (!$csrfTokenManager->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('delete_comment' . $commentaire->getIdCommentaire(), $request->request->get('_token')))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid security token.'], 403);
        }

        $currentUserId = $this->getUserIdFromSession($userSession);
        
        // Handle role extraction from session User entity or array
        $currentUserRole = null;
        if (is_array($userSession)) {
            $currentUserRole = $userSession['role'] ?? $userSession['role_user'] ?? null;
        } elseif (is_object($userSession)) {
            if (method_exists($userSession, 'getRoleUser')) {
                $currentUserRole = $userSession->getRoleUser();
            } elseif (method_exists($userSession, 'getRoles')) {
                $roles = $userSession->getRoles();
                $currentUserRole = is_array($roles) ? ($roles[0] ?? null) : $roles;
            } elseif (method_exists($userSession, 'getRole')) {
                $currentUserRole = $userSession->getRole();
            }
        }

        $moderatorRoles = ['OWNER', 'ADMIN', 'SUPERADMIN', 'SYNDIC'];
        $isAuthor = ($commentaire->getUser() && $commentaire->getUser()->getIdUser() == $currentUserId);
        $isModerator = $currentUserRole && in_array($currentUserRole, $moderatorRoles, true);

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

        $currentUserId = $this->getUserIdFromSession($userSession);
        
        // Handle role extraction from session User entity or array
        $currentUserRole = null;
        if (is_array($userSession)) {
            $currentUserRole = $userSession['role'] ?? $userSession['role_user'] ?? null;
        } elseif (is_object($userSession)) {
            if (method_exists($userSession, 'getRoleUser')) {
                $currentUserRole = $userSession->getRoleUser();
            } elseif (method_exists($userSession, 'getRoles')) {
                $roles = $userSession->getRoles();
                $currentUserRole = is_array($roles) ? ($roles[0] ?? null) : $roles;
            } elseif (method_exists($userSession, 'getRole')) {
                $currentUserRole = $userSession->getRole();
            }
        }

        $moderatorRoles = ['OWNER', 'ADMIN', 'SUPERADMIN', 'SYNDIC'];
        $isAuthor = ($commentaire->getUser()->getIdUser() == $currentUserId);
        $isModerator = $currentUserRole && in_array($currentUserRole, $moderatorRoles, true);

        // CSRF Check
        if (!$this->isCsrfTokenValid('edit_comment' . $commentaire->getIdCommentaire(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid security token.'], 403);
        }

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

    private function getUserIdFromSession($userSession): ?int
    {
        if (is_object($userSession) && method_exists($userSession, 'getIdUser')) {
            return $userSession->getIdUser();
        } elseif (is_object($userSession) && method_exists($userSession, 'getId')) {
            return $userSession->getId();
        } elseif (is_array($userSession)) {
            return $userSession['id_user'] ?? $userSession['id'] ?? null;
        }
        return null;
    }
}
