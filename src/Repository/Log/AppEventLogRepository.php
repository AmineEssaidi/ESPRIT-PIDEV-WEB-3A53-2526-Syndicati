<?php

namespace App\Repository\Log;

use App\Entity\Log\AppEventLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AppEventLog>
 */
class AppEventLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppEventLog::class);
    }

    /**
     * @return AppEventLog[] Returns an array of AppEventLog objects
     */
    public function findByEntityType(string $type, int $id): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.entity_type = :val')
            ->andWhere('a.entity_id = :id')
            ->setParameter('val', $type)
            ->setParameter('id', $id)
            ->orderBy('a.created_at', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findLatestByUser(int $userId, int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.user', 'u')
            ->andWhere('u.id_user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('a.created_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    public function findRecent(int $limit = 25): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->orderBy('a.created_at', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult()
        ;
    }

    public function findSecurityByUser(int $userId, int $limit = 12): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.user', 'u')
            ->andWhere('u.id_user = :userId')
            ->andWhere('a.category IN (:categories) OR a.event_type LIKE :auth OR a.event_type LIKE :security OR a.event_type LIKE :password OR a.event_type LIKE :twofa OR a.event_type LIKE :face')
            ->setParameter('userId', $userId)
            ->setParameter('categories', ['AUTH', 'SECURITY'])
            ->setParameter('auth', 'AUTH_%')
            ->setParameter('security', 'SECURITY_%')
            ->setParameter('password', '%PASSWORD%')
            ->setParameter('twofa', '%TWO_FACTOR%')
            ->setParameter('face', '%FACE%')
            ->orderBy('a.created_at', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult()
        ;
    }

    public function getUserSecuritySummary(int $userId): array
    {
        $conn = $this->getEntityManager()->getConnection();

        try {
            $since = (new \DateTimeImmutable('-30 days'))->format('Y-m-d H:i:s');
            $params = ['userId' => $userId, 'since' => $since];

            $total = (int) $conn->fetchOne(
                "SELECT COUNT(*) FROM app_event_log WHERE user_id = :userId AND created_at >= :since",
                $params
            );
            $security = (int) $conn->fetchOne(
                "SELECT COUNT(*) FROM app_event_log WHERE user_id = :userId AND created_at >= :since AND (category IN ('AUTH','SECURITY') OR event_type LIKE 'AUTH_%' OR event_type LIKE 'SECURITY_%')",
                $params
            );
            $failures = (int) $conn->fetchOne(
                "SELECT COUNT(*) FROM app_event_log WHERE user_id = :userId AND created_at >= :since AND (outcome = 'FAILURE' OR event_type = 'AUTH_FAILURE')",
                $params
            );
            $lastSeen = $conn->fetchOne(
                "SELECT MAX(created_at) FROM app_event_log WHERE user_id = :userId",
                ['userId' => $userId]
            );

            return [
                'total_30d' => $total,
                'security_30d' => $security,
                'failures_30d' => $failures,
                'last_seen' => $lastSeen ? new \DateTimeImmutable((string) $lastSeen) : null,
            ];
        } catch (\Throwable) {
            return [
                'total_30d' => 0,
                'security_30d' => 0,
                'failures_30d' => 0,
                'last_seen' => null,
            ];
        }
    }

    public function countSince(\DateTimeInterface $since, ?string $eventType = null): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.created_at >= :since')
            ->setParameter('since', $since);

        if ($eventType !== null) {
            $qb->andWhere('a.event_type = :eventType')
                ->setParameter('eventType', $eventType);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countActiveIdentitiesSince(\DateTimeInterface $since): int
    {
        try {
            return (int) $this->getEntityManager()->getConnection()->fetchOne(
                "SELECT COUNT(DISTINCT COALESCE(CAST(user_id AS CHAR), JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.ip')), JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.host')))) FROM app_event_log WHERE created_at >= :since",
                ['since' => $since->format('Y-m-d H:i:s')]
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    public function fetchInteractionTrends(\DateTimeInterface $since): array
    {
        return $this->safeFetchAll(
            "SELECT DATE(created_at) AS log_date,
                    SUM(CASE WHEN event_type = 'PAGE_VIEW' THEN 1 ELSE 0 END) AS views,
                    SUM(CASE WHEN event_type = 'UI_CLICK' THEN 1 ELSE 0 END) AS clicks
             FROM app_event_log
             WHERE created_at >= :since
             GROUP BY DATE(created_at)
             ORDER BY log_date ASC",
            ['since' => $since->format('Y-m-d H:i:s')]
        );
    }

    public function fetchTopPages(int $limit = 5): array
    {
        return $this->safeFetchAll(
            "SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.route')), 'Direct/Unknown') AS route,
                    COUNT(*) AS visit_count
             FROM app_event_log
             WHERE event_type = 'PAGE_VIEW'
             GROUP BY route
             ORDER BY visit_count DESC
             LIMIT " . max(1, $limit)
        );
    }

    public function fetchTopClicks(int $limit = 5): array
    {
        return $this->safeFetchAll(
            "SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.text')), 'Unknown') AS element_text,
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.target')), 'Unknown') AS target,
                    COUNT(*) AS click_count
             FROM app_event_log
             WHERE event_type = 'UI_CLICK'
             GROUP BY element_text, target
             ORDER BY click_count DESC
             LIMIT " . max(1, $limit)
        );
    }

    public function fetchTopUsers(\DateTimeInterface $since, int $limit = 5): array
    {
        return $this->safeFetchAll(
            "SELECT u.first_name, u.last_name, u.role_user, COUNT(l.id) AS activity_count
             FROM app_event_log l
             JOIN user u ON l.user_id = u.id_user
             WHERE l.created_at >= :since
             GROUP BY u.id_user, u.first_name, u.last_name, u.role_user
             ORDER BY activity_count DESC
             LIMIT " . max(1, $limit),
            ['since' => $since->format('Y-m-d H:i:s')]
        );
    }

    public function fetchDeviceBreakdown(\DateTimeInterface $since): array
    {
        $rows = $this->safeFetchAll(
            "SELECT COALESCE(user_agent, JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.user_agent'))) AS ua
             FROM app_event_log
             WHERE created_at >= :since",
            ['since' => $since->format('Y-m-d H:i:s')]
        );

        $stats = ['desktop' => 0, 'mobile' => 0, 'chrome' => 0, 'safari' => 0, 'firefox' => 0, 'edge' => 0, 'other' => 0];
        $total = 0;
        foreach ($rows as $row) {
            $ua = strtolower((string) ($row['ua'] ?? ''));
            if ($ua === '') {
                continue;
            }

            $total++;
            if (preg_match('/mobi|android|touch|mini/i', $ua)) {
                $stats['mobile']++;
            } else {
                $stats['desktop']++;
            }

            if (str_contains($ua, 'edg/')) {
                $stats['edge']++;
            } elseif (str_contains($ua, 'chrome') || str_contains($ua, 'crios')) {
                $stats['chrome']++;
            } elseif (str_contains($ua, 'firefox') || str_contains($ua, 'fxios')) {
                $stats['firefox']++;
            } elseif (str_contains($ua, 'safari') && !str_contains($ua, 'chrome')) {
                $stats['safari']++;
            } else {
                $stats['other']++;
            }
        }

        $safeTotal = max(1, $total);

        return [
            'desktop_pct' => (int) round(($stats['desktop'] / $safeTotal) * 100),
            'mobile_pct' => (int) round(($stats['mobile'] / $safeTotal) * 100),
            'browsers' => [
                'Chrome' => (int) round(($stats['chrome'] / $safeTotal) * 100),
                'Safari' => (int) round(($stats['safari'] / $safeTotal) * 100),
                'Firefox' => (int) round(($stats['firefox'] / $safeTotal) * 100),
                'Edge' => (int) round(($stats['edge'] / $safeTotal) * 100),
                'Other' => (int) round(($stats['other'] / $safeTotal) * 100),
            ],
        ];
    }

    public function fetchOutcomeBreakdown(\DateTimeInterface $since): array
    {
        return $this->safeFetchAll(
            "SELECT COALESCE(outcome, 'UNKNOWN') AS label, COUNT(*) AS count
             FROM app_event_log
             WHERE created_at >= :since
             GROUP BY COALESCE(outcome, 'UNKNOWN')
             ORDER BY count DESC",
            ['since' => $since->format('Y-m-d H:i:s')]
        );
    }

    public function fetchLevelBreakdown(\DateTimeInterface $since): array
    {
        return $this->safeFetchAll(
            "SELECT COALESCE(level, 'INFO') AS label, COUNT(*) AS count
             FROM app_event_log
             WHERE created_at >= :since
             GROUP BY COALESCE(level, 'INFO')
             ORDER BY count DESC",
            ['since' => $since->format('Y-m-d H:i:s')]
        );
    }

    public function fetchRiskSignals(int $limit = 8): array
    {
        return $this->safeFetchAll(
            "SELECT event_type, COALESCE(outcome, 'UNKNOWN') AS outcome,
                    COALESCE(risk_score, 0) AS risk_score,
                    COALESCE(anomaly_score, 0) AS anomaly_score,
                    COALESCE(duration_ms, 0) AS duration_ms,
                    COALESCE(level, 'INFO') AS level,
                    COALESCE(service_name, '-') AS service_name,
                    COALESCE(environment, '-') AS environment,
                    created_at
             FROM app_event_log
             ORDER BY COALESCE(risk_score, 0) DESC, COALESCE(anomaly_score, 0) DESC, created_at DESC
             LIMIT " . max(1, $limit)
        );
    }

    public function fetchSuspiciousUsers(\DateTimeInterface $since, int $minFailures = 2, int $limit = 6): array
    {
        return $this->safeFetchAll(
            "SELECT l.user_id, MAX(u.email_user) AS email, COUNT(*) AS failure_count, MAX(l.created_at) AS last_seen
             FROM app_event_log l
             LEFT JOIN user u ON l.user_id = u.id_user
             WHERE l.created_at >= :since
               AND l.user_id IS NOT NULL
               AND (l.event_type = 'AUTH_FAILURE' OR COALESCE(l.outcome, '') = 'FAILURE')
             GROUP BY l.user_id
             HAVING COUNT(*) >= :minFailures
             ORDER BY failure_count DESC, last_seen DESC
             LIMIT " . max(1, $limit),
            ['since' => $since->format('Y-m-d H:i:s'), 'minFailures' => max(1, $minFailures)]
        );
    }

    public function fetchFeatureUsage(int $limit = 8): array
    {
        return $this->safeFetchAll(
            "SELECT COALESCE(action, event_type, 'UNKNOWN') AS feature_key, COUNT(*) AS usage_count
             FROM app_event_log
             GROUP BY COALESCE(action, event_type, 'UNKNOWN')
             ORDER BY usage_count DESC
             LIMIT " . max(1, $limit)
        );
    }

    private function safeFetchAll(string $sql, array $params = []): array
    {
        try {
            return $this->getEntityManager()->getConnection()->fetchAllAssociative($sql, $params);
        } catch (\Throwable) {
            return [];
        }
    }
}
