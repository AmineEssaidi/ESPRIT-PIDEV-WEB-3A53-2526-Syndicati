<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SecuritySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 10]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $session = $request->getSession();

        // Protect /admin routes
        if (str_starts_with($path, '/admin')) {
            // Check if logged in
            if (!$session->get('is_logged_in')) {
                $session->set('access_denied_message', 'You must be logged in to access this page.');

                // Get referer or default to home
                $referer = $request->headers->get('referer');
                $redirectUrl = $referer ?: $this->urlGenerator->generate('main_home');

                $response = new RedirectResponse($redirectUrl);
                $event->setResponse($response);
                return;
            }

            // Check roles
            $user = $session->get('user');
            $allowedRoles = ['OWNER', 'ADMIN', 'SYNDIC', 'SUPERADMIN'];

            if (!$user || !isset($user['role']) || !in_array($user['role'], $allowedRoles)) {
                $session->set('access_denied_message', 'Access denied. You do not have permission to access the admin area.');

                $referer = $request->headers->get('referer');
                $redirectUrl = $referer ?: $this->urlGenerator->generate('main_home');

                $response = new RedirectResponse($redirectUrl);
                $event->setResponse($response);
                return;
            }
        }

        // Protect /profile routes
        if (str_starts_with($path, '/profile')) {
            if (!$session->get('is_logged_in')) {
                $session->set('access_denied_message', 'Please sign in to view your profile.');

                $referer = $request->headers->get('referer');
                $redirectUrl = $referer ?: $this->urlGenerator->generate('main_home');

                $response = new RedirectResponse($redirectUrl);
                $event->setResponse($response);
                return;
            }
        }
    }
}
