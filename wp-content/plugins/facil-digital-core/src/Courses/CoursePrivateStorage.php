<?php

declare(strict_types=1);

namespace FacilDigital\Core\Courses;

use FacilDigital\Core\PDFs\PrivateStorage;
use RuntimeException;

final class CoursePrivateStorage
{
    public function __construct(
        private readonly PrivateStorage $storage =
            new PrivateStorage()
    ) {
    }

    public function resourceKey(
        int $courseId,
        int $lessonId,
        string $extension
    ): string {
        $extension = strtolower(
            sanitize_key($extension)
        );

        if (
            $courseId <= 0
            || $lessonId <= 0
            || $extension === ''
            || !preg_match(
                '/^[a-z0-9]{1,10}$/',
                $extension
            )
        ) {
            throw new RuntimeException(
                'course_storage_key_invalid'
            );
        }

        return sprintf(
            'courses/resources/course-%d/lesson-%d/%s.%s',
            $courseId,
            $lessonId,
            bin2hex(random_bytes(16)),
            $extension
        );
    }

    public function certificateKey(
        int $certificateId,
        string $verificationCode
    ): string {
        $code = strtolower(
            (string) preg_replace(
                '/[^A-Za-z0-9-]/',
                '',
                $verificationCode
            )
        );

        if (
            $certificateId <= 0
            || $code === ''
        ) {
            throw new RuntimeException(
                'course_storage_key_invalid'
            );
        }

        return sprintf(
            'courses/certificates/certificate-%d-%s.pdf',
            $certificateId,
            $code
        );
    }

    public function temporaryCertificateKey(
        int $certificateId
    ): string {
        if ($certificateId <= 0) {
            throw new RuntimeException(
                'course_storage_key_invalid'
            );
        }

        return sprintf(
            'courses/tmp/certificate-%d-%s.pdf',
            $certificateId,
            bin2hex(random_bytes(12))
        );
    }

    public function path(
        string $storageKey
    ): string {
        $storageKey =
            $this->normalizeStorageKey(
                $storageKey
            );

        $this->ensureReady();

        $relative = substr(
            $storageKey,
            strlen('courses/')
        );

        if (
            !is_string($relative)
            || $relative === ''
        ) {
            throw new RuntimeException(
                'course_storage_key_invalid'
            );
        }

        $root = $this->root();

        $path =
            $root
            . '/'
            . $relative;

        $directory = dirname($path);

        if (
            !is_dir($directory)
            && !wp_mkdir_p($directory)
        ) {
            throw new RuntimeException(
                'course_storage_directory_failed'
            );
        }

        @chmod($directory, 0750);

        $normalizedDirectory = rtrim(
            wp_normalize_path(
                $directory
            ),
            '/'
        );

        if (
            $normalizedDirectory !== $root
            && !str_starts_with(
                $normalizedDirectory,
                $root . '/'
            )
        ) {
            throw new RuntimeException(
                'course_storage_escape_detected'
            );
        }

        return $path;
    }

    public function preparePath(
        string $storageKey
    ): string {
        $path = $this->path($storageKey);
        $directory = dirname($path);

        if (
            !is_dir($directory)
            && !wp_mkdir_p($directory)
        ) {
            throw new RuntimeException(
                'course_storage_directory_failed'
            );
        }

        if (!is_writable($directory)) {
            throw new RuntimeException(
                'course_storage_directory_unwritable'
            );
        }

        return $path;
    }

    public function assertIntegrity(
        string $storageKey,
        ?int $expectedSize = null,
        ?string $expectedSha256 = null
    ): string {
        $path = $this->path($storageKey);

        if (
            is_link($path)
            || !is_file($path)
            || !is_readable($path)
        ) {
            throw new RuntimeException(
                'course_storage_file_missing'
            );
        }

        $size = filesize($path);

        if (
            $size === false
            || $size <= 0
        ) {
            throw new RuntimeException(
                'course_storage_integrity_failed'
            );
        }

        if (
            $expectedSize !== null
            && $expectedSize > 0
            && $size !== $expectedSize
        ) {
            throw new RuntimeException(
                'course_storage_integrity_failed'
            );
        }

        $expectedSha256 = strtolower(
            trim(
                (string) $expectedSha256
            )
        );

        if ($expectedSha256 !== '') {
            if (
                !preg_match(
                    '/^[a-f0-9]{64}$/',
                    $expectedSha256
                )
            ) {
                throw new RuntimeException(
                    'course_storage_integrity_failed'
                );
            }

            $actualSha256 = hash_file(
                'sha256',
                $path
            );

            if (
                !is_string($actualSha256)
                || !hash_equals(
                    $expectedSha256,
                    strtolower($actualSha256)
                )
            ) {
                throw new RuntimeException(
                    'course_storage_integrity_failed'
                );
            }
        }

        return $path;
    }

    public function delete(
        string $storageKey
    ): void {
        $path = $this->path($storageKey);

        if (!file_exists($path)) {
            return;
        }

        if (
            is_link($path)
            || !is_file($path)
            || !@unlink($path)
        ) {
            throw new RuntimeException(
                'course_storage_delete_failed'
            );
        }
    }

    private function root(): string
    {
        $baseRoot = rtrim(
            wp_normalize_path(
                $this->storage->root()
            ),
            '/'
        );

        $root =
            $baseRoot
            . '/courses';

        if (
            wp_get_environment_type()
            === 'development'
            && !is_dir($baseRoot)
        ) {
            $parent =
                dirname($baseRoot);

            if (
                !is_dir($parent)
                || !is_writable($parent)
            ) {
                $root = rtrim(
                    wp_normalize_path(
                        sys_get_temp_dir()
                    ),
                    '/'
                )
                . '/facil-digital-private/courses';
            }
        }

        $root = rtrim(
            wp_normalize_path($root),
            '/'
        );

        $publicRoot = rtrim(
            wp_normalize_path(ABSPATH),
            '/'
        );

        if (
            $root === ''
            || !str_starts_with(
                $root,
                '/'
            )
            || $root === $publicRoot
            || str_starts_with(
                $root,
                $publicRoot . '/'
            )
        ) {
            throw new RuntimeException(
                'course_storage_path_invalid'
            );
        }

        return $root;
    }

    private function ensureReady(): void
    {
        $root = $this->root();

        if (
            !is_dir($root)
            && !wp_mkdir_p($root)
        ) {
            throw new RuntimeException(
                'course_storage_create_failed'
            );
        }

        @chmod($root, 0750);

        if (
            is_link($root)
            || !is_dir($root)
            || !is_writable($root)
        ) {
            throw new RuntimeException(
                'course_storage_not_writable'
            );
        }
    }

    private function normalizeStorageKey(
        string $storageKey
    ): string {
        $storageKey = trim(
            str_replace(
                '\\',
                '/',
                $storageKey
            ),
            '/'
        );

        if (
            $storageKey === ''
            || !str_starts_with(
                $storageKey,
                'courses/'
            )
            || str_contains(
                $storageKey,
                "\0"
            )
            || !preg_match(
                '~^[A-Za-z0-9._/-]+$~',
                $storageKey
            )
        ) {
            throw new RuntimeException(
                'course_storage_key_invalid'
            );
        }

        foreach (
            explode('/', $storageKey)
            as $segment
        ) {
            if (
                $segment === ''
                || $segment === '.'
                || $segment === '..'
            ) {
                throw new RuntimeException(
                    'course_storage_key_invalid'
                );
            }
        }

        return $storageKey;
    }
}