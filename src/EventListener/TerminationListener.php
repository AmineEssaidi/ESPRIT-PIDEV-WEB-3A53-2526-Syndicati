<?php

namespace App\EventListener;

use App\Service\Log\LogBuffer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Persists all buffered logs and notifications after the response has been sent to the user.
 */
class TerminationListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LogBuffer $logBuffer
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $items = $this->logBuffer->getItems();
        if (empty($items)) {
            return;
        }

        try {
            // All items are already persisted via $em->persist() in their respective services.
            // We just need a single flush at the end of the process.
            $this->entityManager->flush();
            $this->logBuffer->clear();
        } catch (\Exception $e) {
            error_log("[TerminationListener] Error flushing log buffer: " . $e->getMessage());
        }
    }
}
