<?php

namespace App\Service\Forum;

use App\Entity\Forum\Publication;
use App\Service\Media\ImagePathResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DiscordWebhookService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ImagePathResolver $imagePathResolver,
        #[Autowire('%discord_forum_webhook_url%')]
        private readonly string $webhookUrl = ''
    ) {
    }

    public function announceJeuxVideo(Publication $publication, bool $isUpdate = false): void
    {
        if (!$this->isJeuxVideo($publication->getCategoriePub()) || trim($this->webhookUrl) === '') {
            return;
        }

        $author = $publication->getUser()
            ? trim(($publication->getUser()->getFirstName() ?? '') . ' ' . ($publication->getUser()->getLastName() ?? ''))
            : 'Syndicati member';
        if ($author === '') {
            $author = $publication->getUser()?->getEmailUser() ?? 'Syndicati member';
        }

        $titlePrefix = $isUpdate ? 'Updated Jeux Video publication' : 'New Jeux Video publication';
        $imageUrl = $this->imagePathResolver->publicUrl($publication->getImagePub(), 'forum_images', null, true);

        $embed = [
            'title' => $titlePrefix . ': ' . ($publication->getTitrePub() ?? 'Untitled'),
            'description' => $this->limitText($publication->getDescriptionPub() ?? '', 1800),
            'color' => $isUpdate ? 0xffb86b : 0xff6b6b,
            'author' => ['name' => $author],
            'fields' => [
                ['name' => 'Category', 'value' => 'Jeux Video', 'inline' => true],
                ['name' => 'Source', 'value' => 'Syndicati Web', 'inline' => true],
            ],
            'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'footer' => ['text' => 'Syndicati Forum'],
        ];

        if ($imageUrl) {
            $embed['image'] = ['url' => $imageUrl];
        }

        try {
            $this->httpClient->request('POST', $this->webhookUrl, [
                'json' => [
                    'username' => 'Syndicati Forum',
                    'content' => $isUpdate ? 'A Jeux Video post was updated.' : 'A Jeux Video post just dropped.',
                    'embeds' => [$embed],
                ],
                'timeout' => 4,
            ]);
        } catch (\Throwable $e) {
            // Discord should never block publication saves.
        }
    }

    private function isJeuxVideo(?string $category): bool
    {
        $normalized = mb_strtolower(trim($category ?? ''));
        return in_array($normalized, ['jeux video', 'jeux vidéo'], true);
    }

    private function limitText(string $text, int $max): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $max) {
            return $text === '' ? 'No description provided.' : $text;
        }

        return mb_substr($text, 0, $max - 1) . '...';
    }
}
