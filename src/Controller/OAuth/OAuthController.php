<?php

namespace App\Controller\OAuth;

use App\Repository\User\UserRepository;
use App\Service\OAuth\GoogleOAuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OAuthController extends AbstractController
{
    private function getRedirectUri(Request $request): string
    {
        return $request->getSchemeAndHttpHost() . $this->generateUrl('oauth_gmail_callback');
    }

    #[Route('/oauth/gmail/connect', name: 'oauth_gmail_connect', methods: ['GET'])]
    public function gmailConnect(Request $request, GoogleOAuthService $googleOAuthService, UserRepository $userRepository): Response
    {
        $session = $request->getSession();
        $userId = $session->get('is_logged_in') && isset($session->get('user')['id']) ? (int) $session->get('user')['id'] : null;
        if (!$userId) {
            return $this->redirectToRoute('auth_sign_in');
        }
        $user = $userRepository->find($userId);
        if (!$user instanceof \App\Entity\User\User) {
            return $this->redirectToRoute('auth_sign_in');
        }

        $state = base64_encode(json_encode([
            'user_id' => $user->getIdUser(),
            'csrf' => $request->getSession()->get('_csrf/oauth_gmail') ?? bin2hex(random_bytes(16)),
        ]));
        $request->getSession()->set('oauth_gmail_state', $state);

        $redirectUri = $this->getRedirectUri($request);
        $url = $googleOAuthService->getAuthorizationUrl($state, $redirectUri);
        return $this->redirect($url);
    }

    #[Route('/oauth/gmail/callback', name: 'oauth_gmail_callback', methods: ['GET'])]
    public function gmailCallback(
        Request $request,
        GoogleOAuthService $googleOAuthService,
        UserRepository $userRepository
    ): Response {
        $session = $request->getSession();
        $state = $request->query->get('state');
        $code = $request->query->get('code');
        $error = $request->query->get('error');

        if ($error) {
            $this->addFlash('danger', 'Google denied access: ' . $error);
            return $this->redirectToRoute('frontend_profile');
        }

        $savedState = $session->get('oauth_gmail_state');
        $session->remove('oauth_gmail_state');
        if ($state === null || $state !== $savedState) {
            $this->addFlash('danger', 'Invalid state. Please try connecting again.');
            return $this->redirectToRoute('frontend_profile');
        }

        $decoded = @json_decode((string) base64_decode($state, true), true);
        $userId = is_array($decoded) ? ($decoded['user_id'] ?? null) : null;
        if (!$userId) {
            $this->addFlash('danger', 'Invalid state.');
            return $this->redirectToRoute('frontend_profile');
        }

        $user = $userRepository->find($userId);
        $currentUserId = $session->get('is_logged_in') && isset($session->get('user')['id']) ? (int) $session->get('user')['id'] : null;
        if (!$user || !$currentUserId || $user->getIdUser() !== $currentUserId) {
            $this->addFlash('danger', 'User mismatch.');
            return $this->redirectToRoute('frontend_profile');
        }

        if (!$code) {
            $this->addFlash('danger', 'No authorization code received.');
            return $this->redirectToRoute('frontend_profile');
        }

        try {
            $redirectUri = $this->getRedirectUri($request);
            $googleOAuthService->exchangeCodeAndStore($user, $code, $redirectUri);
            $this->addFlash('success', 'Gmail account connected. You can now use it to send mail.');
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Failed to connect Gmail: ' . $e->getMessage());
        }

        return $this->redirectToRoute('frontend_profile', [], \Symfony\Component\HttpFoundation\Response::HTTP_SEE_OTHER);
    }

    #[Route('/oauth/gmail/disconnect', name: 'oauth_gmail_disconnect', methods: ['POST'])]
    public function gmailDisconnect(Request $request, \App\Repository\OAuth\OAuthRepository $oauthRepository, \App\Repository\User\UserRepository $userRepository, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        $userId = $session->get('is_logged_in') && isset($session->get('user')['id']) ? (int) $session->get('user')['id'] : null;
        if (!$userId) {
            return $this->redirectToRoute('auth_sign_in');
        }
        $user = $userRepository->find($userId);
        if (!$user instanceof \App\Entity\User\User) {
            return $this->redirectToRoute('auth_sign_in');
        }

        $oauth = $oauthRepository->findOneByUser($user);
        if ($oauth) {
            $em->remove($oauth);
            $em->flush();
            $this->addFlash('success', 'Gmail account disconnected.');
        }

        return $this->redirectToRoute('frontend_profile');
    }
}
