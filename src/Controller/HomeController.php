<?php

namespace App\Controller;

use App\Entity\Onboarding\Onboarding;
use App\Repository\Onboarding\OnboardingRepository;
use App\Repository\User\UserRepository;
use App\Service\PageStatusService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly CacheInterface $cache
    ) {
    }

    #[Route('/', name: 'main_home')]
    public function mainHome(PageStatusService $pageStatusService, Request $request, UserRepository $userRepository, OnboardingRepository $onboardingRepository, EntityManagerInterface $em): Response
    {
        // Check if main home is offline
        if (!$pageStatusService->isPageOnline('main_home')) {
            return $this->redirectToRoute('maintenance_with_page', ['pageId' => 'main_home']);
        }

        $session = $request->getSession();
        $user = null;
        $onboarding = null;
        $showOverlay = false;
        $prefs = [];

        if ($session->get('is_logged_in') && $session->get('user') && isset($session->get('user')['id'])) {
            $user = $userRepository->find((int) $session->get('user')['id']);
            if ($user) {
                $onboarding = $onboardingRepository->findOneByUser($user);
                if ($onboarding === null) {
                    // First time we ever see this user on main home: create onboarding record
                    $onboarding = new Onboarding();
                    $onboarding->setUser($user);
                    $onboarding->setStep(1);
                    $onboarding->setStartedAt(new \DateTime());
                    $onboarding->setUpdatedAt(new \DateTime());
                    $em->persist($onboarding);
                    $em->flush();
                }

                // Show overlay only once per browser session, on the first visit after signup
                $alreadyShownThisSession = $session->get('onboarding_overlay_shown', false);
                if (!$onboarding->isCompleted() && !$alreadyShownThisSession) {
                    $showOverlay = true;
                    $prefs = $onboarding->getSelectedPreferences() ?? [];
                    $session->set('onboarding_overlay_shown', true);
                }
            }
        }

        $response = $this->render('frontend/home/main-home.html.twig', [
            'user' => $user,
            'onboarding' => $onboarding,
            'prefs' => $prefs,
            'show_onboarding_overlay' => $showOverlay,
        ]);

        // Ajouter des en-têtes de cache HTTP pour de meilleures performances
        $response->setPublic();
        $response->setMaxAge(300); // 5 minutes
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->setEtag(md5($response->getContent()));

        return $response;
    }

    #[Route('/our-team', name: 'frontend_our_team')]
    public function ourTeam(): Response
    {
        $response = $this->render('frontend/about/our-team.html.twig');

        // Contenu statique, mise en cache plus longue
        $response->setPublic();
        $response->setMaxAge(3600); // 1 hour
        $response->setEtag(md5($response->getContent()));

        return $response;
    }

    #[Route('/contact', name: 'frontend_contact')]
    public function contact(): Response
    {
        $response = $this->render('frontend/about/contact.html.twig');

        // Static content, cache longer
        $response->setPublic();
        $response->setMaxAge(3600); // 1 hour
        $response->setEtag(md5($response->getContent()));

        return $response;
    }
}
