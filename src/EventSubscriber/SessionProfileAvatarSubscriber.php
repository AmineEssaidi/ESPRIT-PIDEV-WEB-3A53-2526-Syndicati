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
        if (!\is_array($userData) || empty($userData['id'])) {
            return;
        }

        $userId = (int) $userData['id'];
        $user = $this->userRepository->find($userId);
        if (!$user) {
            return;
        }

        $profile = $this->profileRepository->findOneByUser($user);
        if (!$profile || !$profile->getAvatar()) {
            return;
        }

        $avatar = $profile->getAvatar();
        if (($userData['avatar'] ?? null) === $avatar) {
            return;
        }

        $userData['avatar'] = $avatar;
        $session->set('user', $userData);
    }
}
