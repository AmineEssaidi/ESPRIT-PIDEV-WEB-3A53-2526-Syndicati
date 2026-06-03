<?php

namespace App\Controller\Concerns;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

trait SessionUserAwareTrait
{
    private function getSessionUserData(SessionInterface $session): ?array
    {
        $user = $session->get('user');

        return is_array($user) ? $user : null;
    }

    private function getSessionUserId(SessionInterface $session): ?int
    {
        $user = $this->getSessionUserData($session);
        $userId = $user['id_user'] ?? $user['id'] ?? null;

        return $userId !== null ? (int) $userId : null;
    }

    private function hasAuthenticatedSessionUser(SessionInterface $session): bool
    {
        return (bool) $session->get('is_logged_in') && $this->getSessionUserId($session) !== null;
    }
}
