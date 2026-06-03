<?php

namespace App\Twig;

use App\Service\Media\ImagePathResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ImagePathExtension extends AbstractExtension
{
    public function __construct(private readonly ImagePathResolver $resolver)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('media_url', [$this, 'mediaUrl']),
            new TwigFunction('media_urls', [$this, 'mediaUrls']),
        ];
    }

    public function mediaUrl(mixed $value, string $folder, ?string $fallback = null, bool $absolute = false): ?string
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_scalar($item) && trim((string) $item) !== '') {
                    return $this->resolver->publicUrl((string) $item, $folder, $fallback, $absolute);
                }
            }

            return $fallback;
        }

        if ($value !== null && !is_scalar($value)) {
            return $fallback;
        }

        return $this->resolver->publicUrl($value !== null ? (string) $value : null, $folder, $fallback, $absolute);
    }

    /**
     * @return list<string>
     */
    public function mediaUrls(mixed $value, string $folder, bool $absolute = false): array
    {
        return $this->resolver->publicUrls($value, $folder, $absolute);
    }
}
