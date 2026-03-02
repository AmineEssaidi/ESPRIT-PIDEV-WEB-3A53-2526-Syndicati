<?php

namespace App\Service\Log;

use App\Entity\Log\AppEventLog;
use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class UserActivityLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
        private LogBuffer $logBuffer
    ) {
    }

    public function log(string $eventType, string $entityType, ?int $entityId = null, array $metadata = [], ?User $user = null): void
    {
        try {
            $log = new AppEventLog();
            $log->setEventType($eventType);
            $log->setEntityType($entityType);
            $log->setEntityId($entityId);

            $request = $this->requestStack->getCurrentRequest();
            if ($request) {
                $metadata['ip'] = $request->getClientIp();
                $metadata['user_agent'] = $request->headers->get('User-Agent');
                $metadata['url'] = $request->getUri();
                $metadata['method'] = $request->getMethod();
            }

            $log->setMetadata($metadata);

            if ($user) {
                $log->setUser($user);
            }

            $this->entityManager->persist($log);
            $this->logBuffer->push($log);
        } catch (\Exception $e) {
            error_log("[UserActivityLogger] Error: " . $e->getMessage());
        }
    }
}
