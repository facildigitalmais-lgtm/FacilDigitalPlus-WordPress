<?php

declare(strict_types=1);

namespace FacilDigital\Core\Courses;

use FacilDigital\Core\Core\Database;
use RuntimeException;

final class CourseRepository
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(
        int $productId,
        string $title,
        string $slug,
        array $data = []
    ): int {
        global $wpdb;

        if ($productId <= 0) {
            throw new RuntimeException(
                'course_product_invalid'
            );
        }

        $title = sanitize_text_field($title);
        $slug = sanitize_title(
            $slug !== ''
                ? $slug
                : $title
        );

        if ($title === '' || $slug === '') {
            throw new RuntimeException(
                'course_identity_invalid'
            );
        }

        $threshold = isset(
            $data['completion_threshold']
        )
            ? (float) $data['completion_threshold']
            : 95.00;

        if (
            $threshold <= 0
            || $threshold > 100
        ) {
            throw new RuntimeException(
                'course_completion_threshold_invalid'
            );
        }

        $navigationMode =
            (string) (
                $data['navigation_mode']
                ?? 'free'
            );

        if (
            !in_array(
                $navigationMode,
                ['free', 'sequential'],
                true
            )
        ) {
            throw new RuntimeException(
                'course_navigation_mode_invalid'
            );
        }

        $status =
            (string) (
                $data['status']
                ?? 'draft'
            );

        if (
            !in_array(
                $status,
                ['draft', 'published', 'archived'],
                true
            )
        ) {
            throw new RuntimeException(
                'course_status_invalid'
            );
        }

        $now = current_time('mysql', true);
        $table = Database::table('courses');

        $inserted = $wpdb->insert(
            $table,
            [
                'product_id' =>
                    $productId,
                'title' =>
                    $title,
                'slug' =>
                    $slug,
                'short_description' =>
                    isset($data['short_description'])
                        ? sanitize_textarea_field(
                            (string) $data['short_description']
                        )
                        : null,
                'description' =>
                    isset($data['description'])
                        ? wp_kses_post(
                            (string) $data['description']
                        )
                        : null,
                'intro_youtube_video_id' =>
                    isset($data['intro_youtube_video_id'])
                        ? sanitize_text_field(
                            (string) $data['intro_youtube_video_id']
                        )
                        : null,
                'workload_minutes' =>
                    max(
                        0,
                        (int) (
                            $data['workload_minutes']
                            ?? 0
                        )
                    ),
                'completion_threshold' =>
                    $threshold,
                'navigation_mode' =>
                    $navigationMode,
                'certificate_enabled' =>
                    !isset(
                        $data['certificate_enabled']
                    )
                    || (bool) $data[
                        'certificate_enabled'
                    ]
                        ? 1
                        : 0,
                'status' =>
                    $status,
                'created_by' =>
                    isset($data['created_by'])
                        ? max(
                            0,
                            (int) $data['created_by']
                        )
                        : null,
                'published_at' =>
                    isset($data['published_at'])
                        && $data['published_at'] !== ''
                            ? (string) $data[
                                'published_at'
                            ]
                            : null,
                'created_at' =>
                    $now,
                'updated_at' =>
                    $now,
            ]
        );

        if ($inserted === false) {
            throw new RuntimeException(
                'course_create_failed'
            );
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->findBy(
            'id',
            $id,
            '%d'
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByProductId(
        int $productId
    ): ?array {
        return $this->findBy(
            'product_id',
            $productId,
            '%d'
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(
        string $slug
    ): ?array {
        return $this->findBy(
            'slug',
            sanitize_title($slug),
            '%s'
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(
        int $id,
        array $data
    ): void {
        global $wpdb;

        if ($id <= 0) {
            throw new RuntimeException(
                'course_id_invalid'
            );
        }

        $update = [];

        if (array_key_exists('title', $data)) {
            $title = sanitize_text_field(
                (string) $data['title']
            );

            if ($title === '') {
                throw new RuntimeException(
                    'course_title_invalid'
                );
            }

            $update['title'] = $title;
        }

        if (array_key_exists('slug', $data)) {
            $slug = sanitize_title(
                (string) $data['slug']
            );

            if ($slug === '') {
                throw new RuntimeException(
                    'course_slug_invalid'
                );
            }

            $update['slug'] = $slug;
        }

        if (
            array_key_exists(
                'short_description',
                $data
            )
        ) {
            $update['short_description'] =
                sanitize_textarea_field(
                    (string) $data[
                        'short_description'
                    ]
                );
        }

        if (
            array_key_exists(
                'description',
                $data
            )
        ) {
            $update['description'] =
                wp_kses_post(
                    (string) $data['description']
                );
        }

        if (
            array_key_exists(
                'workload_minutes',
                $data
            )
        ) {
            $update['workload_minutes'] =
                max(
                    0,
                    (int) $data[
                        'workload_minutes'
                    ]
                );
        }

        if (
            array_key_exists(
                'completion_threshold',
                $data
            )
        ) {
            $threshold =
                (float) $data[
                    'completion_threshold'
                ];

            if (
                $threshold <= 0
                || $threshold > 100
            ) {
                throw new RuntimeException(
                    'course_completion_threshold_invalid'
                );
            }

            $update['completion_threshold'] =
                $threshold;
        }

        if (
            array_key_exists(
                'navigation_mode',
                $data
            )
        ) {
            $navigationMode =
                (string) $data[
                    'navigation_mode'
                ];

            if (
                !in_array(
                    $navigationMode,
                    ['free', 'sequential'],
                    true
                )
            ) {
                throw new RuntimeException(
                    'course_navigation_mode_invalid'
                );
            }

            $update['navigation_mode'] =
                $navigationMode;
        }

        if (
            array_key_exists(
                'certificate_enabled',
                $data
            )
        ) {
            $update['certificate_enabled'] =
                (bool) $data[
                    'certificate_enabled'
                ]
                    ? 1
                    : 0;
        }

        if (
            array_key_exists(
                'intro_youtube_video_id',
                $data
            )
        ) {
            $videoId = sanitize_text_field(
                (string) $data['intro_youtube_video_id']
            );

            $update['intro_youtube_video_id'] =
                $videoId !== ''
                    ? $videoId
                    : null;
        }

        if (array_key_exists('status', $data)) {
            $status =
                (string) $data['status'];

            if (
                !in_array(
                    $status,
                    [
                        'draft',
                        'published',
                        'archived',
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'course_status_invalid'
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
            Database::table('courses'),
            $update,
            ['id' => $id]
        );

        if ($result === false) {
            throw new RuntimeException(
                'course_update_failed'
            );
        }
    }

    public function delete(int $id): void
    {
        global $wpdb;

        $result = $wpdb->delete(
            Database::table('courses'),
            ['id' => $id],
            ['%d']
        );

        if ($result === false) {
            throw new RuntimeException(
                'course_delete_failed'
            );
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findBy(
        string $column,
        int|string $value,
        string $format
    ): ?array {
        global $wpdb;

        $allowedColumns = [
            'id',
            'product_id',
            'slug',
        ];

        if (
            !in_array(
                $column,
                $allowedColumns,
                true
            )
        ) {
            throw new RuntimeException(
                'course_lookup_invalid'
            );
        }

        $table = Database::table('courses');
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE {$column} = {$format}
                 LIMIT 1",
                $value
            ),
            ARRAY_A
        );

        return is_array($row)
            ? $row
            : null;
    }
}