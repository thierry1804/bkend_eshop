<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp',
    ];

    private const WEBP_QUALITY = 80;

    public function __construct(
        private string $uploadDir,
        private string $baseUrl,
        private int $maxSize,
    ) {}

    public function upload(UploadedFile $file): string
    {
        $this->validate($file);
        $this->ensureUploadDir();

        $filename = $this->generateFilename();
        $targetPath = $this->uploadDir . '/' . $filename;

        $this->convertToWebp($file->getPathname(), $targetPath);

        return $this->baseUrl . '/' . $filename;
    }

    public function replace(string $filename, UploadedFile $file): string
    {
        $this->validateFilename($filename);
        $existingPath = $this->uploadDir . '/' . $filename;

        if (!file_exists($existingPath)) {
            throw new \RuntimeException('Image not found.', 404);
        }

        $this->validate($file);
        $this->convertToWebp($file->getPathname(), $existingPath);

        return $this->baseUrl . '/' . $filename;
    }

    public function delete(string $filename): void
    {
        $this->validateFilename($filename);
        $path = $this->uploadDir . '/' . $filename;

        if (!file_exists($path)) {
            throw new \RuntimeException('Image not found.', 404);
        }

        unlink($path);
    }

    public function getAbsolutePath(string $filename): string
    {
        $this->validateFilename($filename);
        $path = $this->uploadDir . '/' . $filename;

        if (!file_exists($path)) {
            throw new \RuntimeException('Image not found.', 404);
        }

        return $path;
    }

    public function list(): array
    {
        $this->ensureUploadDir();
        $files = glob($this->uploadDir . '/*.webp');
        $result = [];

        foreach ($files as $file) {
            $basename = basename($file);
            $result[] = [
                'filename' => $basename,
                'url' => $this->baseUrl . '/' . $basename,
                'size' => filesize($file),
                'created_at' => date('c', filectime($file)),
            ];
        }

        usort($result, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return $result;
    }

    private function validate(UploadedFile $file): void
    {
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException(
                'Invalid image type. Allowed: JPEG, PNG, GIF, WebP, BMP.'
            );
        }

        if ($file->getSize() > $this->maxSize) {
            throw new \InvalidArgumentException(
                sprintf('File too large. Maximum size: %d MB.', $this->maxSize / 1048576)
            );
        }

        $imageInfo = @getimagesize($file->getPathname());
        if ($imageInfo === false) {
            throw new \InvalidArgumentException('File is not a valid image.');
        }
    }

    private function convertToWebp(string $sourcePath, string $targetPath): void
    {
        $imageInfo = getimagesize($sourcePath);
        $mimeType = $imageInfo['mime'];

        $image = match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($sourcePath),
            'image/png' => imagecreatefrompng($sourcePath),
            'image/gif' => imagecreatefromgif($sourcePath),
            'image/webp' => imagecreatefromwebp($sourcePath),
            'image/bmp' => imagecreatefrombmp($sourcePath),
            default => throw new \RuntimeException('Unsupported image format.'),
        };

        if ($image === false) {
            throw new \RuntimeException('Failed to process image.');
        }

        // Preserve transparency for PNG/GIF
        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        if (!imagewebp($image, $targetPath, self::WEBP_QUALITY)) {
            imagedestroy($image);
            throw new \RuntimeException('Failed to convert image to WebP.');
        }

        imagedestroy($image);
    }

    private function generateFilename(): string
    {
        $timestamp = (int)(microtime(true) * 1000);
        $random = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 6);

        return $timestamp . '-' . $random . '.webp';
    }

    private function validateFilename(string $filename): void
    {
        if (!preg_match('/^\d+-[a-z0-9]+\.webp$/', $filename)) {
            throw new \InvalidArgumentException('Invalid filename.');
        }

        // Path traversal protection
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            throw new \InvalidArgumentException('Invalid filename.');
        }
    }

    private function ensureUploadDir(): void
    {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
}
