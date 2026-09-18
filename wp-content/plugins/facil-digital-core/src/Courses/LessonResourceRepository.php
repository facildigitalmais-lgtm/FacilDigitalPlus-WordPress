<?php

declare(strict_types=1);

namespace FacilDigital\Core\Courses;

use FacilDigital\Core\Core\Database;
use RuntimeException;

final class LessonResourceRepository
{
    public function __construct(
        private readonly LessonRepository $lessons =
            new LessonRepository()
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(
        int $courseId,
        int $lessonId,
        string $title,
        string $storageKey,
        string $originalFilename,
        string $mimeType,
        array $data = []
    ): int {
        global $wpdb;

        $lesson = $this->lessons->findById(
            $lessonId
        );

        if (
            !is_array($lesson)
            || (int) (
                $lesson['course_id']
                ?? 0
            ) !== $courseId
        ) {
            throw new RuntimeException(
                'lesson_resource_lesson_invalid'
            );
        }

        $title = sanitize_text_field($title);

        if ($title === '') {
            throw new RuntimeException(
                'lesson_resource_title_invalid'
            );
        }

        $storageKey = $this->normalizeStorageKey(
            $storageKey
        );

        $originalFilename =
            sanitize_file_name(
                $originalFilename
            );

        if ($originalFilename === '') {
            throw new RuntimeException(
                'lesson_resource_filename_invalid'
            );
        }

        $mimeType = sanitize_mime_type(
            $mimeType
        );

        if ($mimeType === '') {
            throw new RuntimeException(
                'lesson_resource_mime_invalid'
            );
        }

        $sha256 = isset($data['sha256'])
            ? strtolower(
                trim(
                    (string) $data['sha256']
                )
            )
            : null;

        if (
            $sha256 !== null
            && $sha256 !== ''
            && !preg_match(
                '/^[a-f0-9]{64}$/',
                $sha256
            )
        ) {
            throw new RuntimeException(
                'lesson_resource_sha256_invalid'
            );
        }

        if ($sha256 === '') {
            $sha256 = null;
        }

        $status =
            (string) (
                $data['status']
                ?? 'active'
            );

        if (
            !in_array(
                $status,
                ['active', 'hidden'],
                true
            )
        ) {
            throw new RuntimeException(
                'lesson_resource_status_invalid'
            );
        }

        $fileSize = array_key_exists(
            'file_size',
            $data
        )
            ? max(
                0,
                (int) $data['file_size']
            )
            : null;

        $now = current_time('mysql', true);

        $inserted = $wpdb->insert(
            Database::table(
                'lesson_resources'
            ),
            [
                'course_id' =>
                    $courseId,
                'lesson_id' =>
                    $lessonId,
                'title' =>
                    $title,
                'storage_key' =>
                    $storageKey,
                'original_filename' =>
                    $originalFilename,
                'mime_type' =>
                    $mimeType,
                'file_size' =>
                    $fileSize,
                'sha256' =>
                    $sha256,
                'sort_order' =>
                    max(
                        0,
                        (int) (
                            $data['sort_order']
                            ?? 0
                        )
                    ),
                'status' =>
                    $status,
                'created_at' =>
                    $now,
                'updated_at' =>
                    $now,
            ]
        );

        if ($inserted === false) {
            throw new RuntimeException(
                'lesson_resource_create_failed'
            );
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        global $wpdb;

        $table = Database::table(
            'lesson_resources'
        );

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE id = %d
                 LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        return is_array($row)
            ? $row
            : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forLesson(
        int $lessonId
    ): array {
        global $wpdb;

        $table = Database::table(
            'lesson_resources'
        );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE lesson_id = %d
                 ORDER BY sort_order ASC, id ASC",
                $lessonId
            ),
            ARRAY_A
        );

        return is_array($rows)
            ? $rows
            : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(
        int $id,
        array $data
    ): void {
        global $wpdb;

        if ($this->findById($id) === null) {
            throw new RuntimeException(
                'lesson_resource_missing'
            );
        }

        $update = [];

        if (array_key_exists('title', $data)) {
            $title = sanitize_text_field(
                (string) $data['title']
            );

            if ($title === '') {
                throw new RuntimeException(
                    'lesson_resource_title_invalid'
                );
            }

            $update['title'] = $title;
        }

        if (
            array_key_exists(
                'storage_key',
                $data
            )
        ) {
            $update['storage_key'] =
                $this->normalizeStorageKey(
                    (string) $data[
                        'storage_key'
                    ]
                );
        }

        if (
            array_key_exists(
                'original_filename',
                $data
            )
        ) {
            $filename =
                sanitize_file_name(
                    (string) $data[
                        'original_filename'
                    ]
                );

            if ($filename === '') {
                throw new RuntimeException(
                    'lesson_resource_filename_invalid'
                );
            }

            $update['original_filename'] =
                $filename;
        }

        if (
            array_key_exists(
                'mime_type',
                $data
            )
        ) {
            $mimeType =
                sanitize_mime_type(
                    (string) $data['mime_type']
                );

            if ($mimeType === '') {
                throw new RuntimeException(
                    'lesson_resource_mime_invalid'
                );
            }

            $update['mime_type'] =
                $mimeType;
        }

        if (
            array_key_exists(
                'file_size',
                $data
            )
        ) {
            $update['file_size'] =
                $data['file_size'] === null
                    ? null
                    : max(
                        0,
                        (int) $data[
                            'file_size'
                        ]
                    );
        }

        if (array_key_exists('sha256', $data)) {
            $sha256 = strtolower(
                trim(
                    (string) $data['sha256']
                )
            );

            if (
                $sha256 !== ''
                && !preg_match(
                    '/^[a-f0-9]{64}$/',
                    $sha256
                )
            ) {
                throw new RuntimeException(
                    'lesson_resource_sha256_invalid'
                );
            }

            $update['sha256'] =
                $sha256 !== ''
                    ? $sha256
                    : null;
        }

        if (
            array_key_exists(
                'sort_order',
                $data
            )
        ) {
            $update['sort_order'] =
                max(
                    0,
                    (int) $data['sort_order']
                );
        }

        if (array_key_exists('status', $data)) {
            $status =
                (string) $data['status'];

            if (
                !in_array(
                    $status,
                    ['active', 'hidden'],
                    true
                )
            ) {
                throw new RuntimeException(
                    'lesson_resource_status_invalid'
                );
            }

            $update['status'] = $status;
        }

        if ($update === []) {
            return;
        }

        $update['updated_at'] =
            current_time('mysql', true);

        $result = $wpdb->update(
            Database::table(
                'lesson_resources'
            ),
            $update,
            ['id' => $id]
        );

        if ($result === false) {
            throw new RuntimeException(
                'lesson_resource_update_failed'
            );
        }
    }

    public function delete(int $id): void
    {
        global $wpdb;

        $result = $wpdb->delete(
            Database::table(
                'lesson_resources'
            ),
            ['id' => $id],
            ['%d']
        );

        if ($result === false) {
            throw new RuntimeException(
                'lesson_resource_delete_failed'
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

        if ($storageKey === '') {
            throw new RuntimeException(
                'lesson_resource_storage_key_invalid'
            );
        }

        $segments = explode(
            '/',
            $storageKey
        );

        foreach ($segments as $segment) {
            if (
                $segment === ''
                || $segment === '.'
                || $segment === '..'
            ) {
                throw new RuntimeException(
                    'lesson_resource_storage_key_invalid'
                );
            }
        }

        return implode(
            '/',
            $segments
        );
    }
}