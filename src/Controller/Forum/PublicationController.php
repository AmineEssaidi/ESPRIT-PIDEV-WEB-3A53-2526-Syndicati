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

#[Route('/publication')]
class PublicationController extends AbstractController
{
    #[Route('/', name: 'app_publication_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('frontend_forum');
    }

    #[Route('/admin', name: 'admin_forum')]
    public function adminIndex(PageStatusService $pageStatusService, Request $request, PublicationRepository $publicationRepository, CommentaireRepository $commentaireRepository, FormFactoryInterface $formFactory): Response
    {
        $publications = $publicationRepository->findBy([], ['date_creation_pub' => 'DESC']);
        $commentaires = $commentaireRepository->findBy([], ['created_at' => 'DESC']);

        $pubEditForm = $formFactory->createNamed('publication_edit', PublicationType::class, new Publication());
        $pubAddForm = $formFactory->createNamed('publication_add', PublicationType::class, new Publication(), [
            'action' => $this->generateUrl('admin_forum_pub_add'),
            'method' => 'POST',
        ]);
        $commentEditForm = $formFactory->createNamed('comment_edit', CommentaireType::class, new Commentaire());

        return $this->render('admin/Forum/index.html.twig', [
            'publications' => $publications,
            'commentaires' => $commentaires,
            'pubEditForm' => $pubEditForm->createView(),
            'pubAddForm' => $pubAddForm->createView(),
            'commentEditForm' => $commentEditForm->createView()
        ]);
    }

    #[Route('/admin/add', name: 'admin_forum_pub_add', methods: ['POST'])]
    public function adminAdd(Request $request, EntityManagerInterface $em, SluggerInterface $slugger, FormFactoryInterface $formFactory): JsonResponse
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
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): JsonResponse
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
    public function delete(Request $request, Publication $publication, EntityManagerInterface $entityManager): JsonResponse
    {
        if ($this->isCsrfTokenValid('delete' . $publication->getId(), $request->request->get('_token'))) {
            $entityManager->remove($publication);
            $entityManager->flush();
            return new JsonResponse(['success' => true, 'message' => 'Publication deleted successfully.']);
        }

        return new JsonResponse(['success' => false, 'message' => 'Invalid security token.'], 403);
    }
}

