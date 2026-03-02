<?php

namespace App\Service\Admin;

use App\Repository\Log\AppEventLogRepository;
use App\Repository\User\UserRepository;
use App\Repository\Forum\PublicationRepository;
use App\Repository\Evenement\EvenementRepository;
use Doctrine\ORM\EntityManagerInterface;

class AdminDashboardService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly AppEventLogRepository $logRepository,
        private readonly PublicationRepository $publicationRepository,
        private readonly EvenementRepository $evenementRepository
    ) {
    }

    public function getHeartbeatStats(): array
    {
        $today = new \DateTime('today');
        $sevenDaysAgo = new \DateTime('-7 days');

        // Total Users
        $totalUsers = $this->userRepository->count([]);

        $conn = $this->em->getConnection();

        // Active Today (Unique user_id in logs)
        try {
            // Count distinct active sessions: prefer user_id, fall back to IP for anonymous
            $sqlActive = "
                SELECT COUNT(DISTINCT COALESCE(
                    CAST(user_id AS CHAR),
                    JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.ip'))
                ))
                FROM app_event_log
                WHERE created_at >= :today
            ";
            $activeToday = $conn->fetchOne($sqlActive, ['today' => $today->format('Y-m-d H:i:s')]);

            // Total Interactions Today
            $sqlInteractions = "SELECT COUNT(*) FROM app_event_log WHERE created_at >= :today";
            $interactionsToday = $conn->fetchOne($sqlInteractions, ['today' => $today->format('Y-m-d H:i:s')]);

            // Active users this week (users with a user_id OR distinct IPs)
            $sqlNewActivity = "
                SELECT COUNT(DISTINCT COALESCE(
                    CAST(user_id AS CHAR),
                    JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.ip'))
                ))
                FROM app_event_log
                WHERE created_at >= :date
            ";
            $newUsersWeek = $conn->fetchOne($sqlNewActivity, ['date' => $sevenDaysAgo->format('Y-m-d H:i:s')]);
        } catch (\Exception $e) {
            $activeToday = 0;
            $interactionsToday = 0;
            $newUsersWeek = 0;
        }

        return [
            'total_users' => (int) $totalUsers,
            'active_today' => (int) $activeToday,
            'interactions_today' => (int) $interactionsToday,
            'new_users_week' => (int) $newUsersWeek,
        ];
    }

    public function getInteractionTrends(): array
    {
        $conn = $this->em->getConnection();
        $sql = "
            SELECT 
                DATE(created_at) as log_date,
                SUM(CASE WHEN event_type = 'PAGE_VIEW' THEN 1 ELSE 0 END) as views,
                SUM(CASE WHEN event_type = 'UI_CLICK' THEN 1 ELSE 0 END) as clicks
            FROM app_event_log
            WHERE created_at >= :date
            GROUP BY DATE(created_at)
            ORDER BY log_date ASC
        ";

        $sevenDaysAgo = new \DateTime('-7 days');
        return $conn->fetchAllAssociative($sql, ['date' => $sevenDaysAgo->format('Y-m-d')]);
    }

    public function getTopPages(): array
    {
        $conn = $this->em->getConnection();
        // Extracting route from JSON metadata. Use JSON_EXTRACT for compatibility.
        $sql = "
            SELECT 
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.route')), 'Direct/Unknown') as route,
                COUNT(*) as visit_count
            FROM app_event_log
            WHERE event_type = 'PAGE_VIEW'
            GROUP BY route
            ORDER BY visit_count DESC
            LIMIT 5
        ";
        return $conn->fetchAllAssociative($sql);
    }

    public function getTopClicks(): array
    {
        $conn = $this->em->getConnection();
        // Extracting target or text from JSON metadata
        $sql = "
            SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.text')) as element_text,
                JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.target')) as target,
                COUNT(*) as click_count
            FROM app_event_log
            WHERE event_type = 'UI_CLICK'
            GROUP BY element_text, target
            ORDER BY click_count DESC
            LIMIT 5
        ";
        return $conn->fetchAllAssociative($sql);
    }

    public function getRecentActivity(): array
    {
        return $this->logRepository->findBy([], ['created_at' => 'DESC'], 10);
    }

    public function getCommunityStats(): array
    {
        $sevenDaysAgo = new \DateTime('-7 days');

        $newPosts = $this->publicationRepository->createQueryBuilder('p')
            ->select('count(p.id)')
            ->where('p.date_creation_pub >= :date')
            ->setParameter('date', $sevenDaysAgo)
            ->getQuery()
            ->getSingleScalarResult();

        $newEvents = $this->evenementRepository->createQueryBuilder('e')
            ->select('count(e.id)')
            ->where('e.created_at >= :date')
            ->setParameter('date', $sevenDaysAgo)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'new_posts' => (int) $newPosts,
            'new_events' => (int) $newEvents,
        ];
    }

    public function getDeviceBreakdown(): array
    {
        $conn = $this->em->getConnection();
        // Fetch User-Agents from the last 30 days
        $sql = "
            SELECT JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.user_agent')) as ua
            FROM app_event_log
            WHERE created_at >= :date AND JSON_EXTRACT(metadata, '$.user_agent') IS NOT NULL
        ";
        $thirtyDaysAgo = new \DateTime('-30 days');
        $statements = $conn->fetchAllAssociative($sql, ['date' => $thirtyDaysAgo->format('Y-m-d')]);

        $stats = [
            'desktop' => 0,
            'mobile' => 0,
            'chrome' => 0,
            'safari' => 0,
            'firefox' => 0,
            'edge' => 0,
            'other' => 0
        ];

        foreach ($statements as $row) {
            $ua = strtolower($row['ua'] ?? '');
            if (!$ua)
                continue;

            // Platform
            if (preg_match('/mobi|android|touch|mini/i', $ua)) {
                $stats['mobile']++;
            } else {
                $stats['desktop']++;
            }

            // Browser
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

        $total = count($statements) ?: 1;

        return [
            'desktop_pct' => round(($stats['desktop'] / $total) * 100),
            'mobile_pct' => round(($stats['mobile'] / $total) * 100),
            'browsers' => [
                'Chrome' => round(($stats['chrome'] / $total) * 100),
                'Safari' => round(($stats['safari'] / $total) * 100),
                'Firefox' => round(($stats['firefox'] / $total) * 100),
                'Edge' => round(($stats['edge'] / $total) * 100),
                'Other' => round(($stats['other'] / $total) * 100),
            ]
        ];
    }

    public function getTopUsers(): array
    {
        $conn = $this->em->getConnection();
        $sql = "
            SELECT 
                u.first_name, 
                u.last_name, 
                u.role_user,
                COUNT(l.id) as activity_count
            FROM app_event_log l
            JOIN user u ON l.user_id = u.id_user
            WHERE l.created_at >= :date
            GROUP BY u.id_user, u.first_name, u.last_name, u.role_user
            ORDER BY activity_count DESC
            LIMIT 5
        ";
        $sevenDaysAgo = new \DateTime('-7 days');
        return $conn->fetchAllAssociative($sql, ['date' => $sevenDaysAgo->format('Y-m-d H:i:s')]);
    }

    public function getRecentSignups(): array
    {
        return $this->userRepository->findBy([], ['created_at' => 'DESC'], 5);
    }

    /**
     * Get statistics for a specific dashboard module tab
     */
    public function getModuleStats(string $module): array
    {
        $conn = $this->em->getConnection();
        $stats = [];
        $today = new \DateTime('today');

        try {
            switch ($module) {
                case 'users':
                    $stats['total'] = (int) $conn->fetchOne("SELECT COUNT(*) FROM user");
                    $stats['verified'] = (int) $conn->fetchOne("SELECT COUNT(*) FROM user WHERE is_verified = 1");
                    $stats['roles'] = $conn->fetchAllKeyValue("SELECT role_user, COUNT(*) FROM user GROUP BY role_user");
                    // Assuming activity log has registration events, otherwise track weekly active users
                    $stats['active_this_week'] = (int) $conn->fetchOne("SELECT COUNT(DISTINCT user_id) FROM app_event_log WHERE created_at >= :date", ['date' => (new \DateTime('-7 days'))->format('Y-m-d H:i:s')]);
                    $stats['recent_users'] = $conn->fetchAllAssociative("
                        SELECT first_name, last_name, role_user, created_at 
                        FROM user 
                        ORDER BY created_at DESC 
                        LIMIT 5
                    ");
                    break;
                case 'forum':
                    $stats['publications'] = (int) $conn->fetchOne("SELECT COUNT(*) FROM publication");
                    $stats['comments'] = (int) $conn->fetchOne("SELECT COUNT(*) FROM commentaire");
                    $stats['pubs_today'] = (int) $conn->fetchOne("SELECT COUNT(*) FROM publication WHERE date_creation_pub >= :today", ['today' => $today->format('Y-m-d H:i:s')]);
                    $stats['top_authors'] = $conn->fetchAllAssociative("
                        SELECT u.first_name, u.last_name, COUNT(p.id) as pub_count 
                        FROM publication p 
                        JOIN user u ON p.user_id = u.id_user 
                        GROUP BY u.id_user 
                        ORDER BY pub_count DESC 
                        LIMIT 4
                    ");
                    $stats['recent_pubs'] = $conn->fetchAllAssociative("
                        SELECT p.titre_pub, p.image_pub, p.date_creation_pub, u.first_name, u.last_name 
                        FROM publication p 
                        JOIN user u ON p.user_id = u.id_user 
                        ORDER BY p.date_creation_pub DESC 
                        LIMIT 4
                    ");
                    break;
                case 'syndicat':
                    $stats['total'] = (int) $conn->fetchOne("SELECT COUNT(*) FROM user WHERE role_user = 'SYNDIC'");
                    $stats['reclamations'] = (int) $conn->fetchOne("SELECT COUNT(*) FROM reclamations");
                    $stats['reclamations_pending'] = (int) $conn->fetchOne("SELECT COUNT(*) FROM reclamations WHERE statutreclamation = 'en_attente'");
                    $stats['reponses'] = (int) $conn->fetchOne("SELECT COUNT(*) FROM reponses");
                    $stats['recent_reclamations'] = $conn->fetchAllAssociative("
                        SELECT titrereclamations, imagereclamation, statutreclamation 
                        FROM reclamations 
                        ORDER BY idreclamations DESC 
                        LIMIT 4
                    ");
                    break;
                case 'residence':
                    $stats['total'] = (int) $conn->fetchOne("SELECT COUNT(*) FROM user WHERE role_user = 'RESIDENT'");
                    $stats['residences'] = (int) $conn->fetchOne("SELECT COUNT(*) FROM residence");
                    $stats['appartements'] = (int) $conn->fetchOne("SELECT COUNT(*) FROM appartement");
                    $stats['recent_residences'] = $conn->fetchAllAssociative("
                        SELECT nom_r, image_r, adresse 
                        FROM residence 
                        ORDER BY id_residence DESC 
                        LIMIT 4
                    ");
                    break;
                case 'evenement':
                    $stats['total'] = (int) $conn->fetchOne("SELECT COUNT(*) FROM evenement");
                    $stats['upcoming'] = (int) $conn->fetchOne("SELECT COUNT(*) FROM evenement WHERE date_event >= :today", ['today' => $today->format('Y-m-d H:i:s')]);
                    $stats['participations'] = (int) $conn->fetchOne("SELECT COUNT(*) FROM participation");
                    $stats['recent_events'] = $conn->fetchAllAssociative("
                        SELECT titre_event, image_event, date_event, statut_event 
                        FROM evenement 
                        ORDER BY id_event DESC 
                        LIMIT 4
                    ");
                    break;
            }
        } catch (\Exception $e) {
            // Silently fail and return empty stats if table doesn't exist or query errors out
            $stats['_error'] = $e->getMessage();
        }

        return $stats;
    }
}
