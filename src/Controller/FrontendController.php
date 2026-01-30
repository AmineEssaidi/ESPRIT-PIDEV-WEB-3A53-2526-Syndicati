<?php

namespace App\Controller;

use App\Repository\User\UserRepository;
use App\Service\PageStatusService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class FrontendController extends AbstractController
{
    public function __construct(
        private readonly CacheInterface $cache
    ) {}

    #[Route('/', name: 'main_home')]
    public function mainHome(PageStatusService $pageStatusService, Request $request): Response
    {
        // Check if main home is offline
        if (!$pageStatusService->isPageOnline('main_home')) {
            return $this->redirectToRoute('maintenance_with_page', ['pageId' => 'main_home']); // Redirection remains, no user-facing string
        }
        
        $response = $this->render('frontend/home/main-home.html.twig');
        
        // Ajouter des en-têtes de cache HTTP pour de meilleures performances
        $response->setPublic();
        $response->setMaxAge(300); // 5 minutes
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->setEtag(md5($response->getContent()));
        
        return $response;
    }

    #[Route('/profile', name: 'frontend_profile')]
    public function profile(PageStatusService $pageStatusService, Request $request): Response
    {
        // Vérifier si le profil est hors ligne
        if (!$pageStatusService->isPageOnline('profile')) {
            return $this->redirectToRoute('maintenance_with_page', ['pageId' => 'profile']);
        }
        
        $response = $this->render('frontend/profile/profile.html.twig');
        
        // Les pages de profil sont privées, ne pas mettre en cache publiquement
        $response->setPrivate();
        $response->setMaxAge(60);
        
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

    #[Route('/sign-in', name: 'auth_sign_in', methods: ['GET', 'POST'])]
    public function signIn(Request $request, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher): Response
    {
        $session = $request->getSession();
        $error = null;
        $lastEmail = '';

        if ($session->get('is_logged_in')) {
            return $this->redirectToRoute('main_home');
        }

        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email'));
            $password = $request->request->get('password');
            $lastEmail = $email;

            if ($email !== '' && $password !== null) {
                $user = $userRepository->findOneBy(['email_user' => $email]);
                if ($user !== null && $passwordHasher->isPasswordValid($user, $password)) {
                    if (!$user->getIsVerified()) {
                        $error = 'Your account is not yet verified by an administrator. You cannot log in until your account is verified.';
                    } else {
                        $session->set('is_logged_in', true);
                        $session->set('user', [
                            'id' => $user->getIdUser(),
                            'name' => trim($user->getFirstName() . ' ' . $user->getLastName()),
                            'email' => $user->getEmailUser(),
                            'role' => $user->getRoleUser(),
                        ]);
                        return $this->redirectToRoute('main_home');
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
            } else {
                $error = 'Please enter your email and password.';
            }
        }

        $response = $this->render('frontend/auth/sign-in.html.twig', [
            'error' => $error,
            'last_email' => $lastEmail,
        ]);
        $response->setPrivate();

        return $response;
    }

    #[Route('/sign-up', name: 'auth_sign_up', methods: ['GET', 'POST'])]
    public function signUp(Request $request): Response
    {
        // Redirect to the real signup form (Symfony form, saves to DB)
        return $this->redirectToRoute('user_signup');
    }

    #[Route('/logout', name: 'auth_logout')]
    public function logout(Request $request): Response
    {
        $session = $request->getSession();
        $session->remove('is_logged_in');
        $session->remove('user');
        
        return $this->redirectToRoute('main_home');
    }
}
