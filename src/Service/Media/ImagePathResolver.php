<?php

namespace App\Service\Media;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;

class ImagePathResolver
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly KernelInterface $kernel
    )
    {
    }

    public function publicUrl(?string $value, string $folder, ?string $fallback = null, bool $absolute = false): ?string
    {
        $value = $this->normalizeValue($value);
        if ($value === null) {
            return $fallback;
        }

        $decodedValue = $this->extractFirstValueFromPayload($value);
        if ($decodedValue !== null) {
            $value = $decodedValue;
        }

        if ($this->isUrl($value) || str_starts_with($value, 'data:')) {
            return $value;
        }

        $embeddedUrl = $this->extractEmbeddedUrl($value);
        if ($embeddedUrl !== null) {
            return $embeddedUrl;
        }

        $path = str_replace('\\', '/', $value);
        if (preg_match('/^[A-Za-z]:\//', $path)) {
            $path = basename($path);
        }

        $path = ltrim($path, '/');
        $path = preg_replace('#^(public/|uploads/)#', '', $path) ?? $path;

        if ($folder !== '' && !str_starts_with($path, trim($folder, '/') . '/')) {
            $path = trim($folder, '/') . '/' . basename($path);
        }

        if ($this->looksLikeLocalMedia($path) && !$this->localMediaExists($path)) {
            return $fallback;
        }

        $url = '/' . ltrim($path, '/');
        return $absolute ? $this->absolute($url) : $url;
    }

    /**
     * @return list<string>
     */
    public function publicUrls(mixed $value, string $folder, bool $absolute = false): array
    {
        $items = [];

        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            $items = is_array($decoded) ? $decoded : [$value];
        }

        $urls = [];
        foreach ($items as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $url = $this->publicUrl((string) $item, $folder, null, $absolute);
            if ($url !== null) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    private function normalizeValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value, " \t\n\r\0\x0B\"'");
        $value = preg_replace('/(?:%22|")+]$/', '', $value) ?? $value;
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if ($value === '' || $value === '-') {
            return null;
        }

        return $value;
    }

    private function isUrl(string $value): bool
    {
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }

    private function extractEmbeddedUrl(string $value): ?string
    {
        if (preg_match('#https?://[^\s\'")]+#', $value, $match)) {
            return $match[0];
        }

        return null;
    }

    private function absolute(string $url): string
    {
        if ($this->isUrl($url)) {
            return $url;
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return $url;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/') . '/' . ltrim($url, '/');
    }

    private function extractFirstValueFromPayload(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (($trimmed[0] ?? '') === '[' || ($trimmed[0] ?? '') === '{') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                if (array_is_list($decoded)) {
                    foreach ($decoded as $item) {
                        if (is_scalar($item) && trim((string) $item) !== '') {
                            return trim((string) $item);
                        }
                    }
                }

                foreach ($decoded as $item) {
                    if (is_scalar($item) && trim((string) $item) !== '') {
                        return trim((string) $item);
                    }
                }
            }
        }

        return null;
    }

    private function looksLikeLocalMedia(string $path): bool
    {
        return preg_match('/\.(jpe?g|png|gif|webp|svg|bmp|avif)$/i', $path) === 1;
    }

    private function localMediaExists(string $path): bool
    {
        $fullPath = $this->kernel->getProjectDir() . '/public/' . ltrim($path, '/');

        return is_file($fullPath);
    }
}
