<?php

namespace App\Controller;

use App\Entity\Profile\Profile;
use App\Repository\Profile\ProfileRepository;
use App\Repository\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\FormErrorHelperTrait;

class SettingsController extends AbstractController
{
    use FormErrorHelperTrait;
    #[Route('/settings', name: 'frontend_settings', methods: ['GET'])]
    public function index(Request $request, ProfileRepository $profileRepository, UserRepository $userRepository): Response
    {
        $session = $request->getSession();
        $defaults = [
            'theme' => 'dark',
            'accent-gradient' => 'linear-gradient(135deg, #6c5ce7, #8b5cf6, #06b6d4)',
            'accent-color' => '#6c5ce7',
            'lang' => 'fr'
        ];

        $settings = $defaults;
        if ($session->get('is_logged_in') && $session->get('user') && isset($session->get('user')['id'])) {
            $userId = (int) $session->get('user')['id'];
            $user = $userRepository->find($userId);
            if ($user) {
                $profile = $profileRepository->findOneByUser($user);
                if ($profile) {
                    $settings = array_merge($defaults, $profile->getSettings());
                }
            }
        }

        return $this->render('frontend/settings/settings.html.twig', [
            'settings' => $settings,
            'is_logged_in' => $session->get('is_logged_in', false)
        ]);
    }

    #[Route('/settings/update', name: 'frontend_settings_update', methods: ['POST'])]
    public function update(Request $request, ProfileRepository $profileRepository, UserRepository $userRepository, EntityManagerInterface $em): JsonResponse
    {
        $session = $request->getSession();
        if (!$session->get('is_logged_in') || !$session->get('user') || !isset($session->get('user')['id'])) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $userId = (int) $session->get('user')['id'];
        $user = $userRepository->find($userId);
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        $profile = $profileRepository->findOneByUser($user);
        if (!$profile) {
            $profile = new Profile();
            $profile->setUser($user);
            $em->persist($profile);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Invalid data'], 400);
        }

        $currentSettings = $profile->getSettings();
        $newSettings = array_merge($currentSettings, $data);
        $profile->setSettings($newSettings);

        // Sync legacy fields
        if (isset($data['theme'])) {
            $profile->setTheme($data['theme'] === 'light' ? 1 : 0);
        }
        if (isset($data['lang'])) {
            $profile->setLocale($data['lang']);
        }

        $em->flush();

        // Update session
        $userData = $session->get('user', []);
        $userData['settings'] = $newSettings;
        $session->set('user', $userData);

        return new JsonResponse(['success' => true, 'settings' => (object) $newSettings]);
    }
}
