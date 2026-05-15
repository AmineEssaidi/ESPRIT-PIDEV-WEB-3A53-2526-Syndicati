<?php

namespace App\Service\Media;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ImageKitStorageService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $privateKey = '',
        private readonly string $urlEndpoint = '',
        private readonly bool $enabled = false
    ) {
    }

    public function storeUploadedFile(UploadedFile $file, string $localDirectory, string $publicFolder, string $imageKitFolder, string $filename): string
    {
        if (!is_dir($localDirectory) && !mkdir($localDirectory, 0755, true) && !is_dir($localDirectory)) {
            throw new FileException('Could not create upload directory.');
        }

        $localPath = rtrim($localDirectory, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filename);
        $localSubdir = dirname($localPath);
        if (!is_dir($localSubdir) && !mkdir($localSubdir, 0755, true) && !is_dir($localSubdir)) {
            throw new FileException('Could not create upload subdirectory.');
        }

        try {
            $file->move($localDirectory, $filename);
        } catch (FileException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new FileException('Could not save uploaded file.', 0, $e);
        }

        $fallback = trim($publicFolder, '/') . '/' . $filename;
        $relativeDir = trim(str_replace('\\', '/', dirname($filename)), '.');
        $remoteFolder = '/' . trim($imageKitFolder, '/');
        if ($relativeDir !== '') {
            $remoteFolder .= '/' . trim($relativeDir, '/');
        }
        $remoteUrl = $this->uploadLocalFile($localPath, $remoteFolder, basename($filename));

        return $remoteUrl ?: $fallback;
    }

    public function uploadLocalFile(string $localPath, string $imageKitFolder, ?string $filename = null): ?string
    {
        if (!$this->enabled || $this->privateKey === '' || !is_file($localPath)) {
            return null;
        }

        $filename ??= basename($localPath);

        try {
            $response = $this->httpClient->request('POST', 'https://upload.imagekit.io/api/v1/files/upload', [
                'auth_basic' => [$this->privateKey, ''],
                'body' => [
                    'file' => fopen($localPath, 'rb'),
                    'fileName' => $filename,
                    'folder' => '/' . trim($imageKitFolder, '/'),
                    'useUniqueFileName' => 'false',
                ],
                'timeout' => 25,
            ]);

            if ($response->getStatusCode() >= 400) {
                return null;
            }

            $data = $response->toArray(false);
            $url = (string) ($data['url'] ?? '');
            if ($url !== '') {
                return $url;
            }

            $filePath = (string) ($data['filePath'] ?? '');
            if ($this->urlEndpoint !== '' && $filePath !== '') {
                return rtrim($this->urlEndpoint, '/') . '/' . ltrim($filePath, '/');
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
