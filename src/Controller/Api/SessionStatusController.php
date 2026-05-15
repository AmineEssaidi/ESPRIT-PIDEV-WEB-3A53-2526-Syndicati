<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/session')]
class SessionStatusController extends AbstractController
{
    #[Route('/status', name: 'api_session_status', methods: ['GET', 'POST'])]
    public function status(Request $request): JsonResponse
    {
        $session = $request->getSession();
        $user = $session->get('user');
        $isLoggedIn = $session->get('is_logged_in') && is_array($user) && isset($user['id']);

        if ($isLoggedIn && $request->isMethod('POST')) {
            $session->set('last_session_heartbeat', time());
        }

        $response = $this->json([
            'success' => true,
            'authenticated' => (bool) $isLoggedIn,
            'serverTime' => time(),
            'user' => $isLoggedIn ? [
                'id' => (int) $user['id'],
                'name' => (string) ($user['name'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
                'role' => (string) ($user['role'] ?? 'USER'),
                'avatar' => $user['avatar'] ?? null,
            ] : null,
        ]);
        $response->setPrivate();
        $response->setMaxAge(10);
        return $response;
    }
}
