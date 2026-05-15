<?php

namespace App\Controller\Sync;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/sync')]
class ChangeFeedController extends AbstractController
{
    #[Route('/changes', name: 'app_sync_changes', methods: ['GET'])]
    public function changes(Connection $connection): JsonResponse
    {
        $versions = [
            'forum' => $this->version($connection, [
                ['publication', 'id', 'date_creation_pub'],
                ['commentaire', 'id_commentaire', 'updated_at'],
            ]),
            'residence' => $this->version($connection, [
                ['residence', 'id_residence', 'date_ajout'],
                ['appartement', 'id_app', null],
                ['review', 'id_revue', null],
            ]),
            'evenement' => $this->version($connection, [
                ['evenement', 'id_event', 'edited_at'],
                ['participation', 'id_participation', null],
            ]),
        ];

        return new JsonResponse([
            'status' => 'ok',
            'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'versions' => $versions,
        ]);
    }

    /**
     * @param array<int, array{0: string, 1: string, 2: ?string}> $tables
     */
    private function version(Connection $connection, array $tables): string
    {
        $parts = [];

        foreach ($tables as [$table, $idColumn, $dateColumn]) {
            try {
                $selects = [
                    'COUNT(*) AS row_count',
                    sprintf('COALESCE(MAX(%s), 0) AS max_id', $idColumn),
                ];

                if ($dateColumn) {
                    $selects[] = sprintf("COALESCE(UNIX_TIMESTAMP(MAX(%s)), 0) AS max_seen", $dateColumn);
                }

                $row = $connection->fetchAssociative(sprintf('SELECT %s FROM %s', implode(', ', $selects), $table));
                $parts[] = $table . ':' . implode(':', array_map(static fn ($value) => (string) $value, $row ?: []));
            } catch (\Throwable) {
                $parts[] = $table . ':unavailable';
            }
        }

        return sha1(implode('|', $parts));
    }
}
