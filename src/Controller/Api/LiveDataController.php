<?php

namespace App\Controller\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/live')]
class LiveDataController extends AbstractController
{
    public function __construct(private readonly Connection $connection)
    {
    }

    #[Route('/snapshot', name: 'api_live_snapshot', methods: ['GET'])]
    public function snapshot(Request $request): JsonResponse
    {
        $session = $request->getSession();
        $sessionUser = $session->get('user');

        if (!$session->get('is_logged_in') || !is_array($sessionUser) || !isset($sessionUser['id'])) {
            return $this->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $userId = (int) $sessionUser['id'];
        $role = strtoupper((string) ($sessionUser['role'] ?? $sessionUser['roleUser'] ?? ''));
        $isAdmin = in_array($role, ['OWNER', 'ADMIN', 'SUPERADMIN', 'SYNDIC'], true);
        $scopeSql = $isAdmin ? '1 = 1' : 'user_id = :user_id';
        $reclamationScopeSql = $isAdmin ? '1 = 1' : 'id_user = :user_id';
        $params = $isAdmin ? [] : ['user_id' => $userId];

        $forum = [
            'publications' => $this->count('publication', $scopeSql, $params),
            'comments' => $this->count('commentaire', $isAdmin ? '1 = 1' : 'id_user = :user_id', $params),
            'reactions' => $this->count('reaction', $scopeSql, $params),
            'bookmarks' => $this->count('reaction', ($isAdmin ? '1 = 1' : 'user_id = :user_id') . " AND kind = 'Bookmark'", $params),
            'latestAt' => max(
                $this->timestamp('publication', 'date_creation_pub', $scopeSql, $params),
                $this->timestamp('commentaire', 'updated_at', $isAdmin ? '1 = 1' : 'id_user = :user_id', $params),
                $this->timestamp('reaction', 'updated_at', $scopeSql, $params)
            ),
        ];

        $reclamations = [
            'total' => $this->count('reclamations', $reclamationScopeSql, $params),
            'pending' => $this->count('reclamations', $reclamationScopeSql . " AND statutreclamation = 'en_attente'", $params),
            'active' => $this->count('reclamations', $reclamationScopeSql . " AND statutreclamation = 'active'", $params),
            'closed' => $this->count('reclamations', $reclamationScopeSql . " AND statutreclamation IN ('termine', 'refuse')", $params),
            'latestAt' => $this->timestamp('reclamations', 'updated_at', $reclamationScopeSql, $params),
        ];

        $circle = [
            'friends' => (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM user_relationship
                 WHERE status = 'FRIENDS' AND (user_first_id = :user_id OR user_second_id = :user_id)",
                ['user_id' => $userId]
            ),
            'pending' => (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM user_relationship
                 WHERE (user_second_id = :user_id AND status = 'PENDING_FIRST_SECOND')
                    OR (user_first_id = :user_id AND status = 'PENDING_SECOND_FIRST')",
                ['user_id' => $userId]
            ),
            'latestAt' => (int) ($this->connection->fetchOne(
                "SELECT COALESCE(UNIX_TIMESTAMP(MAX(updated_at)), UNIX_TIMESTAMP(MAX(created_at)), 0)
                 FROM user_relationship
                 WHERE user_first_id = :user_id OR user_second_id = :user_id",
                ['user_id' => $userId]
            ) ?: 0),
        ];

        $standing = $this->connection->fetchAssociative(
            'SELECT level, points, standing_label, COALESCE(UNIX_TIMESTAMP(updated_at), 0) AS latest_at
             FROM user_standing WHERE user_id = :user_id LIMIT 1',
            ['user_id' => $userId]
        ) ?: ['level' => 1, 'points' => 0, 'standing_label' => 'NORMAL', 'latest_at' => 0];

        $payload = [
            'success' => true,
            'serverTime' => time(),
            'scope' => $isAdmin ? 'admin' : 'user',
            'forum' => $forum,
            'reclamations' => $reclamations,
            'circle' => $circle,
            'standing' => [
                'level' => (int) ($standing['level'] ?? 1),
                'points' => (int) ($standing['points'] ?? 0),
                'label' => (string) ($standing['standing_label'] ?? 'NORMAL'),
                'latestAt' => (int) ($standing['latest_at'] ?? 0),
            ],
        ];
        $payload['version'] = hash('xxh3', json_encode($payload, JSON_THROW_ON_ERROR));

        $response = new JsonResponse($payload);
        $response->setPrivate();
        $response->setMaxAge(8);
        $response->headers->set('Cache-Control', 'private, max-age=8, stale-while-revalidate=20');
        return $response;
    }

    private function count(string $table, string $where, array $params): int
    {
        return (int) $this->connection->fetchOne("SELECT COUNT(*) FROM {$table} WHERE {$where}", $params);
    }

    private function timestamp(string $table, string $column, string $where, array $params): int
    {
        return (int) ($this->connection->fetchOne(
            "SELECT COALESCE(UNIX_TIMESTAMP(MAX({$column})), 0) FROM {$table} WHERE {$where}",
            $params
        ) ?: 0);
    }
}
