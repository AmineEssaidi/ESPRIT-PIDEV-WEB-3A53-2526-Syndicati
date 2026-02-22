<?php

namespace App\Controller\Forum;

use App\Entity\Forum\Publication;
use App\Form\Forum\PublicationType;
use App\Repository\Forum\PublicationRepository;
use App\Entity\Forum\Commentaire;
use App\Form\Forum\CommentaireType;
use App\Repository\Forum\CommentaireRepository;
use App\Service\PageStatusService;
use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/publication')]
class PublicationController extends AbstractController
{
    #[Route('/', name: 'app_publication_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('frontend_forum');
    }

    #[Route('/admin', name: 'admin_forum')]
    public function adminIndex(
        PageStatusService $pageStatusService,
        Request $request,
        PublicationRepository $publicationRepository,
        CommentaireRepository $commentaireRepository,
        FormFactoryInterface $formFactory,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        $publications = $publicationRepository->findBy([], ['date_creation_pub' => 'DESC']);
        $commentaires = $commentaireRepository->findBy([], ['created_at' => 'DESC']);

        // Fetch Global Counts for Admin Table
        $pubIds = array_map(fn($p) => $p->getId(), $publications);
        $globalCounts = [];
        foreach ($pubIds as $id) {
            $globalCounts[$id] = ['likes' => 0, 'dislikes' => 0, 'reports' => 0];
        }

        if (!empty($pubIds)) {
            $em = $publicationRepository->getEntityManager();
            
            // Reactions
            $allReactions = $em->getRepository(\App\Entity\Forum\PublicationReaction::class)
                ->createQueryBuilder('r')
                ->where('r.publication IN (:ids)')
                ->setParameter('ids', $pubIds)
                ->getQuery()
                ->getResult();
            
            foreach ($allReactions as $r) {
                $pid = $r->getPublication()->getId();
                if ($r->getReactionType() === 'like') $globalCounts[$pid]['likes']++;
                elseif ($r->getReactionType() === 'dislike') $globalCounts[$pid]['dislikes']++;
            }

            // Reports
            $allReports = $em->getRepository(\App\Entity\Forum\PublicationReport::class)
                ->createQueryBuilder('rep')
                ->where('rep.publication IN (:ids)')
                ->setParameter('ids', $pubIds)
                ->getQuery()
                ->getResult();
            
            foreach ($allReports as $rep) {
                $globalCounts[$rep->getPublication()->getId()]['reports']++;
            }
        }

        $pubEditForm = $formFactory->createNamed('publication_edit', PublicationType::class, new Publication());
        $pubAddForm = $formFactory->createNamed('publication_add', PublicationType::class, new Publication(), [
            'action' => $this->generateUrl('admin_forum_pub_add'),
            'method' => 'POST',
        ]);
        $commentEditForm = $formFactory->createNamed('comment_edit', CommentaireType::class, new Commentaire());

        // Fetch Global Counts for Comments
        $commentIds = array_map(fn($c) => $c->getIdCommentaire(), $commentaires);
        $commentCounts = [];
        foreach ($commentIds as $id) {
            $commentCounts[$id] = ['likes' => 0, 'dislikes' => 0, 'reports' => 0];
        }

        if (!empty($commentIds)) {
            $em = $commentaireRepository->getEntityManager();
            
            // Comment Reactions
            $allCReactions = $em->getRepository(\App\Entity\Forum\CommentReaction::class)
                ->createQueryBuilder('cr')
                ->where('cr.comment IN (:ids)')
                ->setParameter('ids', $commentIds)
                ->getQuery()
                ->getResult();
            
            foreach ($allCReactions as $cr) {
                $cid = $cr->getComment()->getIdCommentaire();
                if ($cr->getReactionType() === 'like') $commentCounts[$cid]['likes']++;
                elseif ($cr->getReactionType() === 'dislike') $commentCounts[$cid]['dislikes']++;
            }

            // Comment Reports
            $allCReports = $em->getRepository(\App\Entity\Forum\CommentReport::class)
                ->createQueryBuilder('crep')
                ->where('crep.comment IN (:ids)')
                ->setParameter('ids', $commentIds)
                ->getQuery()
                ->getResult();
            
            foreach ($allCReports as $crep) {
                $commentCounts[$crep->getComment()->getIdCommentaire()]['reports']++;
            }
        }

        // Prepare Data for JS
        $pubData = [];
        foreach ($publications as $p) {
            $pid = $p->getId();
            $pubData[] = [
                'topic' => $p->getTitrePub(),
                'category' => $p->getCategoriePub(),
                'author' => $p->getUser() ? ($p->getUser()->getFirstName() . ' ' . $p->getUser()->getLastName()) : '—',
                'date' => $p->getDateCreationPub()->format('Y-m-d H:i'),
                'id' => $pid,
                'description' => $p->getDescriptionPub(),
                'image' => $p->getImagePub(),
                'token' => $csrfTokenManager->getToken('delete_publication' . $pid)->getValue(),
                'likes' => $globalCounts[$pid]['likes'] ?? 0,
                'dislikes' => $globalCounts[$pid]['dislikes'] ?? 0,
                'reports' => $globalCounts[$pid]['reports'] ?? 0,
            ];
        }

        $commData = [];
        foreach ($commentaires as $c) {
            $cid = $c->getIdCommentaire();
            $commData[] = [
                'text' => substr($c->getDescriptionCommentaire() ?? '', 0, 50) . '...',
                'pub' => $c->getPublication() ? $c->getPublication()->getTitrePub() : 'Deleted',
                'author' => $c->getUser() ? ($c->getUser()->getFirstName() . ' ' . $c->getUser()->getLastName()) : '—',
                'date' => $c->getCreatedAt() ? $c->getCreatedAt()->format('Y-m-d H:i') : '—',
                'id' => $cid,
                'fullText' => $c->getDescriptionCommentaire(),
                'visibility' => $c->isVisibility() ? 'true' : 'false',
                'image' => $c->getImageCommentaire(),
                'token' => $csrfTokenManager->getToken('delete_comment' . $cid)->getValue(),
                'likes' => $commentCounts[$cid]['likes'] ?? 0,
                'dislikes' => $commentCounts[$cid]['dislikes'] ?? 0,
                'reports' => $commentCounts[$cid]['reports'] ?? 0,
            ];
        }

        return $this->render('admin/Forum/index.html.twig', [
            'publications' => $publications,
            'commentaires' => $commentaires,
            'pubData' => $pubData,
            'commData' => $commData,
            'pubEditForm' => $pubEditForm->createView(),
            'pubAddForm' => $pubAddForm->createView(),
            'commentEditForm' => $commentEditForm->createView()
        ]);
    }

    #[Route('/admin/add', name: 'admin_forum_pub_add', methods: ['POST'])]
    public function adminAdd(Request $request, EntityManagerInterface $em, SluggerInterface $slugger, FormFactoryInterface $formFactory, \App\Service\MailNotificationService $mailNotificationService): JsonResponse
    {
        $publication = new Publication();
        $form = $formFactory->createNamed('publication_add', PublicationType::class, $publication);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if (!$user) {
                $sessionUser = $request->getSession()->get('user');
                if ($sessionUser) {
                    $userId = is_array($sessionUser) ? ($sessionUser['id_user'] ?? $sessionUser['id'] ?? null) : null;
                    if ($userId) {
                        $user = $em->getRepository(User::class)->find($userId);
                    }
                }
            }

            if (!$user) {
                return new JsonResponse(['success' => false, 'message' => 'Unable to identify author. Please log in again.'], 401);
            }

            $publication->setUser($user);

            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $imageFile */
            $imageFile = $form->get('image_pub')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $imageFile->move($this->getParameter('publications_directory'), $newFilename);
                    $publication->setImagePub($newFilename);
                } catch (\Exception $e) {
                }
            }

            if (!$publication->getDateCreationPub()) {
                $publication->setDateCreationPub(new \DateTime());
            }

            $em->persist($publication);
            $em->flush();

            // Send email notification ONLY for Announcements
            if ($publication->getCategoriePub() === 'Announcement') {
                $mailNotificationService->sendNewPublicationNotification($publication);
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Publication created successfully.',
                'publication' => [
                    'id' => $publication->getId(),
                    'title' => $publication->getTitrePub(),
                    'content' => $publication->getDescriptionPub(),
                    'date' => $publication->getDateCreationPub()->format('Y-m-d H:i'),
                    'author' => $user->getEmailUser(),
                    'image' => $publication->getImagePub() ? '/uploads/publications/' . $publication->getImagePub() : null
                ]
            ]);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }
        return new JsonResponse(['success' => false, 'message' => implode(' ', $errors)], 400);
    }

    #[Route('/admin/{id}/edit', name: 'admin_forum_pub_edit', methods: ['POST'])]
    public function adminEdit(int $id, Request $request, PublicationRepository $publicationRepository, EntityManagerInterface $em, SluggerInterface $slugger, FormFactoryInterface $formFactory): JsonResponse
    {
        $publication = $publicationRepository->find($id);
        if (!$publication) {
            return new JsonResponse(['success' => false, 'message' => 'Publication not found.'], 404);
        }

        $form = $formFactory->createNamed('publication_edit', PublicationType::class, $publication);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $imageFile */
            $imageFile = $form->get('image_pub')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $imageFile->move($this->getParameter('publications_directory'), $newFilename);
                    $publication->setImagePub($newFilename);
                } catch (\Exception $e) {
                }
            }
            $em->flush();
            return new JsonResponse([
                'success' => true,
                'message' => 'Publication updated successfully.',
                'publication' => [
                    'id' => $publication->getId(),
                    'title' => $publication->getTitrePub(),
                    'content' => $publication->getDescriptionPub(),
                    'image' => $publication->getImagePub() ? '/uploads/publications/' . $publication->getImagePub() : null
                ]
            ]);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }
        return new JsonResponse(['success' => false, 'message' => implode(' ', $errors)], 400);
    }

    #[Route('/admin/comment/{id}/edit', name: 'admin_forum_comment_edit', methods: ['POST'])]
    public function commentEdit(int $id, Request $request, CommentaireRepository $commentaireRepository, EntityManagerInterface $em, SluggerInterface $slugger, FormFactoryInterface $formFactory): JsonResponse
    {
        $comment = $commentaireRepository->find($id);
        if (!$comment) {
            return new JsonResponse(['success' => false, 'message' => 'Comment not found.'], 404);
        }

        $form = $formFactory->createNamed('comment_edit', CommentaireType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $imageFile */
            $imageFile = $form->get('image_commentaire')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $imageFile->move($this->getParameter('commentaire_images_directory'), $newFilename);
                    $comment->setImageCommentaire($newFilename);
                } catch (\Exception $e) {
                }
            }
            $comment->setUpdatedAt(new \DateTime());
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Comment updated successfully.',
                'comment' => [
                    'id' => $comment->getIdCommentaire(),
                    'content' => $comment->getDescriptionCommentaire(),
                    'image' => $comment->getImageCommentaire() ? '/uploads/comments/' . $comment->getImageCommentaire() : null
                ]
            ]);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }
        return new JsonResponse(['success' => false, 'message' => implode(' ', $errors)], 400);
    }

    #[Route('/new', name: 'app_publication_new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, \App\Service\MailNotificationService $mailNotificationService): JsonResponse
    {
        $publication = new Publication();
        $user = $this->getUser();
        if ($user) {
            $publication->setUser($user);
        }

        $form = $this->createForm(PublicationType::class, $publication);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
                    $publication->setImagePub($newFilename);
                } catch (\Exception $e) {
                }
            }

            $entityManager->persist($publication);
            $entityManager->flush();

            // Send email notification ONLY for Announcements
            if ($publication->getCategoriePub() === 'Announcement') {
                $mailNotificationService->sendNewPublicationNotification($publication);
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Publication created successfully.',
                'publication' => [
                    'id' => $publication->getId(),
                    'title' => $publication->getTitrePub()
                ]
            ]);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }
        return new JsonResponse(['success' => false, 'message' => implode(' ', $errors)], 400);
    }

    #[Route('/{id}/edit', name: 'app_publication_edit', methods: ['POST'])]
    public function edit(Request $request, Publication $publication, EntityManagerInterface $entityManager, SluggerInterface $slugger): JsonResponse
    {
        $form = $this->createForm(PublicationType::class, $publication);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
                    $publication->setImagePub($newFilename);
                } catch (\Exception $e) {
                }
            }

            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Publication updated successfully.',
                'publication' => [
                    'id' => $publication->getId(),
                    'title' => $publication->getTitrePub()
                ]
            ]);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }
        return new JsonResponse(['success' => false, 'message' => implode(' ', $errors)], 400);
    }

    #[Route('/{id}', name: 'app_publication_delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        PublicationRepository $publicationRepository,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager
    ): JsonResponse {
        $publication = $publicationRepository->find($id);
        $userSession = $request->getSession()->get('user');

        if (!$publication || !$userSession) {
            return new JsonResponse(['success' => false, 'message' => 'Publication not found or not logged in'], 404);
        }

        // CSRF Check
        if (!$csrfTokenManager->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('delete_publication' . $publication->getId(), $request->request->get('_token')))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid security token.'], 403);
        }

        // --- Authorization ---
        $currentUserId = null;
        if (is_array($userSession)) {
            $currentUserId = $userSession['id_user'] ?? $userSession['id'] ?? null;
        } elseif (is_object($userSession)) {
            $currentUserId = method_exists($userSession, 'getIdUser') ? $userSession->getIdUser() : (method_exists($userSession, 'getId') ? $userSession->getId() : null);
        }

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
        $isAuthor = ($publication->getUser() && $publication->getUser()->getIdUser() == $currentUserId);
        $isModerator = $currentUserRole && in_array($currentUserRole, $moderatorRoles, true);

        if (!$isAuthor && !$isModerator) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized. Admin or Author access required.'], 403);
        }

        $entityManager->remove($publication);
        $entityManager->flush();

        return new JsonResponse(['success' => true, 'message' => 'Publication deleted successfully.']);
    }
}

