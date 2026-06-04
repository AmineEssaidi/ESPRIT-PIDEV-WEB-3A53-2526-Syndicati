<?php

namespace App\Command;

use App\Service\Media\ImageKitStorageService;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:media:migrate-imagekit',
    description: 'Uploads existing local media references to ImageKit and updates database paths.'
)]
class MigrateMediaToImageKitCommand extends Command
{
    /**
     * @var array<string, array{table: string, id: string, column: string, folder: string, imagekit: string, max: int}>
     */
    private const MEDIA_SETS = [
        'events' => [
            'table' => 'evenement',
            'id' => 'id_event',
            'column' => 'image_event',
            'folder' => 'event_images',
            'imagekit' => '/syndicati/event_images',
            'max' => 255,
        ],
        'residences' => [
            'table' => 'residence',
            'id' => 'id_residence',
            'column' => 'image_r',
            'folder' => 'residence_images',
            'imagekit' => '/syndicati/residence_images',
            'max' => 255,
        ],
        'apartments' => [
            'table' => 'appartement',
            'id' => 'id_app',
            'column' => 'image_a',
            'folder' => 'appartement_images',
            'imagekit' => '/syndicati/appartement_images',
            'max' => 500,
        ],
        'profiles' => [
            'table' => 'profile',
            'id' => 'id_profile',
            'column' => 'avatar',
            'folder' => 'profile_images',
            'imagekit' => '/syndicati/profile_images',
            'max' => 255,
        ],
        'forum' => [
            'table' => 'publication',
            'id' => 'id',
            'column' => 'image_pub',
            'folder' => 'forum_images',
            'imagekit' => '/syndicati/forum_images',
            'max' => 255,
        ],
        'comments' => [
            'table' => 'commentaire',
            'id' => 'id_commentaire',
            'column' => 'image_commentaire',
            'folder' => 'commentaire_images',
            'imagekit' => '/syndicati/commentaire_images',
            'max' => 255,
        ],
        'reclamations' => [
            'table' => 'reclamations',
            'id' => 'idreclamations',
            'column' => 'imagereclamation',
            'folder' => 'reclamation_images',
            'imagekit' => '/syndicati/reclamation_images',
            'max' => 255,
        ],
        'responses' => [
            'table' => 'reponses',
            'id' => 'idreponses',
            'column' => 'imagereponse',
            'folder' => 'reponse_images',
            'imagekit' => '/syndicati/reponse_images',
            'max' => 255,
        ],
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly ImageKitStorageService $imageStorage,
        private readonly KernelInterface $kernel
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Actually upload files and update database rows.')
            ->addOption('only', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Limit to one or more media sets: '.implode(', ', array_keys(self::MEDIA_SETS)))
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limit rows per media set.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');
        $limit = $input->getOption('limit') !== null ? max(1, (int) $input->getOption('limit')) : null;
        $selected = $this->selectedMediaSets((array) $input->getOption('only'));

        if ($selected === []) {
            $io->error('No valid media sets selected.');
            return Command::FAILURE;
        }

        $io->title($apply ? 'Migrating local media to ImageKit' : 'Dry-run: local media to ImageKit');

        $totals = [
            'scanned' => 0,
            'changed' => 0,
            'already_remote' => 0,
            'missing' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($selected as $name => $set) {
            $io->section($name);
            $rows = $this->fetchRows($set, $limit);
            $io->text(sprintf('Found %d row(s) with media values.', count($rows)));

            foreach ($rows as $row) {
                $totals['scanned']++;
                $id = (int) $row[$set['id']];
                $value = (string) $row[$set['column']];
                $result = $this->migrateValue($value, $set, $apply);

                foreach (['already_remote', 'missing', 'skipped', 'failed'] as $key) {
                    $totals[$key] += $result[$key];
                }

                if (!$result['changed']) {
                    continue;
                }

                $newValue = $result['value'];
                if (strlen($newValue) > $set['max']) {
                    $totals['failed']++;
                    $io->warning(sprintf(
                        '%s #%d was uploaded but the URL is %d chars, longer than %s.%s max %d. Skipping DB update.',
                        $set['table'],
                        $id,
                        strlen($newValue),
                        $set['table'],
                        $set['column'],
                        $set['max']
                    ));
                    continue;
                }

                if ($apply) {
                    $this->connection->update(
                        $set['table'],
                        [$set['column'] => $newValue],
                        [$set['id'] => $id]
                    );
                }

                $totals['changed']++;
                $io->text(sprintf(
                    '%s #%d: %s',
                    $set['table'],
                    $id,
                    $apply ? 'uploaded and updated' : 'would upload and update'
                ));
            }
        }

        $io->table(
            ['Metric', 'Count'],
            [
                ['Rows scanned', $totals['scanned']],
                [$apply ? 'Rows updated' : 'Rows that would update', $totals['changed']],
                ['Already remote URLs', $totals['already_remote']],
                ['Missing local files', $totals['missing']],
                ['Skipped values', $totals['skipped']],
                ['Failed uploads/updates', $totals['failed']],
            ]
        );

        if (!$apply) {
            $io->note('Dry-run only. Re-run with --apply to upload files and update DB references.');
        }

        return $totals['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param list<string> $only
     * @return array<string, array{table: string, id: string, column: string, folder: string, imagekit: string, max: int}>
     */
    private function selectedMediaSets(array $only): array
    {
        $only = array_values(array_filter(array_map('strval', $only)));
        if ($only === []) {
            return self::MEDIA_SETS;
        }

        return array_intersect_key(self::MEDIA_SETS, array_flip($only));
    }

    /**
     * @param array{table: string, id: string, column: string, folder: string, imagekit: string, max: int} $set
     * @return list<array<string, mixed>>
     */
    private function fetchRows(array $set, ?int $limit): array
    {
        $sql = sprintf(
            'SELECT %s, %s FROM %s WHERE %s IS NOT NULL AND TRIM(%s) <> \'\'',
            $set['id'],
            $set['column'],
            $set['table'],
            $set['column'],
            $set['column']
        );

        if ($limit !== null) {
            $sql .= ' LIMIT '.$limit;
        }

        return $this->connection->fetchAllAssociative($sql);
    }

    /**
     * @param array{table: string, id: string, column: string, folder: string, imagekit: string, max: int} $set
     * @return array{value: string, changed: bool, already_remote: int, missing: int, skipped: int, failed: int}
     */
    private function migrateValue(string $value, array $set, bool $apply): array
    {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $changed = false;
            $stats = ['already_remote' => 0, 'missing' => 0, 'skipped' => 0, 'failed' => 0];

            $walk = function (mixed $item) use (&$walk, &$changed, &$stats, $set, $apply): mixed {
                if (is_array($item)) {
                    foreach ($item as $key => $nested) {
                        $item[$key] = $walk($nested);
                    }
                    return $item;
                }

                if (!is_scalar($item)) {
                    $stats['skipped']++;
                    return $item;
                }

                $result = $this->migrateSingleValue((string) $item, $set, $apply);
                foreach ($stats as $key => $_) {
                    $stats[$key] += $result[$key];
                }
                $changed = $changed || $result['changed'];

                return $result['value'];
            };

            $newPayload = $walk($decoded);

            return [
                'value' => json_encode($newPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'changed' => $changed,
                ...$stats,
            ];
        }

        return $this->migrateSingleValue($value, $set, $apply);
    }

    /**
     * @param array{table: string, id: string, column: string, folder: string, imagekit: string, max: int} $set
     * @return array{value: string, changed: bool, already_remote: int, missing: int, skipped: int, failed: int}
     */
    private function migrateSingleValue(string $value, array $set, bool $apply): array
    {
        $original = $value;
        $value = trim($value, " \t\n\r\0\x0B\"'");
        $empty = ['value' => $original, 'changed' => false, 'already_remote' => 0, 'missing' => 0, 'skipped' => 0, 'failed' => 0];

        if ($value === '' || $value === '-') {
            return [...$empty, 'skipped' => 1];
        }

        if ($this->isRemoteValue($value)) {
            return [...$empty, 'already_remote' => 1];
        }

        if (!$this->looksLikeImage($value)) {
            return [...$empty, 'skipped' => 1];
        }

        $local = $this->resolveLocalMedia($value, $set['folder']);
        if ($local === null) {
            return [...$empty, 'missing' => 1];
        }

        if (!$apply) {
            return [
                'value' => $this->expectedRemoteUrl($local['relative'], $set['imagekit']),
                'changed' => true,
                'already_remote' => 0,
                'missing' => 0,
                'skipped' => 0,
                'failed' => 0,
            ];
        }

        $remoteUrl = $this->imageStorage->uploadLocalFile(
            $local['path'],
            $this->remoteFolderFor($local['relative'], $set['folder'], $set['imagekit']),
            basename($local['relative'])
        );

        if ($remoteUrl === null) {
            return [...$empty, 'failed' => 1];
        }

        return [
            'value' => $remoteUrl,
            'changed' => true,
            'already_remote' => 0,
            'missing' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];
    }

    /**
     * @return array{path: string, relative: string}|null
     */
    private function resolveLocalMedia(string $value, string $folder): ?array
    {
        $path = str_replace('\\', '/', $value);
        if (preg_match('/^[A-Za-z]:\//', $path)) {
            $path = basename($path);
        }

        $path = ltrim($path, '/');
        $path = preg_replace('#^(public/|uploads/)#', '', $path) ?? $path;
        $folder = trim($folder, '/');

        if (!str_starts_with($path, $folder.'/')) {
            $path = $folder.'/'.basename($path);
        }

        $fullPath = $this->kernel->getProjectDir().'/public/'.$path;
        if (is_file($fullPath)) {
            return ['path' => $fullPath, 'relative' => $path];
        }

        $fallback = $folder.'/'.basename($path);
        $fallbackPath = $this->kernel->getProjectDir().'/public/'.$fallback;
        if (is_file($fallbackPath)) {
            return ['path' => $fallbackPath, 'relative' => $fallback];
        }

        return null;
    }

    private function isRemoteValue(string $value): bool
    {
        return str_starts_with($value, 'http://')
            || str_starts_with($value, 'https://')
            || str_starts_with($value, 'data:')
            || preg_match('#https?://[^\s\'")]+#', $value) === 1;
    }

    private function looksLikeImage(string $value): bool
    {
        return preg_match('/\.(jpe?g|png|gif|webp|svg|bmp|avif)(?:\?.*)?$/i', $value) === 1;
    }

    private function expectedRemoteUrl(string $relative, string $imagekitFolder): string
    {
        return rtrim($imagekitFolder, '/').'/'.basename($relative);
    }

    private function remoteFolderFor(string $relative, string $folder, string $imagekitFolder): string
    {
        $folder = trim($folder, '/');
        $relativeInsideFolder = preg_replace('#^'.preg_quote($folder, '#').'/#', '', $relative) ?? basename($relative);
        $subdir = trim(str_replace('\\', '/', dirname($relativeInsideFolder)), '.');

        $remoteFolder = '/'.trim($imagekitFolder, '/');
        if ($subdir !== '') {
            $remoteFolder .= '/'.trim($subdir, '/');
        }

        return $remoteFolder;
    }
}
