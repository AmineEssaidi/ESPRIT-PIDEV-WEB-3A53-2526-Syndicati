<?php

namespace App\Controller;

use App\Controller\Concerns\SessionUserAwareTrait;
use App\Entity\Onboarding\Onboarding;
use App\Repository\Onboarding\OnboardingRepository;
use App\Repository\Evenement\EvenementRepository;
use App\Repository\Forum\PublicationRepository;
use App\Repository\Residence\AppartementRepository;
use App\Repository\Residence\ResidenceRepository;
use App\Repository\User\UserRepository;
use App\Service\PageStatusService;
use App\Service\UserStanding\UserStandingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;

class HomeController extends AbstractController
{
    use SessionUserAwareTrait;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly UserStandingService $userStandingService
    ) {
    }

    #[Route('/', name: 'main_home')]
    public function mainHome(
        PageStatusService $pageStatusService,
        Request $request,
        UserRepository $userRepository,
        OnboardingRepository $onboardingRepository,
        ResidenceRepository $residenceRepository,
        AppartementRepository $appartementRepository,
        EvenementRepository $evenementRepository,
        PublicationRepository $publicationRepository,
        EntityManagerInterface $em
    ): Response {
        // Check if main home is offline
        if (!$pageStatusService->isPageOnline('main_home')) {
            return $this->redirectToRoute('maintenance_with_page', ['pageId' => 'main_home']);
        }

        $session = $request->getSession();
        $user = null;
        $onboarding = null;
        $showOverlay = false;
        $prefs = [];

        $userId = $this->getSessionUserId($session);
        if ($session->get('is_logged_in') && $userId !== null) {
            $user = $userRepository->find($userId);

            if ($user) {
                $this->userStandingService->updateHeartbeat($user, $request);

                // Cache onboarding status in session to avoid database hit on every request
                $onboardingData = $session->get('onboarding_cache');
                if (!$onboardingData || $onboardingData['user_id'] !== $userId) {
                    $onboarding = $onboardingRepository->findOneByUser($user);
                    if ($onboarding === null) {
                        $onboarding = new Onboarding();
                        $onboarding->setUser($user);
                        $onboarding->setStep(1);
                        $onboarding->setStartedAt(new \DateTime());
                        $onboarding->setUpdatedAt(new \DateTime());
                        $em->persist($onboarding);
                        $em->flush();
                    }
                    $session->set('onboarding_cache', [
                        'user_id' => $userId,
                        'is_completed' => $onboarding->isCompleted(),
                        'prefs' => $onboarding->getSelectedPreferences() ?? []
                    ]);
                }

                $cachedOnboarding = $session->get('onboarding_cache');
                $alreadyShownThisSession = $session->get('onboarding_overlay_shown', false);

                if (!$cachedOnboarding['is_completed'] && !$alreadyShownThisSession) {
                    $showOverlay = true;
                    $prefs = $cachedOnboarding['prefs'];
                    $session->set('onboarding_overlay_shown', true);
                }
            }
        }

        $stats = $this->cache->get('home_page_stats', function (\Symfony\Contracts\Cache\ItemInterface $item) use ($userRepository, $residenceRepository, $appartementRepository, $evenementRepository) {
            $item->expiresAfter(60);
            return [
                'residents_count' => $userRepository->count([]),
                'residences_count' => $residenceRepository->count([]),
                'apartments_count' => $appartementRepository->count([]),
                'events_count' => $evenementRepository->count([]),
            ];
        });

        $featuredResidences = $residenceRepository->findLatestForHome(4);
        $upcomingEvents = $evenementRepository->findUpcomingForHome(3);
        $latestForum = $publicationRepository->findLatestWithProfiles(3, true);

        $response = $this->render('frontend/home/main-home.html.twig', [
            'user' => $user,
            'onboarding' => (object) $session->get('onboarding_cache'),
            'prefs' => $prefs,
            'show_onboarding_overlay' => $showOverlay,
            'stats' => $stats,
            'featuredResidences' => $featuredResidences,
            'upcomingEvents' => $upcomingEvents,
            'latestForum' => $latestForum,
        ]);

        // Ajouter des en-têtes de cache HTTP pour de meilleures performances
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-cache', true);
        $response->headers->addCacheControlDirective('must-revalidate', true);

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
