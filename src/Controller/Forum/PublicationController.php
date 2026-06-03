<?php

namespace App\Controller\Forum;

use App\Entity\Forum\Publication;
use App\Form\Forum\PublicationType;
use App\Repository\Forum\PublicationRepository;
use App\Entity\Forum\Commentaire;
use App\Form\Forum\CommentaireType;
use App\Repository\Forum\CommentaireRepository;
use App\Entity\Forum\Reaction;
use App\Form\Forum\ReactionType;
use App\Repository\Forum\ReactionRepository;
use App\Service\PageStatusService;
use App\Service\Media\ImageKitStorageService;
use App\Service\Media\ImagePathResolver;
use App\Service\Forum\DiscordWebhookService;
use App\Message\Forum\NotifyAnnouncementMessage;
use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use App\Service\UserStanding\UserStandingService;

use App\Service\FormErrorHelperTrait;

#[Route('/publication')]
class PublicationController extends AbstractController
{
    use FormErrorHelperTrait;
    public function __construct(
        private readonly UserStandingService $userStandingService,
        private readonly \App\Service\User\NotificationService $notifService,
        private readonly ImageKitStorageService $imageStorage,
        private readonly ImagePathResolver $imagePathResolver,
        private readonly DiscordWebhookService $discordWebhook
    ) {
    }
    #[Route('/', name: 'app_publication_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('frontend_forum');
    }

    #[Route('/admin', name: 'admin_forum')]
    public function adminIndex(PageStatusService $pageStatusService, Request $request, PublicationRepository $publicationRepository, CommentaireRepository $commentaireRepository, ReactionRepository $reactionRepository, FormFactoryInterface $formFactory): Response
    {
        $publications = $publicationRepository->findBy([], ['date_creation_pub' => 'DESC']);
        $commentaires = $commentaireRepository->findBy([], ['created_at' => 'DESC']);
        $reactions = $reactionRepository->findBy([], ['created_at' => 'DESC']);

        $pubEditForm = $formFactory->createNamed('publication_edit', PublicationType::class, new Publication());
        $pubAddForm = $formFactory->createNamed('publication_add', PublicationType::class, new Publication(), [
            'action' => $this->generateUrl('admin_forum_pub_add'),
            'method' => 'POST',
        ]);
        $commentEditForm = $formFactory->createNamed('comment_edit', CommentaireType::class, new Commentaire());
        $reactionEditForm = $formFactory->createNamed('reaction_edit', ReactionType::class, new Reaction());

        return $this->render('admin/Forum/index.html.twig', [
            'publications' => $publications,
            'commentaires' => $commentaires,
            'reactions' => $reactions,
            'pubEditForm' => $pubEditForm->createView(),
            'pubAddForm' => $pubAddForm->createView(),
            'commentEditForm' => $commentEditForm->createView(),
            'reactionEditForm' => $reactionEditForm->createView()
        ]);
    }

    #[Route('/admin/add', name: 'admin_forum_pub_add', methods: ['POST'])]
    public function adminAdd(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        FormFactoryInterface $formFactory,
        \App\Service\Forum\ForumNotificationService $notificationService,
        MessageBusInterface $messageBus
    ): JsonResponse {
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
                    $publication->setImagePub($this->imageStorage->storeUploadedFile(
                        $imageFile,
                        $this->getParameter('publications_directory'),
                        'forum_images',
                        '/syndicati/forum_images',
                        $newFilename
                    ));
                } catch (\Exception $e) {
                }
            }

            if (!$publication->getDateCreationPub()) {
                $publication->setDateCreationPub(new \DateTime());
            }

            $em->persist($publication);
            $em->flush();

            // Notify user of success
            $this->notifService->notify(
                $user,
                'SUCCESS',
                'PUBLICATION',
                $publication->getId(),
                'Publication créée',
                'Votre publication "' . $publication->getTitrePub() . '" est maintenant en ligne.'
            );

            $this->userStandingService->awardForAction($user, 'FORUM_POST');

            // Notify if Announcement
            if ($publication->getCategoriePub() === 'Announcement') {
                $messageBus->dispatch(new NotifyAnnouncementMessage($publication->getId()));
            }
            $this->discordWebhook->announceJeuxVideo($publication, false);

            return new JsonResponse([
                'success' => true,
                'message' => 'Publication created successfully.',
                'publication' => [
                    'id' => $publication->getId(),
                    'title' => $publication->getTitrePub(),
                    'content' => $publication->getDescriptionPub(),
                    'date' => $publication->getDateCreationPub()->format('Y-m-d H:i'),
                    'author' => $user->getEmailUser(),
                    'image' => $this->imagePathResolver->publicUrl($publication->getImagePub(), 'forum_images')
                ]
            ]);
        }

        if ($form->isSubmitted()) {
            return new JsonResponse(['success' => false, 'errors' => $this->getFormErrors($form)], 400);
        }

        return new JsonResponse(['success' => false, 'message' => 'Form submission failed.'], 400);
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
                    $publication->setImagePub($this->imageStorage->storeUploadedFile(
                        $imageFile,
                        $this->getParameter('publications_directory'),
                        'forum_images',
                        '/syndicati/forum_images',
                        $newFilename
                    ));
                } catch (\Exception $e) {
                }
            }
            $em->flush();
            $this->discordWebhook->announceJeuxVideo($publication, true);

            $user = $this->getUser();
            if ($user) {
                $this->notifService->notify(
                    $user,
                    'SUCCESS',
                    'PUBLICATION',
                    $publication->getId(),
                    'Publication mise à jour',
                    'Les modifications ont été enregistrées avec succès.'
                );
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Publication updated successfully.',
                'publication' => [
                    'id' => $publication->getId(),
                    'title' => $publication->getTitrePub(),
                    'content' => $publication->getDescriptionPub(),
                    'image' => $this->imagePathResolver->publicUrl($publication->getImagePub(), 'forum_images')
                ]
            ]);
        }
        return new JsonResponse(['success' => false, 'errors' => $this->getFormErrors($form)], 400);
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
                    $comment->setImageCommentaire($this->imageStorage->storeUploadedFile(
                        $imageFile,
                        $this->getParameter('commentaire_images_directory'),
                        'commentaire_images',
                        '/syndicati/commentaire_images',
                        $newFilename
                    ));
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
                    'image' => $this->imagePathResolver->publicUrl($comment->getImageCommentaire(), 'commentaire_images')
                ]
            ]);
        }
        return new JsonResponse(['success' => false, 'errors' => $this->getFormErrors($form)], 400);
    }

    #[Route('/admin/reaction/{id}/edit', name: 'admin_forum_reaction_edit', methods: ['POST'])]
    public function reactionEdit(int $id, Request $request, ReactionRepository $reactionRepository, EntityManagerInterface $em, FormFactoryInterface $formFactory): JsonResponse
    {
        $reaction = $reactionRepository->find($id);
        if (!$reaction) {
            return new JsonResponse(['success' => false, 'message' => 'Reaction not found.'], 404);
        }

        $form = $formFactory->createNamed('reaction_edit', ReactionType::class, $reaction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Reaction updated successfully.',
                'reaction' => [
                    'id' => $reaction->getIdReaction(),
                    'kind' => $reaction->getKind(),
                    'emoji' => $reaction->getEmoji(),
                    'reason' => $reaction->getReportReason()
                ]
            ]);
        }
        return new JsonResponse(['success' => false, 'errors' => $this->getFormErrors($form)], 400);
    }

    #[Route('/admin/reaction/{id}/delete', name: 'admin_forum_reaction_delete', methods: ['POST'])]
    public function reactionDelete(int $id, Request $request, ReactionRepository $reactionRepository, EntityManagerInterface $em): JsonResponse
    {
        $reaction = $reactionRepository->find($id);
        if (!$reaction) {
            return new JsonResponse(['success' => false, 'message' => 'Reaction not found.'], 404);
        }

        // CSRF check
        if (!$this->isCsrfTokenValid('delete' . $reaction->getIdReaction(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid security token.'], 403);
        }

        $em->remove($reaction);
        $em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Reaction deleted successfully.']);
    }

    #[Route('/new', name: 'app_publication_new', methods: ['POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        \App\Service\Forum\ForumNotificationService $notificationService,
        MessageBusInterface $messageBus
    ): JsonResponse {
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
                    $publication->setImagePub($this->imageStorage->storeUploadedFile(
                        $imageFile,
                        $this->getParameter('publications_directory'),
                        'forum_images',
                        '/syndicati/forum_images',
                        $newFilename
                    ));
                } catch (\Exception $e) {
                }
            }

            $entityManager->persist($publication);
            $entityManager->flush();

            if ($user instanceof User) {
                $this->notifService->notify(
                    $user,
                    'SUCCESS',
                    'PUBLICATION',
                    $publication->getId(),
                    'Publication créée',
                    'Votre publication a été publiée avec succès.'
                );
            }

            if ($user instanceof User) {
                $this->userStandingService->awardForAction($user, 'FORUM_POST');
            }

            // Notify if Announcement
            if ($publication->getCategoriePub() === 'Announcement') {
                $messageBus->dispatch(new NotifyAnnouncementMessage($publication->getId()));
            }
            $this->discordWebhook->announceJeuxVideo($publication, false);

            return new JsonResponse([
                'success' => true,
                'message' => 'Publication created successfully.',
                'publication' => [
                    'id' => $publication->getId(),
                    'title' => $publication->getTitrePub()
                ]
            ]);
        }
        return new JsonResponse(['success' => false, 'errors' => $this->getFormErrors($form)], 400);
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
                    $publication->setImagePub($this->imageStorage->storeUploadedFile(
                        $imageFile,
                        $this->getParameter('publications_directory'),
                        'forum_images',
                        '/syndicati/forum_images',
                        $newFilename
                    ));
                } catch (\Exception $e) {
                }
            }

            $entityManager->flush();
            $this->discordWebhook->announceJeuxVideo($publication, true);

            return new JsonResponse([
                'success' => true,
                'message' => 'Publication updated successfully.',
                'publication' => [
                    'id' => $publication->getId(),
                    'title' => $publication->getTitrePub()
                ]
            ]);
        }
        return new JsonResponse(['success' => false, 'errors' => $this->getFormErrors($form)], 400);
    }

    #[Route('/{id}', name: 'app_publication_delete', methods: ['POST'])]
    public function delete(Request $request, Publication $publication, EntityManagerInterface $entityManager): JsonResponse
    {
        if ($this->isCsrfTokenValid('delete' . $publication->getId(), $request->request->get('_token'))) {
            $entityManager->remove($publication);
            $entityManager->flush();

            $user = $this->getUser();
            if ($user) {
                $this->notifService->notify(
                    $user,
                    'SUCCESS',
                    'PUBLICATION',
                    $publication->getId(),
                    'Publication supprimée',
                    'La publication a été retirée.'
                );
            }
            return new JsonResponse(['success' => true, 'message' => 'Publication deleted successfully.']);
        }

        return new JsonResponse(['success' => false, 'message' => 'Invalid security token.'], 403);
    }
}

