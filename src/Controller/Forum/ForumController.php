<?php

namespace App\Controller\Forum;

use App\Entity\Forum\Publication;
use App\Form\Forum\PublicationType;
use App\Repository\Forum\PublicationRepository;
use App\Service\DirectAiClient;
use App\Service\Forum\ContentModerationService;
use App\Service\Forum\DiscordWebhookService;
use App\Service\Media\ImageKitStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
    public function index(Request $request, PublicationRepository $publicationRepository, EntityManagerInterface $entityManager, SluggerInterface $slugger, \App\Service\Forum\ForumNotificationService $notificationService, ContentModerationService $moderationService, ImageKitStorageService $imageStorage, DiscordWebhookService $discordWebhook): Response
    {
        $publication = new Publication();
        $session = $request->getSession();
        $userSession = $session->get('user');

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
                $flaggedCategories = $moderationService->checkContent(
                    ($publication->getTitrePub() ?? '') . ' ' . ($publication->getDescriptionPub() ?? '')
                );
                if ($flaggedCategories !== []) {
                    if ($isAjax) {
                        return $this->json([
                            'success' => false,
                            'message' => 'Inappropriate content detected: ' . implode(', ', $flaggedCategories),
                            'moderation' => $flaggedCategories,
                        ], 422);
                    }

                    throw $this->createAccessDeniedException('Inappropriate content detected.');
                }

                /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $imageFile */
                $imageFile = $form->get('image_pub')->getData();

                if ($imageFile) {
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                    try {
                        $publication->setImagePub($imageStorage->storeUploadedFile(
                            $imageFile,
                            $this->getParameter('publications_directory'),
                            'forum_images',
                            '/syndicati/forum_images',
                            $newFilename
                        ));
                    } catch (\Exception $e) {
                        if ($isAjax) {
                            return $this->json(['success' => false, 'message' => 'Failed to upload image.'], 500);
                        }
                    }
                }

                $entityManager->persist($publication);
                $entityManager->flush();

                // Notify if Announcement
                if ($publication->getCategoriePub() === 'Announcement') {
                    $notificationService->notifyNewAnnouncement($publication);
                }
                $discordWebhook->announceJeuxVideo($publication, false);

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

        $publications = $publicationRepository->findAllLatest(36);

        // Fetch all profiles for these publications' authors
        $authorIds = [];
        foreach ($publications as $pub) {
            $authorIds[] = $pub->getUser()->getIdUser();
        }
        $authorIds = array_unique($authorIds);

        $profiles = [];
        if (!empty($authorIds)) {
            $profileEntities = $entityManager->getRepository(\App\Entity\Profile\Profile::class)
                ->createQueryBuilder('p')
                ->where('p.user IN (:ids)')
                ->setParameter('ids', $authorIds)
                ->getQuery()
                ->getResult();

            foreach ($profileEntities as $profile) {
                $profiles[$profile->getUser()->getIdUser()] = $profile;
            }
        }

        return $this->render('frontend/forum/index.html.twig', [
            'publications' => $publications,
            'author_profiles' => $profiles,
            'form' => $form->createView(),
            'currentUser' => $userSession,
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
    public function edit(int $id, Request $request, PublicationRepository $publicationRepository, EntityManagerInterface $entityManager, SluggerInterface $slugger, ValidatorInterface $validator, ImageKitStorageService $imageStorage, DiscordWebhookService $discordWebhook): Response
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
                $publication->setImagePub($imageStorage->storeUploadedFile(
                    $imageFile,
                    $this->getParameter('publications_directory'),
                    'forum_images',
                    '/syndicati/forum_images',
                    $newFilename
                ));
            } catch (\Exception $e) {
                if ($isAjax)
                    return $this->json(['success' => false, 'message' => 'Failed to upload image.'], 500);
            }
        }

        $entityManager->flush();
        $discordWebhook->announceJeuxVideo($publication, true);

        if ($isAjax) {
            return $this->json(['success' => true, 'message' => 'Post updated successfully!']);
        }

        return $this->redirectToRoute('frontend_forum');
    }

    #[Route('/forum/ajax/list', name: 'frontend_forum_ajax_list', methods: ['GET'])]
    public function ajaxList(Request $request, PublicationRepository $publicationRepository, EntityManagerInterface $entityManager): Response
    {
        $category = $request->query->get('category', 'General');
        $publications = $publicationRepository->findByCategory($category, 36);

        // Fetch profiles
        $authorIds = [];
        foreach ($publications as $pub) {
            $authorIds[] = $pub->getUser()->getIdUser();
        }
        $authorIds = array_unique($authorIds);

        $profiles = [];
        if (!empty($authorIds)) {
            $profileEntities = $entityManager->getRepository(\App\Entity\Profile\Profile::class)
                ->createQueryBuilder('p')
                ->where('p.user IN (:ids)')
                ->setParameter('ids', $authorIds)
                ->getQuery()
                ->getResult();

            foreach ($profileEntities as $profile) {
                $profiles[$profile->getUser()->getIdUser()] = $profile;
            }
        }

        return $this->render('frontend/forum/_ajax_list.html.twig', [
            'publications' => $publications,
            'author_profiles' => $profiles,
        ]);
    }

    #[Route('/forum/feeling/publication/{id}', name: 'frontend_forum_feeling_publication', methods: ['POST'])]
    public function feelingPublication(int $id, PublicationRepository $publicationRepository, DirectAiClient $ai): JsonResponse
    {
        $publication = $publicationRepository->find($id);
        if (!$publication) {
            return $this->json(['success' => false, 'message' => 'Post not found.'], 404);
        }

        $text = trim(sprintf(
            "Title: %s\nCategory: %s\nContent: %s",
            $publication->getTitrePub() ?? '',
            $publication->getCategoriePub() ?? '',
            $publication->getDescriptionPub() ?? ''
        ));

        return $this->json([
            'success' => true,
            'feeling' => $ai->analyzeFeeling($text),
        ]);
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
