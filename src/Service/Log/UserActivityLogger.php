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
            $eventType = strtoupper(trim($eventType));
            $category = strtoupper((string) ($metadata['category'] ?? $this->inferCategory($eventType, $entityType)));
            $outcome = strtoupper((string) ($metadata['outcome'] ?? 'SUCCESS'));
            $level = strtoupper((string) ($metadata['level'] ?? ($outcome === 'FAILURE' ? 'WARN' : 'INFO')));

            $log->setEventType($eventType);
            $log->setCategory($category);
            $log->setAction((string) ($metadata['action'] ?? $eventType));
            $log->setOutcome($outcome);
            $log->setMessage($metadata['message'] ?? null);
            $log->setEntityType(strtoupper(trim($entityType)));
            $log->setEntityId($entityId);
            $log->setDurationMs(isset($metadata['duration_ms']) ? (int) $metadata['duration_ms'] : null);
            $log->setRiskScore($metadata['risk_score'] ?? $this->inferRiskScore($eventType, $outcome, $level));
            $log->setAnomalyScore($metadata['anomaly_score'] ?? null);
            $log->setLevel($level);
            $log->setServiceName('HorizonWeb');
            $log->setEnvironment($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'web');
            $log->setApplicationVersion($_SERVER['APP_VERSION'] ?? $_ENV['APP_VERSION'] ?? 'dev');

            $request = $this->requestStack->getCurrentRequest();
            if ($request) {
                $metadata['ip'] = $request->getClientIp();
                $metadata['user_agent'] = $request->headers->get('User-Agent');
                $metadata['url'] = $request->getUri();
                $metadata['method'] = $request->getMethod();
                $metadata['route'] = $metadata['route'] ?? $request->attributes->get('_route');
                $metadata['is_ajax'] = $metadata['is_ajax'] ?? $request->isXmlHttpRequest();

                $session = $request->hasSession() ? $request->getSession() : null;
                $log->setSessionId($session?->getId());
                $log->setRequestId($request->headers->get('X-Request-Id') ?: bin2hex(random_bytes(8)));
                $log->setTraceId($request->headers->get('X-Trace-Id') ?: ($session?->get('activity_trace_id') ?: bin2hex(random_bytes(16))));
                $log->setSpanId(bin2hex(random_bytes(8)));
                $log->setUserAgent($metadata['user_agent']);

                if ($session && !$session->has('activity_trace_id')) {
                    $session->set('activity_trace_id', $log->getTraceId());
                }
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

    public function logAuthAction(string $action, string $outcome, string $message, array $metadata = [], ?User $user = null): void
    {
        $metadata['category'] = 'AUTH';
        $metadata['action'] = strtoupper($action);
        $metadata['outcome'] = strtoupper($outcome);
        $metadata['message'] = $message;
        $metadata['level'] = strtoupper($outcome) === 'FAILURE' ? 'WARN' : 'INFO';

        $this->log(strtoupper($outcome) === 'FAILURE' ? 'AUTH_FAILURE' : 'AUTH_' . strtoupper($action), 'USER', $user?->getIdUser(), $metadata, $user);
    }

    public function logSecurityAlert(string $alertType, string $severity, string $message, array $metadata = [], ?User $user = null): void
    {
        $metadata['category'] = 'SECURITY';
        $metadata['action'] = strtoupper($alertType);
        $metadata['outcome'] = $metadata['outcome'] ?? 'WARNING';
        $metadata['level'] = strtoupper($severity);
        $metadata['message'] = $message;

        $this->log('SECURITY_ALERT', 'SYSTEM', null, $metadata, $user);
    }

    private function inferCategory(string $eventType, string $entityType): string
    {
        if (str_starts_with($eventType, 'AUTH') || str_contains($eventType, 'LOGIN')) {
            return 'AUTH';
        }
        if (str_contains($eventType, 'SECURITY') || str_contains($eventType, 'PASSWORD') || str_contains($eventType, 'TWO_FACTOR') || str_contains($eventType, 'FACE')) {
            return 'SECURITY';
        }
        if (in_array(strtoupper($entityType), ['ROUTE', 'UI_ELEMENT'], true)) {
            return 'USER_ACTIVITY';
        }

        return 'APPLICATION';
    }

    private function inferRiskScore(string $eventType, string $outcome, string $level): string
    {
        if ($outcome === 'FAILURE' || in_array($level, ['WARN', 'WARNING', 'ERROR', 'CRITICAL'], true)) {
            return '65.00';
        }
        if (str_starts_with($eventType, 'AUTH') || str_contains($eventType, 'SECURITY')) {
            return '35.00';
        }

        return '0.00';
    }
}
