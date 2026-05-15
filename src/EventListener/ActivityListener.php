<?php

namespace App\EventListener;

use App\Service\Log\UserActivityLogger;
use App\Entity\User\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ActivityListener implements EventSubscriberInterface
{
    public function __construct(
        private UserActivityLogger $logger,
        private Security $security
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        $path = $request->getPathInfo();

        // Skip profiler, toolbar, API/AJAX polling, and asset-like requests.
        if (
            $route && (
                str_starts_with($route, '_wdt') ||
                str_starts_with($route, '_profiler') ||
                $route === 'activity_log' ||
                $route === 'api_log_event' ||
                $route === 'admin_dashboard_live' ||
                str_starts_with($route, 'api_') ||
                str_contains($route, '_status') ||
                str_contains($route, '_live') ||
                str_contains($route, 'notification') ||
                str_contains($route, 'messaging')
            )
        ) {
            return;
        }

        if (
            $request->isXmlHttpRequest() ||
            str_starts_with($path, '/api/') ||
            str_contains($path, '/live') ||
            str_contains($path, '/status') ||
            preg_match('#\.(?:css|js|map|png|jpe?g|webp|gif|svg|ico|woff2?|ttf|mp4|webm|json)$#i', $path)
        ) {
            return;
        }

        // Attach user if logged in; log as anonymous otherwise
        $user = $this->security->getUser();
        $userEntity = $user instanceof User ? $user : null;

        $this->logger->log(
            'PAGE_VIEW',
            'ROUTE',
            null,
            [
                'route' => $route,
                'params' => $request->attributes->get('_route_params'),
                'is_ajax' => $request->isXmlHttpRequest()
            ],
            $userEntity
        );
    }
}
