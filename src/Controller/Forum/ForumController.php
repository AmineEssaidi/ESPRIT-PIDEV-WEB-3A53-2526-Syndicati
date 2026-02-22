<?php

namespace App\Controller\Forum;

use App\Entity\Forum\Publication;
use App\Form\Forum\PublicationType;
use App\Repository\Forum\PublicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ForumController extends AbstractController
{
    #[Route('/forum', name: 'frontend_forum', methods: ['GET', 'POST'])]
    public function index(Request $request, PublicationRepository $publicationRepository, EntityManagerInterface $entityManager, SluggerInterface $slugger, \App\Service\MailNotificationService $mailNotificationService): Response
    {
        $publication = new Publication();
        $session = $request->getSession();
        $userSession = $session->get('user');
        $userEntity = null; // Initialize to avoid undefined variable error when not logged in

        if ($userSession) {
            $userId = null;
            if (is_object($userSession) && method_exists($userSession, 'getIdUser')) {
                $userId = $userSession->getIdUser();
            } elseif (is_object($userSession) && method_exists($userSession, 'getId')) {
                $userId = $userSession->getId();
            } elseif (is_array($userSession)) {
                $userId = $userSession['id_user'] ?? $userSession['id'] ?? null;
            }

            if ($userId) {
                $userEntity = $entityManager->getRepository(\App\Entity\User\User::class)->find($userId);
                if ($userEntity) {
                    $publication->setUser($userEntity);
                }
            }
        }

        $form = $this->createForm(PublicationType::class, $publication);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $isAjax = $request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

            // Check if user is logged in
            if (!isset($userEntity) || !$userEntity) {
                if ($isAjax) {
                    return $this->json(['success' => false, 'message' => 'You must be logged in to post.'], 403);
                }
                throw $this->createAccessDeniedException('You must be logged in to post.');
            }

            if ($form->isValid()) {
                /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $imageFile */
                $imageFile = $form->get('image_pub')->getData();

                if ($imageFile) {
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                    try {
                        $imageFile->move(
                            $this->getParameter('publications_directory'),
                            $newFilename
                        );
                    } catch (\Exception $e) {
                        if ($isAjax) {
                            return $this->json(['success' => false, 'message' => 'Failed to upload image.'], 500);
                        }
                    }

                    $publication->setImagePub($newFilename);
                }

                $entityManager->persist($publication);
                $entityManager->flush();

                // Send email notification ONLY for Announcements
                if ($publication->getCategoriePub() === 'Announcement') {
                    $mailNotificationService->sendNewPublicationNotification($publication);
                }

                if ($isAjax) {
                    return $this->json(['success' => true, 'message' => 'Post created successfully!']);
                }

                return $this->redirectToRoute('frontend_forum');
            } else {
                if ($isAjax) {
                    $errors = [];
                    foreach ($form->getErrors(true) as $error) {
                        $fieldName = $error->getOrigin()->getName();
                        $errors[$fieldName] = $error->getMessage();
                    }
                    return $this->json(['success' => false, 'errors' => $errors], 400);
                }
            }
        }

        $type = $request->query->get('type', 'general');
        if ($type === 'announcement') {
            $publications = $publicationRepository->findBy(['categorie_pub' => 'Announcement'], ['date_creation_pub' => 'DESC']);
        } else {
            $publications = $publicationRepository->createQueryBuilder('p')
                ->where('p.categorie_pub != :cat')
                ->setParameter('cat', 'Announcement')
                ->orderBy('p.date_creation_pub', 'DESC')
                ->getQuery()
                ->getResult();
        }

        // Fetch Authors' Profiles (Optimized)
        $profiles = [];
        if (!empty($publications)) {
            $authorIds = array_unique(array_map(fn($p) => $p->getUser()->getIdUser(), $publications));
            $profileEntities = $entityManager->getRepository(\App\Entity\Profile\Profile::class)
                ->createQueryBuilder('p')
                ->select('p', 'u')
                ->join('p.user', 'u')
                ->where('u.id_user IN (:ids)')
                ->setParameter('ids', $authorIds)
                ->getQuery()
                ->getResult();
            foreach ($profileEntities as $profile) {
                $profiles[$profile->getUser()->getIdUser()] = $profile;
            }
        }

        // Global counts & interactions
        $globalCounts = [];
        $userInteractions = ['reactions' => [], 'bookmarks' => [], 'reports' => []];

        if (!empty($publications)) {
            $pubIds = array_map(fn($p) => $p->getId(), $publications);
            
            // Re-add initialization to avoid undefined index errors
            foreach ($pubIds as $pid) {
                $globalCounts[$pid] = ['likes' => 0, 'dislikes' => 0, 'reports' => 0];
            }

            // Optimized global counts using aggregate queries
            $reactionCounts = $entityManager->getRepository(\App\Entity\Forum\PublicationReaction::class)
                ->createQueryBuilder('r')
                ->select('IDENTITY(r.publication) as pubId, r.reaction_type, COUNT(r.id_pubreaction) as count')
                ->where('r.publication IN (:ids)')
                ->setParameter('ids', $pubIds)
                ->groupBy('pubId, r.reaction_type')
                ->getQuery()
                ->getResult();
            foreach ($reactionCounts as $rc) {
                $pid = $rc['pubId'];
                if ($rc['reaction_type'] === 'like') $globalCounts[$pid]['likes'] = (int)$rc['count'];
                elseif ($rc['reaction_type'] === 'dislike') $globalCounts[$pid]['dislikes'] = (int)$rc['count'];
            }

            $reportCounts = $entityManager->getRepository(\App\Entity\Forum\PublicationReport::class)
                ->createQueryBuilder('rep')
                ->select('IDENTITY(rep.publication) as pubId, COUNT(rep.id_report) as count')
                ->where('rep.publication IN (:ids)')
                ->setParameter('ids', $pubIds)
                ->groupBy('pubId')
                ->getQuery()
                ->getResult();
            foreach ($reportCounts as $rc) {
                $globalCounts[$rc['pubId']]['reports'] = (int)$rc['count'];
            }

            // User interactions (Optimized with partial selection)
            if ($userEntity) {
                // User Reactions
                $userReactions = $entityManager->getRepository(\App\Entity\Forum\PublicationReaction::class)
                    ->createQueryBuilder('r')
                    ->select('IDENTITY(r.publication) as pubId, r.reaction_type')
                    ->where('r.user = :user')
                    ->andWhere('r.publication IN (:ids)')
                    ->setParameter('user', $userEntity)
                    ->setParameter('ids', $pubIds)
                    ->getQuery()
                    ->getScalarResult();
                foreach ($userReactions as $ur) {
                    $userInteractions['reactions'][$ur['pubId']] = $ur['reaction_type'];
                }

                // User Bookmarks
                $userBookmarks = $entityManager->getRepository(\App\Entity\Forum\PublicationBookmark::class)
                    ->createQueryBuilder('b')
                    ->select('IDENTITY(b.publication) as pubId')
                    ->where('b.user = :user')
                    ->andWhere('b.publication IN (:ids)')
                    ->setParameter('user', $userEntity)
                    ->setParameter('ids', $pubIds)
                    ->getQuery()
                    ->getScalarResult();
                foreach ($userBookmarks as $ub) {
                    $userInteractions['bookmarks'][$ub['pubId']] = 'true';
                }

                // User Reports
                $userReports = $entityManager->getRepository(\App\Entity\Forum\PublicationReport::class)
                    ->createQueryBuilder('rep')
                    ->select('IDENTITY(rep.publication) as pubId')
                    ->where('rep.user = :user')
                    ->andWhere('rep.publication IN (:ids)')
                    ->setParameter('user', $userEntity)
                    ->setParameter('ids', $pubIds)
                    ->getQuery()
                    ->getScalarResult();
                foreach ($userReports as $urp) {
                    $userInteractions['reports'][$urp['pubId']] = 'true';
                }
            }
        }

        // Return AJAX Response for Filtering
        if ($request->query->has('ajax_filter')) {
            return $this->json([
                'success' => true,
                'html' => $this->renderView('frontend/forum/_list_content.html.twig', [
                    'publications' => $publications,
                    'author_profiles' => $profiles,
                    'globalCounts' => $globalCounts,
                    'userInteractions' => $userInteractions,
                ])
            ]);
        }

        return $this->render('frontend/forum/index.html.twig', [
            'publications' => $publications,
            'author_profiles' => $profiles,
            'form' => $form->createView(),
            'currentUser' => $userSession,
            'userInteractions' => $userInteractions,
            'globalCounts' => $globalCounts,
            'activeType' => $type,
        ]);
    }

    #[Route('/forum/delete/{id}', name: 'frontend_forum_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, PublicationRepository $publicationRepository, EntityManagerInterface $entityManager): Response
    {
        $publication = $publicationRepository->find($id);
        $isAjax = $request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

        if (!$publication) {
            if ($isAjax)
                return $this->json(['success' => false, 'message' => 'Post not found.'], 404);
            throw $this->createNotFoundException('Post not found.');
        }

        // CSRF Token Check
        if (!$this->isCsrfTokenValid('delete' . $publication->getId(), $request->request->get('_token'))) {
            if ($isAjax)
                return $this->json(['success' => false, 'message' => 'Invalid security token.'], 403);
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $session = $request->getSession();
        $userSession = $session->get('user');
        $userId = $this->getUserIdFromSession($userSession);
        $userRole = $userSession['role'] ?? null;
        $moderatorRoles = ['OWNER', 'ADMIN', 'SUPERADMIN', 'SYNDIC'];

        $isAuthor = $userId && $publication->getUser()->getIdUser() === $userId;
        $isModerator = $userRole && in_array($userRole, $moderatorRoles, true);

        if (!$isAuthor && !$isModerator) {
            if ($isAjax)
                return $this->json(['success' => false, 'message' => 'Unauthorized.'], 403);
            throw $this->createAccessDeniedException('You are not authorized to delete this post.');
        }

        $entityManager->remove($publication);
        $entityManager->flush();

        if ($isAjax) {
            return $this->json(['success' => true, 'message' => 'Post deleted successfully!']);
        }

        return $this->redirectToRoute('frontend_forum');
    }

    #[Route('/forum/edit/{id}', name: 'frontend_forum_edit', methods: ['POST'])]
    public function edit(int $id, Request $request, PublicationRepository $publicationRepository, EntityManagerInterface $entityManager, SluggerInterface $slugger, ValidatorInterface $validator): Response
    {
        $publication = $publicationRepository->find($id);
        $isAjax = $request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

        if (!$publication) {
            if ($isAjax)
                return $this->json(['success' => false, 'message' => 'Post not found.'], 404);
            throw $this->createNotFoundException('Post not found.');
        }

        $session = $request->getSession();
        $userSession = $session->get('user');
        $userId = $this->getUserIdFromSession($userSession);
        $userRole = $userSession['role'] ?? null;
        $moderatorRoles = ['OWNER', 'ADMIN', 'SUPERADMIN', 'SYNDIC'];

        $isAuthor = $userId && $publication->getUser()->getIdUser() === $userId;
        $isModerator = $userRole && in_array($userRole, $moderatorRoles, true);

        if (!$isAuthor && !$isModerator) {
            if ($isAjax)
                return $this->json(['success' => false, 'message' => 'Unauthorized.'], 403);
            throw $this->createAccessDeniedException('Unauthorized.');
        }

        $title = $request->request->get('title');
        $description = $request->request->get('description');
        $category = $request->request->get('category');

        if ($title)
            $publication->setTitrePub($title);
        if ($description)
            $publication->setDescriptionPub($description);
        if ($category)
            $publication->setCategoriePub($category);

        // Manual validation since we're not using a Symfony Form here
        $errors = $validator->validate($publication);
        if (count($errors) > 0) {
            if ($isAjax) {
                $errorsMap = [];
                foreach ($errors as $error) {
                    $field = $error->getPropertyPath();
                    $errorsMap[$field] = $error->getMessage();
                }
                return $this->json(['success' => false, 'errors' => $errorsMap], 400);
            }
        }

        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $imageFile */
        $imageFile = $request->files->get('image_pub');
        if ($imageFile) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

            try {
                $imageFile->move(
                    $this->getParameter('publications_directory'),
                    $newFilename
                );
                $publication->setImagePub($newFilename);
            } catch (\Exception $e) {
                if ($isAjax)
                    return $this->json(['success' => false, 'message' => 'Failed to upload image.'], 500);
            }
        }

        $entityManager->flush();

        if ($isAjax) {
            return $this->json(['success' => true, 'message' => 'Post updated successfully!']);
        }

        return $this->redirectToRoute('frontend_forum');
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