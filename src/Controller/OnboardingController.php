<?php

namespace App\Controller;

use App\Entity\Onboarding\Onboarding;
use App\Repository\Onboarding\OnboardingRepository;
use App\Repository\User\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class OnboardingController extends AbstractController
{
    #[Route('/onboarding', name: 'onboarding', methods: ['GET', 'POST'])]
    public function onboarding(
        Request $request,
        UserRepository $userRepository,
        OnboardingRepository $onboardingRepository,
        EntityManagerInterface $em,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        $session = $request->getSession();
        if (!$session->get('is_logged_in') || !$session->get('user') || !isset($session->get('user')['id'])) {
            $this->addFlash('danger', 'Please sign in to continue.');
            return $this->redirectToRoute('auth_sign_in');
        }

        $userId = (int) $session->get('user')['id'];
        $user = $userRepository->find($userId);
        if (!$user) {
            $session->remove('is_logged_in');
            $session->remove('user');
            return $this->redirectToRoute('auth_sign_in');
        }

        $onboarding = $onboardingRepository->findOneByUser($user);
        if (!$onboarding) {
            $onboarding = new Onboarding();
            $onboarding->setUser($user);
            $onboarding->setStep(1);
            $onboarding->setStartedAt(new \DateTime());
            $onboarding->setUpdatedAt(new \DateTime());
            $em->persist($onboarding);
            $em->flush();
        }

        if ($onboarding->isCompleted()) {
            return $this->redirectToRoute('main_home');
        }

        if ($request->isMethod('POST')) {
            if (!$csrfTokenManager->isTokenValid(new CsrfToken('onboarding', $request->request->get('_token') ?? ''))) {
                $this->addFlash('danger', 'Invalid request. Please try again.');
                return $this->redirectToRoute('onboarding');
            }
            $step = (int) $request->request->get('step', 1);
            $action = $request->request->get('action', 'next');

            $prefs = $onboarding->getSelectedPreferences() ?? [];

            if ($step === 1) {
                $prefs['language_preference'] = $request->request->get('language_preference', 'FR');
                $prefs['theme_preference'] = $request->request->get('theme_preference', 'DARK');
                $lang = $request->request->get('language_preference', 'FR');
                $localeMap = ['EN' => 'en', 'AR' => 'ar', 'FR_AR' => 'fr_ar'];
                $onboarding->setSelectedLocale($localeMap[$lang] ?? 'fr');
                $theme = $request->request->get('theme_preference', 'DARK');
                $onboarding->setSelectedTheme($theme === 'LIGHT' ? 'light' : 'dark');
            } elseif ($step === 2) {
                $prefs['notification_channel'] = $request->request->get('notification_channel', 'EMAIL');
                $prefs['notification_frequency'] = $request->request->get('notification_frequency', 'DAILY_DIGEST');
            } elseif ($step === 3) {
                $prefs['property_type'] = $request->request->get('property_type', 'APARTMENT');
                $prefs['occupancy_status'] = $request->request->get('occupancy_status', 'OWNER_OCCUPIED');
                $prefs['parking_type'] = $request->request->get('parking_type', 'NONE');
            } elseif ($step === 4) {
                $prefs['meeting_participation'] = $request->request->get('meeting_participation', 'HYBRID');
                $prefs['document_delivery'] = $request->request->get('document_delivery', 'DIGITAL');
                $prefs['contact_preference'] = $request->request->get('contact_preference', 'EMAIL');
            } elseif ($step === 5) {
                $prefs['maintenance_priority'] = $request->request->get('maintenance_priority', 'FLEXIBLE');
                $prefs['community_engagement'] = $request->request->get('community_engagement', 'MODERATE');
            } elseif ($step === 6) {
                $prefs['payment_method_preference'] = $request->request->get('payment_method_preference', 'ONLINE');
                $prefs['noise_sensitivity'] = $request->request->get('noise_sensitivity', 'MODERATE');
                $prefs['pets_status'] = $request->request->get('pets_status', 'NO_PETS');
                $prefs['accessibility_needs'] = $request->request->get('accessibility_needs', 'NONE');
            } elseif ($step === 7) {
                $onboarding->setSuggestions($request->request->get('suggestions'));
            }

            $onboarding->setSelectedPreferences($prefs);
            $onboarding->setUpdatedAt(new \DateTime());

            // Step navigation / completion
            if ($action === 'next' || $action === 'finish') {
                if ($action === 'finish' || $step >= 7) {
                    $onboarding->setCompleted(true);
                    $onboarding->setCompletedAt(new \DateTime());
                    $onboarding->setStep(7);
                } else {
                    $onboarding->setStep($step + 1);
                }
            } elseif ($action === 'prev') {
                $onboarding->setStep(max(1, $step - 1));
            } elseif ($action === 'cancel') {
                // Cancel: save current answers and mark onboarding as completed so overlay won't show again
                $onboarding->setCompleted(true);
                $onboarding->setCompletedAt(new \DateTime());
            }

            $em->flush();

            // AJAX flow: return updated overlay fragment instead of redirect
            if ($request->isXmlHttpRequest()) {
                // If completed (finish or cancel), just signal completion
                if ($onboarding->isCompleted()) {
                    return new JsonResponse([
                        'completed' => true,
                    ]);
                }

                $html = $this->renderView('frontend/onboarding/_overlay_content.html.twig', [
                    'onboarding' => $onboarding,
                    'prefs' => $onboarding->getSelectedPreferences() ?? [],
                    'user' => $user,
                ]);

                return new JsonResponse([
                    'completed' => false,
                    'html' => $html,
                ]);
            }

            // Non-AJAX flow (fallback): redirect as before
            if ($onboarding->isCompleted()) {
                $this->addFlash('success', 'Welcome! Your preferences have been saved.');
                return $this->redirectToRoute('main_home');
            }

            return $this->redirectToRoute('onboarding');
        }

        return $this->render('frontend/home/main-home.html.twig', [
            'onboarding' => $onboarding,
            'prefs' => $onboarding->getSelectedPreferences() ?? [],
            'user' => $user,
            'show_onboarding_overlay' => true,
        ]);
    }

    #[Route('/onboarding/overlay-save', name: 'onboarding_overlay_save', methods: ['GET', 'POST'])]
    public function overlaySave(
        Request $request,
        UserRepository $userRepository,
        OnboardingRepository $onboardingRepository,
        EntityManagerInterface $em,
        CsrfTokenManagerInterface $csrfTokenManager
    ): JsonResponse {
        // If someone hits this URL with GET (e.g. via mis-click or prefetch),
        // just send them back to main home instead of throwing 405.
        if ($request->isMethod('GET')) {
            return new JsonResponse([
                'redirect' => $this->generateUrl('main_home'),
            ], 200);
        }
        $session = $request->getSession();
        if (!$session->get('is_logged_in') || !$session->get('user') || !isset($session->get('user')['id'])) {
            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        $userId = (int) $session->get('user')['id'];
        $user = $userRepository->find($userId);
        if (!$user) {
            return new JsonResponse(['error' => 'user_not_found'], 404);
        }

        if (!$csrfTokenManager->isTokenValid(new CsrfToken('onboarding', $request->request->get('_token') ?? ''))) {
            return new JsonResponse(['error' => 'invalid_csrf'], 400);
        }

        $onboarding = $onboardingRepository->findOneByUser($user);
        if (!$onboarding instanceof Onboarding) {
            $onboarding = new Onboarding();
            $onboarding->setUser($user);
            $onboarding->setStep(1);
            $onboarding->setStartedAt(new \DateTime());
        }

        $prefs = $onboarding->getSelectedPreferences() ?? [];

        // Only update fields for steps the user has actually reached
        $currentStep = (int) $request->request->get('current_step', 1);

        if ($currentStep >= 1) {
            $prefs['language_preference'] = $request->request->get('language_preference', $prefs['language_preference'] ?? 'FR');
            $prefs['theme_preference']    = $request->request->get('theme_preference', $prefs['theme_preference'] ?? 'DARK');
        }
        if ($currentStep >= 2) {
            $prefs['notification_channel']   = $request->request->get('notification_channel', $prefs['notification_channel'] ?? 'EMAIL');
            $prefs['notification_frequency'] = $request->request->get('notification_frequency', $prefs['notification_frequency'] ?? 'DAILY_DIGEST');
        }
        if ($currentStep >= 3) {
            $prefs['property_type']    = $request->request->get('property_type', $prefs['property_type'] ?? 'APARTMENT');
            $prefs['occupancy_status'] = $request->request->get('occupancy_status', $prefs['occupancy_status'] ?? 'OWNER_OCCUPIED');
            $prefs['parking_type']     = $request->request->get('parking_type', $prefs['parking_type'] ?? 'NONE');
        }
        if ($currentStep >= 4) {
            $prefs['meeting_participation'] = $request->request->get('meeting_participation', $prefs['meeting_participation'] ?? 'HYBRID');
            $prefs['document_delivery']     = $request->request->get('document_delivery', $prefs['document_delivery'] ?? 'DIGITAL');
            $prefs['contact_preference']    = $request->request->get('contact_preference', $prefs['contact_preference'] ?? 'EMAIL');
        }
        if ($currentStep >= 5) {
            $prefs['maintenance_priority'] = $request->request->get('maintenance_priority', $prefs['maintenance_priority'] ?? 'FLEXIBLE');
            $prefs['community_engagement'] = $request->request->get('community_engagement', $prefs['community_engagement'] ?? 'MODERATE');
        }
        if ($currentStep >= 6) {
            $prefs['payment_method_preference'] = $request->request->get('payment_method_preference', $prefs['payment_method_preference'] ?? 'ONLINE');
            $prefs['noise_sensitivity']         = $request->request->get('noise_sensitivity', $prefs['noise_sensitivity'] ?? 'MODERATE');
            $prefs['pets_status']               = $request->request->get('pets_status', $prefs['pets_status'] ?? 'NO_PETS');
            $prefs['accessibility_needs']       = $request->request->get('accessibility_needs', $prefs['accessibility_needs'] ?? 'NONE');
        }
        if ($currentStep >= 7) {
            $onboarding->setSuggestions($request->request->get('suggestions', $onboarding->getSuggestions()));
        }

        $onboarding->setSelectedPreferences($prefs);
        $onboarding->setSelectedLocale(match ($prefs['language_preference']) {
            'EN' => 'en',
            'AR' => 'ar',
            'FR_AR' => 'fr_ar',
            default => 'fr',
        });
        $onboarding->setSelectedTheme($prefs['theme_preference'] === 'LIGHT' ? 'light' : 'dark');

        // Save current step + completed flag
        $onboarding->setStep($currentStep > 0 ? $currentStep : 7);
        $onboarding->setUpdatedAt(new \DateTime());
        $onboarding->setCompleted(true);
        $onboarding->setCompletedAt(new \DateTime());

        // Ensure the overlay never shows again in this browser session
        $session->set('onboarding_overlay_shown', true);

        $em->persist($onboarding);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }
}
