<?php

declare(strict_types=1);

namespace FacilDigital\Core\Courses;

use FacilDigital\Core\Core\Database;
use RuntimeException;

final class CourseModuleRepository
{
    public function __construct(
        private readonly CourseRepository $courses =
            new CourseRepository()
    ) {
    }

    public function create(
        int $courseId,
        string $title,
        string $description = '',
        int $sortOrder = 0
    ): int {
        global $wpdb;

        if (
            $courseId <= 0
            || $this->courses->findById(
                $courseId
            ) === null
        ) {
            throw new RuntimeException(
                'course_module_course_invalid'
            );
        }

        $title = sanitize_text_field($title);

        if ($title === '') {
            throw new RuntimeException(
                'course_module_title_invalid'
            );
        }

        $now = current_time('mysql', true);

        $inserted = $wpdb->insert(
            Database::table(
                'course_modules'
            ),
            [
                'course_id' =>
                    $courseId,
                'title' =>
                    $title,
                'description' =>
                    $description !== ''
                        ? sanitize_textarea_field(
                            $description
                        )
                        : null,
                'sort_order' =>
                    max(0, $sortOrder),
                'status' =>
                    'active',
                'created_at' =>
                    $now,
                'updated_at' =>
                    $now,
            ]
        );

        if ($inserted === false) {
            throw new RuntimeException(
                'course_module_create_failed'
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
            'course_modules'
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
    public function forCourse(
        int $courseId
    ): array {
        global $wpdb;

        $table = Database::table(
            'course_modules'
        );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE course_id = %d
                 ORDER BY sort_order ASC, id ASC",
                $courseId
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

        $current = $this->findById($id);

        if (!is_array($current)) {
            throw new RuntimeException(
                'course_module_missing'
            );
        }

        $update = [];

        if (array_key_exists('title', $data)) {
            $title = sanitize_text_field(
                (string) $data['title']
            );

            if ($title === '') {
                throw new RuntimeException(
                    'course_module_title_invalid'
                );
            }

            $update['title'] = $title;
        }

        if (
            array_key_exists(
                'description',
                $data
            )
        ) {
            $update['description'] =
                sanitize_textarea_field(
                    (string) $data[
                        'description'
                    ]
                );
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
                    'course_module_status_invalid'
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
                'course_modules'
            ),
            $update,
            ['id' => $id]
        );

        if ($result === false) {
            throw new RuntimeException(
                'course_module_update_failed'
            );
        }
    }

    public function delete(int $id): void
    {
        global $wpdb;

        $result = $wpdb->delete(
            Database::table(
                'course_modules'
            ),
            ['id' => $id],
            ['%d']
        );

        if ($result === false) {
            throw new RuntimeException(
                'course_module_delete_failed'
            );
        }
    }
}