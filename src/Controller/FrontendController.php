<?php

namespace App\Controller;

use App\Service\PageStatusService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
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
    public function signIn(Request $request): Response
    {
        $session = $request->getSession();
        $error = null;
        
        // If already logged in, redirect to home
        if ($session->get('is_logged_in')) {
            return $this->redirectToRoute('main_home');
        }
        
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            
            // Simple hardcoded auth: admin@admin.com / admin OR admin / admin
            if (($email === 'admin@admin.com' || $email === 'admin') && $password === 'admin') {
                $session->set('is_logged_in', true);
                $session->set('user', [
                    'name' => 'Amine Saidi',
                    'email' => 'amine.saidi@example.com',
                    'avatar' => 'https://demos.themeselection.com/sneat-bootstrap-html-admin-template/assets/img/avatars/1.png',
                    'role' => 'Full Stack Developer'
                ]);
                return $this->redirectToRoute('main_home');
            } else {
                $error = 'Invalid credentials. Use admin@admin.com / admin';
            }
        }
        
        $response = $this->render('frontend/auth/sign-in.html.twig', [
            'error' => $error
        ]);
        $response->setPrivate();
        
        return $response;
    }

    #[Route('/sign-up', name: 'auth_sign_up', methods: ['GET', 'POST'])]
    public function signUp(Request $request): Response
    {
        // TODO: Add actual registration logic here
        if ($request->isMethod('POST')) {
            // Handle sign up form submission
            // For now, just redirect to sign in
            return $this->redirectToRoute('auth_sign_in');
        }
        
        $response = $this->render('frontend/auth/sign-up.html.twig');
        $response->setPublic();
        $response->setMaxAge(300);
        
        return $response;
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
