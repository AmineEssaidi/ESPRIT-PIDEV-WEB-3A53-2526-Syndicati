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

    public function mediaUrl(?string $value, string $folder, ?string $fallback = null, bool $absolute = false): ?string
    {
        return $this->resolver->publicUrl($value, $folder, $fallback, $absolute);
    }

    /**
     * @return list<string>
     */
    public function mediaUrls(mixed $value, string $folder, bool $absolute = false): array
    {
        return $this->resolver->publicUrls($value, $folder, $absolute);
    }
}
