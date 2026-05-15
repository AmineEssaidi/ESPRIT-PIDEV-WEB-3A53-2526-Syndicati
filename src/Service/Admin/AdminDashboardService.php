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
        $activeToday = $this->logRepository->countActiveIdentitiesSince($today);
        $interactionsToday = $this->logRepository->countSince($today);
        $newUsersWeek = $this->logRepository->countActiveIdentitiesSince($sevenDaysAgo);

        return [
            'total_users' => (int) $totalUsers,
            'active_today' => (int) $activeToday,
            'interactions_today' => (int) $interactionsToday,
            'new_users_week' => (int) $newUsersWeek,
        ];
    }

    public function getInteractionTrends(): array
    {
        return $this->logRepository->fetchInteractionTrends(new \DateTime('-7 days'));
    }

    public function getTopPages(): array
    {
        return $this->logRepository->fetchTopPages(5);
    }

    public function getTopClicks(): array
    {
        return $this->logRepository->fetchTopClicks(5);
    }

    public function getRecentActivity(): array
    {
        return $this->logRepository->findRecent(10);
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
        return $this->logRepository->fetchDeviceBreakdown(new \DateTime('-30 days'));
    }

    public function getTopUsers(): array
    {
        return $this->logRepository->fetchTopUsers(new \DateTime('-7 days'), 5);
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
                    $stats['disabled'] = (int) $conn->fetchOne("SELECT COUNT(*) FROM user WHERE is_disabled = 1");
                    $stats['roles'] = $conn->fetchAllKeyValue("SELECT role_user, COUNT(*) FROM user GROUP BY role_user");
                    // Assuming activity log has registration events, otherwise track weekly active users
                    $stats['active_this_week'] = (int) $conn->fetchOne("SELECT COUNT(DISTINCT user_id) FROM app_event_log WHERE created_at >= :date", ['date' => (new \DateTime('-7 days'))->format('Y-m-d H:i:s')]);
                    $stats['auth_failures_30d'] = (int) $conn->fetchOne("SELECT COUNT(*) FROM app_event_log WHERE created_at >= :date AND (event_type = 'AUTH_FAILURE' OR outcome = 'FAILURE')", ['date' => (new \DateTime('-30 days'))->format('Y-m-d H:i:s')]);
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

    public function getActivityModuleStats(): array
    {
        $today = new \DateTimeImmutable('today');
        $week = new \DateTimeImmutable('-7 days');
        $month = new \DateTimeImmutable('-30 days');

        return [
            'active_today' => $this->logRepository->countActiveIdentitiesSince($today),
            'interactions_today' => $this->logRepository->countSince($today),
            'page_views_week' => $this->logRepository->countSince($week, 'PAGE_VIEW'),
            'clicks_week' => $this->logRepository->countSince($week, 'UI_CLICK'),
            'auth_failures_month' => $this->countAuthFailuresSince($month),
            'security_events_month' => $this->countSecurityEventsSince($month),
            'recent_activity' => $this->logRepository->findRecent(60),
            'risk_signals' => $this->logRepository->fetchRiskSignals(8),
            'outcomes' => $this->logRepository->fetchOutcomeBreakdown($month),
            'levels' => $this->logRepository->fetchLevelBreakdown($month),
            'devices' => $this->logRepository->fetchDeviceBreakdown($month),
            'features' => $this->logRepository->fetchFeatureUsage(8),
            'suspicious_users' => $this->logRepository->fetchSuspiciousUsers($month, 2, 6),
        ];
    }

    private function countAuthFailuresSince(\DateTimeInterface $since): int
    {
        try {
            return (int) $this->em->getConnection()->fetchOne(
                "SELECT COUNT(*) FROM app_event_log WHERE created_at >= :since AND (event_type = 'AUTH_FAILURE' OR outcome = 'FAILURE')",
                ['since' => $since->format('Y-m-d H:i:s')]
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    private function countSecurityEventsSince(\DateTimeInterface $since): int
    {
        try {
            return (int) $this->em->getConnection()->fetchOne(
                "SELECT COUNT(*) FROM app_event_log WHERE created_at >= :since AND (category IN ('AUTH','SECURITY') OR event_type LIKE 'AUTH_%' OR event_type LIKE 'SECURITY_%')",
                ['since' => $since->format('Y-m-d H:i:s')]
            );
        } catch (\Throwable) {
            return 0;
        }
    }
}
