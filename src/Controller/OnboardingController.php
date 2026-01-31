<?php

namespace App\Controller;

use App\Entity\Onboarding\Onboarding;
use App\Repository\Onboarding\OnboardingRepository;
use App\Repository\User\UserRepository;
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

            if ($action === 'next' || $action === 'finish') {
                if ($action === 'finish' || $step >= 7) {
                    $onboarding->setCompleted(true);
                    $onboarding->setCompletedAt(new \DateTime());
                    $onboarding->setStep(7);
                    $em->flush();
                    $this->addFlash('success', 'Welcome! Your preferences have been saved.');
                    return $this->redirectToRoute('main_home');
                }
                $onboarding->setStep($step + 1);
            } elseif ($action === 'prev') {
                $onboarding->setStep(max(1, $step - 1));
            }

            $em->flush();
            return $this->redirectToRoute('onboarding');
        }

        return $this->render('frontend/onboarding/onboarding.html.twig', [
            'onboarding' => $onboarding,
            'prefs' => $onboarding->getSelectedPreferences() ?? [],
            'user' => $user,
        ]);
    }
}
