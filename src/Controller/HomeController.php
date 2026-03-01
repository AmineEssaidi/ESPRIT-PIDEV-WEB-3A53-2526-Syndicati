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

use App\Repository\Residence\AppartementRepository;
use App\Repository\Residence\ResidenceRepository;
use App\Repository\Evenement\EvenementRepository;


class HomeController extends AbstractController
{
    public function __construct(
        private readonly CacheInterface $cache
    ) {
    }

    #[Route('/', name: 'main_home')]
    public function mainHome(PageStatusService $pageStatusService, Request $request, UserRepository $userRepository, 
    AppartementRepository $AppartementRepository, ResidenceRepository $ResidenceRepository,
    EvenementRepository $EvenementRepository,
    OnboardingRepository $onboardingRepository, EntityManagerInterface $em): Response
    {
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
                    $onboarding = new Onboarding();
                    $onboarding->setUser($user);
                    $onboarding->setStep(1);
                    $onboarding->setStartedAt(new \DateTime());
                    $onboarding->setUpdatedAt(new \DateTime());
                    $em->persist($onboarding);
                    $em->flush();
                }

                $alreadyShownThisSession = $session->get('onboarding_overlay_shown', false);
                if (!$onboarding->isCompleted() && !$alreadyShownThisSession) {
                    $showOverlay = true;
                    $prefs = $onboarding->getSelectedPreferences() ?? [];
                    $session->set('onboarding_overlay_shown', true);
                }
            }
        }

        $n_appartements=$AppartementRepository->NTotalAppartements();
        $n_residences=$ResidenceRepository->NTotalResidences();
        $n_users=$userRepository->NTotalUsers();
        $n_events=$EvenementRepository->NTotalEvenements();

        $nouvelles_r=$ResidenceRepository->NouvellesResidences();
        $evennements=$EvenementRepository->EvennementsProchains();

        $response = $this->render('frontend/home/main-home.html.twig', [
            'user' => $user,
            'onboarding' => $onboarding,
            'prefs' => $prefs,
            'show_onboarding_overlay' => $showOverlay,
            'n_appartements'=>$n_appartements,
            'n_residences'=>$n_residences,
            'n_users'=>$n_users,
            'n_evennements'=> $n_events,
            'nouvelles_r'=>$nouvelles_r,
            'evennements'=>$evennements,
        ]);

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
