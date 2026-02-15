<?php

namespace App\Controller\Admin;

use App\Entity\Onboarding\Onboarding;
use App\Entity\Profile\Profile;
use App\Entity\User\User;
use App\Form\Onboarding\OnboardingType;
use App\Form\Profile\ProfileType;
use App\Form\User\UserType;
use App\Repository\Onboarding\OnboardingRepository;
use App\Repository\Profile\ProfileRepository;
use App\Repository\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/admin')]
class UserController extends AbstractController
{
    #[Route('/users', name: 'admin_users')]
    public function users(Request $request, UserRepository $userRepository, ProfileRepository $profileRepository, OnboardingRepository $onboardingRepository): Response
    {
        $users = $userRepository->findBy([], ['created_at' => 'DESC']);
        $profiles = $profileRepository->findBy([], ['id_profile' => 'DESC']);
        $onboardings = $onboardingRepository->findBy([], ['id_onboarding' => 'DESC']);
        $editUser = new User();
        $editForm = $this->createForm(UserType::class, $editUser, ['signup' => false, 'edit' => true]);
        $addUser = new User();
        $addForm = $this->createForm(UserType::class, $addUser, ['signup' => false, 'edit' => false, 'add' => true]);
        $profileEditForm = $this->createForm(ProfileType::class, new Profile());
        $onboardingEditForm = $this->createForm(OnboardingType::class, new Onboarding(), ['admin_edit' => true, 'use_prefs_from_request' => true]);
        return $this->render('admin/Users/index.html.twig', [
            'users' => $users,
            'profiles' => $profiles,
            'onboardings' => $onboardings,
            'editForm' => $editForm->createView(),
            'addForm' => $addForm->createView(),
            'profileEditForm' => $profileEditForm->createView(),
            'onboardingEditForm' => $onboardingEditForm->createView(),
        ]);
    }

    #[Route('/profile/{id}/edit', name: 'admin_profile_edit', methods: ['POST'])]
    public function profileEdit(int $id, Request $request, ProfileRepository $profileRepository, EntityManagerInterface $em, SluggerInterface $slugger): JsonResponse
    {
        $profile = $profileRepository->find($id);
        if (!$profile instanceof Profile) {
            return new JsonResponse(['success' => false, 'message' => 'Profile not found.'], 404);
        }
        $form = $this->createForm(ProfileType::class, $profile);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $avatarFile */
            $avatarFile = $form->get('avatarFile')->getData();
            if ($avatarFile) {
                $originalFilename = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = 'avatar_admin_' . $id . '_' . uniqid() . '.' . $avatarFile->guessExtension();
                $targetDir = $this->getParameter('kernel.project_dir') . '/public/profile_images';

                try {
                    $avatarFile->move($targetDir, $newFilename);
                    $profile->setAvatar('profile_images/' . $newFilename);
                } catch (FileException $e) {
                    return new JsonResponse(['success' => false, 'message' => 'Failed to upload avatar.'], 500);
                }
            }

            $em->flush();
            return new JsonResponse(['success' => true, 'message' => 'Profile updated successfully.']);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }
        return new JsonResponse(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors], 400);
    }

    #[Route('/users/add', name: 'admin_users_add', methods: ['POST'])]
    public function userAdd(Request $request, UserRepository $userRepository, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['signup' => false, 'edit' => false, 'add' => true]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $existing = $userRepository->findOneBy(['email_user' => $user->getEmailUser()]);
            if ($existing) {
                return new JsonResponse(['success' => false, 'message' => 'A user with this email already exists.'], 400);
            }
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPasswordUser($passwordHasher->hashPassword($user, $plainPassword));
            $now = new \DateTime();
            $user->setCreatedAt($now);
            $user->setUpdatedAt($now);
            $em->persist($user);
            $em->flush();
            return new JsonResponse(['success' => true, 'message' => 'User added successfully.']);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }
        return new JsonResponse(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors], 400);
    }

    #[Route('/users/{id}/edit', name: 'admin_users_edit', methods: ['POST'])]
    public function userEdit(int $id, Request $request, UserRepository $userRepository, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $user = $userRepository->find($id);
        if (!$user instanceof User) {
            return new JsonResponse(['success' => false, 'message' => 'User not found.'], 404);
        }
        $form = $this->createForm(UserType::class, $user, ['signup' => false, 'edit' => true]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            // Password Change Logic
            $currentPassword = $form->get('currentPassword')->getData();
            $newPassword = $form->get('newPassword')->getData();

            if ($newPassword) {
                if (!$currentPassword) {
                    return new JsonResponse(['success' => false, 'message' => 'You must provide the current password to change it.'], 400);
                } else {
                    if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                        return new JsonResponse(['success' => false, 'message' => 'Current password is invalid.'], 400);
                    } else {
                        $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
                        $user->setPasswordUser($hashedPassword);
                    }
                }
            }

            $user->setUpdatedAt(new \DateTime());
            $em->flush();
            return new JsonResponse(['success' => true, 'message' => 'User updated successfully.']);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }
        return new JsonResponse(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors], 400);
    }

    #[Route('/users/{id}/delete', name: 'admin_users_delete', methods: ['POST'])]
    public function userDelete(int $id, Request $request, UserRepository $userRepository, EntityManagerInterface $em, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        $token = $request->request->get('_token');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('user_delete', $token ?? ''))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid security token.'], 403);
        }
        $user = $userRepository->find($id);
        if (!$user instanceof User) {
            return new JsonResponse(['success' => false, 'message' => 'User not found.'], 404);
        }
        $em->remove($user);
        $em->flush();
        return new JsonResponse(['success' => true, 'message' => 'User deleted successfully.']);
    }

    #[Route('/onboarding/{id}/edit', name: 'admin_onboarding_edit', methods: ['POST'])]
    public function onboardingEdit(int $id, Request $request, OnboardingRepository $onboardingRepository, EntityManagerInterface $em): JsonResponse
    {
        $onboarding = $onboardingRepository->find($id);
        if (!$onboarding instanceof Onboarding) {
            return new JsonResponse(['success' => false, 'message' => 'Onboarding not found.'], 404);
        }
        $form = $this->createForm(OnboardingType::class, $onboarding, ['admin_edit' => true, 'use_prefs_from_request' => true]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $prefs = $request->request->all('prefs');
            $defaults = [
                'notification_channel' => 'EMAIL',
                'notification_frequency' => 'DAILY_DIGEST',
                'property_type' => 'APARTMENT',
                'occupancy_status' => 'OWNER_OCCUPIED',
                'parking_type' => 'NONE',
                'meeting_participation' => 'HYBRID',
                'document_delivery' => 'DIGITAL',
                'contact_preference' => 'EMAIL',
                'maintenance_priority' => 'FLEXIBLE',
                'community_engagement' => 'MODERATE',
                'payment_method_preference' => 'ONLINE',
                'noise_sensitivity' => 'MODERATE',
                'pets_status' => 'NO_PETS',
                'accessibility_needs' => 'NONE',
            ];
            $prefs = array_merge($defaults, is_array($prefs) ? $prefs : []);
            $prefs['language_preference'] = match ($onboarding->getSelectedLocale()) {
                'en' => 'EN', 'ar' => 'AR', 'fr_ar' => 'FR_AR', default => 'FR',
            };
            $prefs['theme_preference'] = $onboarding->getSelectedTheme() === 'light' ? 'LIGHT' : 'DARK';
            $onboarding->setSelectedPreferences($prefs);
            $onboarding->setUpdatedAt(new \DateTime());
            $em->flush();
            return new JsonResponse(['success' => true, 'message' => 'Onboarding updated successfully.']);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }
        return new JsonResponse(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors], 400);
    }
}
