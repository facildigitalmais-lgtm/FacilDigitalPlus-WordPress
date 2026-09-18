<?php

declare(strict_types=1);

namespace FacilDigital\Core\Courses;

use FacilDigital\Core\Core\Database;
use RuntimeException;

final class LessonRepository
{
    public function __construct(
        private readonly CourseRepository $courses =
            new CourseRepository(),
        private readonly CourseModuleRepository $modules =
            new CourseModuleRepository()
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(
        int $courseId,
        int $moduleId,
        string $title,
        string $slug,
        array $data = []
    ): int {
        global $wpdb;

        if (
            $courseId <= 0
            || $this->courses->findById(
                $courseId
            ) === null
        ) {
            throw new RuntimeException(
                'lesson_course_invalid'
            );
        }

        $module = $this->modules->findById(
            $moduleId
        );

        if (
            !is_array($module)
            || (int) (
                $module['course_id']
                ?? 0
            ) !== $courseId
        ) {
            throw new RuntimeException(
                'lesson_module_invalid'
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
                'lesson_identity_invalid'
            );
        }

        $table = Database::table(
            'course_lessons'
        );

        $existingId = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table}
                 WHERE course_id = %d
                   AND slug = %s
                 LIMIT 1",
                $courseId,
                $slug
            )
        );

        if ($existingId !== null) {
            throw new RuntimeException(
                'lesson_slug_duplicate'
            );
        }

        $lessonType =
            (string) (
                $data['lesson_type']
                ?? 'text'
            );

        if (
            !in_array(
                $lessonType,
                ['text', 'video', 'mixed'],
                true
            )
        ) {
            throw new RuntimeException(
                'lesson_type_invalid'
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
                'lesson_status_invalid'
            );
        }

        $videoId =
            isset($data['youtube_video_id'])
                ? sanitize_text_field(
                    (string) $data[
                        'youtube_video_id'
                    ]
                )
                : null;

        if ($videoId === '') {
            $videoId = null;
        }

        $now = current_time('mysql', true);

        $inserted = $wpdb->insert(
            $table,
            [
                'course_id' =>
                    $courseId,
                'module_id' =>
                    $moduleId,
                'title' =>
                    $title,
                'slug' =>
                    $slug,
                'lesson_type' =>
                    $lessonType,
                'content' =>
                    isset($data['content'])
                        ? wp_kses_post(
                            (string) $data[
                                'content'
                            ]
                        )
                        : null,
                'transcript' =>
                    isset($data['transcript'])
                        ? wp_kses_post(
                            (string) $data[
                                'transcript'
                            ]
                        )
                        : null,
                'youtube_video_id' =>
                    $videoId,
                'duration_seconds' =>
                    max(
                        0,
                        (int) (
                            $data[
                                'duration_seconds'
                            ]
                            ?? 0
                        )
                    ),
                'is_required' =>
                    !isset($data['is_required'])
                    || (bool) $data[
                        'is_required'
                    ]
                        ? 1
                        : 0,
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
                'lesson_create_failed'
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
            'course_lessons'
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
    public function forModule(
        int $moduleId
    ): array {
        global $wpdb;

        $table = Database::table(
            'course_lessons'
        );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE module_id = %d
                 ORDER BY sort_order ASC, id ASC",
                $moduleId
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
                'lesson_missing'
            );
        }

        $update = [];

        if (array_key_exists('title', $data)) {
            $title = sanitize_text_field(
                (string) $data['title']
            );

            if ($title === '') {
                throw new RuntimeException(
                    'lesson_title_invalid'
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
                    'lesson_slug_invalid'
                );
            }

            $update['slug'] = $slug;
        }

        if (
            array_key_exists(
                'lesson_type',
                $data
            )
        ) {
            $lessonType =
                (string) $data[
                    'lesson_type'
                ];

            if (
                !in_array(
                    $lessonType,
                    [
                        'text',
                        'video',
                        'mixed',
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'lesson_type_invalid'
                );
            }

            $update['lesson_type'] =
                $lessonType;
        }

        if (array_key_exists('content', $data)) {
            $update['content'] =
                wp_kses_post(
                    (string) $data['content']
                );
        }

        if (
            array_key_exists(
                'transcript',
                $data
            )
        ) {
            $update['transcript'] =
                wp_kses_post(
                    (string) $data[
                        'transcript'
                    ]
                );
        }

        if (
            array_key_exists(
                'youtube_video_id',
                $data
            )
        ) {
            $videoId =
                sanitize_text_field(
                    (string) $data[
                        'youtube_video_id'
                    ]
                );

            $update['youtube_video_id'] =
                $videoId !== ''
                    ? $videoId
                    : null;
        }

        if (
            array_key_exists(
                'duration_seconds',
                $data
            )
        ) {
            $update['duration_seconds'] =
                max(
                    0,
                    (int) $data[
                        'duration_seconds'
                    ]
                );
        }

        if (
            array_key_exists(
                'is_required',
                $data
            )
        ) {
            $update['is_required'] =
                (bool) $data[
                    'is_required'
                ]
                    ? 1
                    : 0;
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
                    [
                        'draft',
                        'published',
                        'archived',
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'lesson_status_invalid'
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
                'course_lessons'
            ),
            $update,
            ['id' => $id]
        );

        if ($result === false) {
            throw new RuntimeException(
                'lesson_update_failed'
            );
        }
    }

    public function delete(int $id): void
    {
        global $wpdb;

        $result = $wpdb->delete(
            Database::table(
                'course_lessons'
            ),
            ['id' => $id],
            ['%d']
        );

        if ($result === false) {
            throw new RuntimeException(
                'lesson_delete_failed'
            );
        }
    }
}