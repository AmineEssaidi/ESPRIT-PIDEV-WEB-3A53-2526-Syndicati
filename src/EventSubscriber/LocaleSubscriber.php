<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{
    private string $defaultLocale;

    public function __construct(string $defaultLocale = 'fr')
    {
        $this->defaultLocale = $defaultLocale;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest()) {
            return;
        }

        // Try to get locale from session (Authenticated user setting)
        $session = $request->getSession();
        if ($session && $session->has('user') && isset($session->get('user')['settings']['lang'])) {
            $locale = $session->get('user')['settings']['lang'];
            $request->setLocale($locale);
            return;
        }

        // Try to get locale from cookie (Anonymous user setting)
        if ($request->cookies->has('frontend_lang')) {
            $locale = $request->cookies->get('frontend_lang');
            $request->setLocale($locale);
            return;
        }

        // Fallback to default
        $request->setLocale($request->getDefaultLocale() ?: $this->defaultLocale);
    }

    public static function getSubscribedEvents(): array
    {
        return [
                // Must be registered before (i.e. with a higher priority than) the default Locale listener
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }
}
