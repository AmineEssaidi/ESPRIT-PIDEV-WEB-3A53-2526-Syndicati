<?php

namespace App\EventSubscriber;

use App\Repository\Profile\ProfileRepository;
use App\Repository\User\UserRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Syncs the logged-in user's session with their profile avatar so the frontend
 * header/navbar shows the correct avatar on every page (not only on profile page).
 */
class SessionProfileAvatarSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ProfileRepository $profileRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 5],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        if (!$session->get('is_logged_in')) {
            return;
        }

        $userData = $session->get('user');
        if (!\is_array($userData)) {
            return;
        }

        $userId = (int) ($userData['id'] ?? $userData['id_user'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        // Only check database once every 30 minutes or if avatar is missing from session
        $now = time();
        $lastChecked = $session->get('last_avatar_check', 0);
        if (isset($userData['avatar']) && ($now - $lastChecked < 1800)) {
            return;
        }

        $user = $this->userRepository->find($userId);
        if (!$user) {
            return;
        }

        $profile = $this->profileRepository->findOneByUser($user);
        $avatar = $profile ? $profile->getAvatar() : null;

        if (!$avatar) {
            $session->set('last_avatar_check', $now);
            return;
        }

        if (($userData['avatar'] ?? null) !== $avatar) {
            $userData['avatar'] = $avatar;
            $session->set('user', $userData);
        }

        $session->set('last_avatar_check', $now);
    }
}
