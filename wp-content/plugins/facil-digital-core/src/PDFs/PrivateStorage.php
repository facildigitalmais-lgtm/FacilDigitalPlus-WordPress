<?php

declare(strict_types=1);

namespace FacilDigital\Core\PDFs;

use RuntimeException;

final class PrivateStorage
{
    public const DEFAULT_MAX_MASTER_BYTES = 262144000;

    /**
     * @return list<string>
     */
    private function directories(): array
    {
        return [
            'masters',
            'generated',
            'temp',
        ];
    }

    public function root(): string
    {
        $configured = '';

        if (defined('FACIL_DIGITAL_PRIVATE_DIR')) {
            $value = constant('FACIL_DIGITAL_PRIVATE_DIR');
            if (is_string($value)) {
                $configured = $value;
            }
        }

        if ($configured === '') {
            $environment = getenv('FACIL_DIGITAL_PRIVATE_DIR');
            if (is_string($environment)) {
                $configured = $environment;
            }
        }

        if ($configured === '') {
            $configured = dirname(rtrim(ABSPATH, '/\\'))
                . '/facil-digital-private';
        }

        $root = rtrim(wp_normalize_path($configured), '/');

        if ($root === '' || !str_starts_with($root, '/')) {
            throw new RuntimeException('private_storage_path_invalid');
        }

        $publicRoot = rtrim(wp_normalize_path(ABSPATH), '/');

        if (
            $root === $publicRoot
            || str_starts_with($root, $publicRoot . '/')
        ) {
            throw new RuntimeException('private_storage_inside_public_root');
        }

        return $root;
    }

    public function ensureReady(): void
    {
        $root = $this->root();

        if (!is_dir($root) && !wp_mkdir_p($root)) {
            throw new RuntimeException('private_storage_create_failed');
        }

        @chmod($root, 0750);

        foreach ($this->directories() as $directory) {
            $path = $root . '/' . $directory;

            if (!is_dir($path) && !wp_mkdir_p($path)) {
                throw new RuntimeException('private_storage_create_failed');
            }

            @chmod($path, 0750);
        }

        if (!is_writable($root)) {
            throw new RuntimeException('private_storage_not_writable');
        }
    }

    public function isReady(): bool
    {
        try {
            $this->ensureReady();
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    public function path(string $key): string
    {
        $this->ensureReady();

        $key = trim(str_replace('\\', '/', $key), '/');

        if (
            $key === ''
            || str_contains($key, '..')
            || preg_match(
                '#^(masters|generated|temp)/[A-Za-z0-9][A-Za-z0-9/._-]*$#',
                $key
            ) !== 1
        ) {
            throw new RuntimeException('private_storage_key_invalid');
        }

        $path = $this->root() . '/' . $key;
        $directory = dirname($path);

        if (!is_dir($directory) && !wp_mkdir_p($directory)) {
            throw new RuntimeException('private_storage_directory_failed');
        }

        @chmod($directory, 0750);

        $normalizedDirectory = rtrim(
            wp_normalize_path($directory),
            '/'
        );
        $root = $this->root();

        if (
            $normalizedDirectory !== $root
            && !str_starts_with(
                $normalizedDirectory,
                $root . '/'
            )
        ) {
            throw new RuntimeException('private_storage_escape_detected');
        }

        return $path;
    }

    public function storeMaster(
        int $productId,
        string $sourcePath
    ): string {
        if ($productId <= 0) {
            throw new RuntimeException('master_product_invalid');
        }

        $this->assertPdf($sourcePath);

        $token = bin2hex(random_bytes(16));
        $key = sprintf(
            'masters/product-%d/%s.pdf',
            $productId,
            $token
        );

        $destination = $this->path($key);

        if (!copy($sourcePath, $destination)) {
            throw new RuntimeException('master_copy_failed');
        }

        @chmod($destination, 0640);
        $this->assertPdf($destination);

        return $key;
    }

    public function generatedKey(
        int $userId,
        int $orderId,
        int $productId,
        string $version
    ): string {
        $version = sanitize_title($version);
        if ($version === '') {
            $version = '1';
        }

        return sprintf(
            'generated/user-%d/order-%d/product-%d/version-%s/%s.pdf',
            $userId,
            $orderId,
            $productId,
            $version,
            bin2hex(random_bytes(16))
        );
    }

    public function tempKey(): string
    {
        return 'temp/' . bin2hex(random_bytes(20)) . '.pdf';
    }

    public function delete(string $key): void
    {
        try {
            $path = $this->path($key);
        } catch (\Throwable) {
            return;
        }

        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function assertPdf(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('pdf_file_unreadable');
        }

        $size = filesize($path);
        if ($size === false || $size < 8) {
            throw new RuntimeException('pdf_file_empty');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('pdf_file_unreadable');
        }

        try {
            $signature = fread($handle, 5);
        } finally {
            fclose($handle);
        }

        if ($signature !== '%PDF-') {
            throw new RuntimeException('pdf_signature_invalid');
        }
    }
}
