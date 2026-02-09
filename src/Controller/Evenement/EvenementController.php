<?php

namespace App\Controller\Evenement;

use App\Entity\Evenement\Evenement;
use App\Form\Evenement\EvenementType;
use App\Repository\Evenement\EvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/evenement')]
class EvenementController extends AbstractController
{
    #[Route('/', name: 'app_evenement_index', methods: ['GET', 'POST'])]
    public function index(Request $request, EvenementRepository $evenementRepository, \App\Repository\User\UserRepository $userRepository, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get user from Security or Session fallback (inside submit)
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

            if ($user) {
                $evenement->setUser($user);
            }

            $imageFile = $form->get('image_event')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('event_images_directory'),
                        $newFilename
                    );
                } catch (\Exception $e) {
                }

                $evenement->setImageEvent($newFilename);
            }

            $entityManager->persist($evenement);
            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => true, 'message' => 'Event created successfully!']);
            }

            return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($form->isSubmitted() && !$form->isValid() && $request->isXmlHttpRequest()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            return $this->json(['success' => false, 'errors' => $errors], 400);
        }

        // Check for existing participations
        $participatedEventIds = [];
        $userForCheck = $this->getUser();
        if (!$userForCheck) {
            $sessionUser = $request->getSession()->get('user');
            if ($sessionUser) {
                $userId = is_array($sessionUser) ? ($sessionUser['id'] ?? $sessionUser['id_user'] ?? null) : null;
                if ($userId)
                    $userForCheck = $userRepository->find($userId);
            }
        }

        if ($userForCheck) {
            $participatedEventIds = $entityManager->createQueryBuilder()
                ->select('e.id')
                ->from(\App\Entity\Evenement\Participation::class, 'p')
                ->join('p.evenement', 'e')
                ->where('p.user = :user')
                ->andWhere('p.statut_participation != :status')
                ->setParameter('user', $userForCheck)
                ->setParameter('status', 'annule')
                ->getQuery()
                ->getSingleColumnResult();
        }

        /** @var \App\Entity\User\User|null $user */
        $user = $userForCheck;
        $currentUserId = ($user instanceof \App\Entity\User\User) ? $user->getIdUser() : null;
        $currentUserRole = ($user instanceof \App\Entity\User\User) ? $user->getRoleUser() : null;

        return $this->render('frontend/evenement/index.html.twig', [
            'evenements' => $evenementRepository->findAllWithUser(),
            'form' => $form->createView(),
            'participationForm' => $this->createForm(\App\Form\Evenement\ParticipationType::class)->createView(),
            'participatedEventIds' => $participatedEventIds,
            'currentUserId' => $currentUserId,
            'currentUserRole' => $currentUserRole,
        ]);
    }

    #[Route('/new', name: 'app_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $evenement = new Evenement();
        $user = $this->getUser();
        if ($user) {
            $evenement->setUser($user);
        }

        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $isAjax = $request->isXmlHttpRequest();
            if ($form->isValid()) {
                /** @var UploadedFile $imageFile */
                $imageFile = $form->get('image_event')->getData();

                if ($imageFile) {
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                    try {
                        $imageFile->move(
                            $this->getParameter('event_images_directory'),
                            $newFilename
                        );
                    } catch (\Exception $e) {
                        // Handle exception
                        if ($isAjax)
                            return $this->json(['success' => false, 'message' => 'Image upload failed.'], 500);
                    }

                    $evenement->setImageEvent($newFilename);
                }

                $entityManager->persist($evenement);
                $entityManager->flush();

                if ($isAjax) {
                    return $this->json(['success' => true, 'message' => 'Event created successfully!']);
                }

                return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
            } else {
                if ($isAjax) {
                    $errors = [];
                    foreach ($form->getErrors(true) as $error) {
                        $errors[] = $error->getMessage();
                    }
                    return $this->json(['success' => false, 'errors' => $errors], 400);
                }
            }
        }

        return $this->redirectToRoute('app_evenement_index');
    }

    #[Route('/{id}', name: 'app_evenement_show', methods: ['GET'])]
    public function show(Evenement $evenement): Response
    {
        return $this->redirectToRoute('app_evenement_index');
    }

    #[Route('/{id}/edit', name: 'app_evenement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Evenement $evenement, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $isAjax = $request->isXmlHttpRequest();
            if ($form->isValid()) {
                /** @var UploadedFile $imageFile */
                $imageFile = $form->get('image_event')->getData();

                if ($imageFile) {
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                    try {
                        $imageFile->move(
                            $this->getParameter('event_images_directory'),
                            $newFilename
                        );
                    } catch (\Exception $e) {
                        // Handle exception
                        if ($isAjax)
                            return $this->json(['success' => false, 'message' => 'Image upload failed.'], 500);
                    }

                    $evenement->setImageEvent($newFilename);
                }

                $entityManager->flush();

                if ($isAjax) {
                    return $this->json(['success' => true, 'message' => 'Event updated successfully!']);
                }

                $this->addFlash('success', 'Event updated successfully!');
                return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
            } else {
                if ($isAjax) {
                    $errors = [];
                    foreach ($form->getErrors(true) as $error) {
                        $errors[] = $error->getMessage();
                    }
                    return $this->json(['success' => false, 'errors' => $errors], 400);
                }
                foreach ($form->getErrors(true) as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }

        return $this->redirectToRoute('app_evenement_index');
    }

    #[Route('/{id}', name: 'app_evenement_delete', methods: ['POST'])]
    public function delete(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $evenement->getId(), $request->request->get('_token'))) {
            $entityManager->remove($evenement);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
    }
}
