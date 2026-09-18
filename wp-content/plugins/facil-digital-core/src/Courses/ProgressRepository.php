<?php

declare(strict_types=1);

namespace FacilDigital\Core\Courses;

use FacilDigital\Core\Core\Database;
use RuntimeException;

final class ProgressRepository
{
    public function __construct(
        private readonly EnrollmentRepository $enrollments =
            new EnrollmentRepository(),
        private readonly LessonRepository $lessons =
            new LessonRepository()
    ) {
    }

    public function getOrCreate(
        int $enrollmentId,
        int $lessonId
    ): int {
        global $wpdb;

        $enrollment =
            $this->enrollments->findById(
                $enrollmentId
            );

        if (!is_array($enrollment)) {
            throw new RuntimeException(
                'lesson_progress_enrollment_invalid'
            );
        }

        $lesson = $this->lessons->findById(
            $lessonId
        );

        if (
            !is_array($lesson)
            || (int) (
                $lesson['course_id']
                ?? 0
            ) !== (int) (
                $enrollment['course_id']
                ?? 0
            )
        ) {
            throw new RuntimeException(
                'lesson_progress_lesson_invalid'
            );
        }

        $existing =
            $this->findForEnrollmentLesson(
                $enrollmentId,
                $lessonId
            );

        if (is_array($existing)) {
            return (int) (
                $existing['id']
                ?? 0
            );
        }

        if (
            (string) (
                $enrollment['status']
                ?? ''
            ) !== 'active'
        ) {
            throw new RuntimeException(
                'lesson_progress_enrollment_inactive'
            );
        }

        $now = current_time('mysql', true);

        $inserted = $wpdb->insert(
            Database::table(
                'lesson_progress'
            ),
            [
                'enrollment_id' =>
                    $enrollmentId,
                'user_id' =>
                    (int) (
                        $enrollment['user_id']
                        ?? 0
                    ),
                'course_id' =>
                    (int) (
                        $enrollment['course_id']
                        ?? 0
                    ),
                'lesson_id' =>
                    $lessonId,
                'status' =>
                    'not_started',
                'watched_seconds' =>
                    0,
                'last_position_seconds' =>
                    0,
                'duration_seconds' =>
                    0,
                'completion_percent' =>
                    0.00,
                'started_at' =>
                    null,
                'last_activity_at' =>
                    null,
                'completed_at' =>
                    null,
                'created_at' =>
                    $now,
                'updated_at' =>
                    $now,
            ]
        );

        if ($inserted === false) {
            $concurrent =
                $this->findForEnrollmentLesson(
                    $enrollmentId,
                    $lessonId
                );

            if (is_array($concurrent)) {
                return (int) (
                    $concurrent['id']
                    ?? 0
                );
            }

            throw new RuntimeException(
                'lesson_progress_create_failed'
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
            'lesson_progress'
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
     * @return array<string, mixed>|null
     */
    public function findForEnrollmentLesson(
        int $enrollmentId,
        int $lessonId
    ): ?array {
        global $wpdb;

        $table = Database::table(
            'lesson_progress'
        );

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE enrollment_id = %d
                   AND lesson_id = %d
                 LIMIT 1",
                $enrollmentId,
                $lessonId
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
    public function forEnrollment(
        int $enrollmentId
    ): array {
        global $wpdb;

        $table = Database::table(
            'lesson_progress'
        );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE enrollment_id = %d
                 ORDER BY id ASC",
                $enrollmentId
            ),
            ARRAY_A
        );

        return is_array($rows)
            ? $rows
            : [];
    }

    public function start(int $id): void
    {
        global $wpdb;

        $current = $this->findById($id);

        if (!is_array($current)) {
            throw new RuntimeException(
                'lesson_progress_missing'
            );
        }

        if (
            (string) (
                $current['status']
                ?? ''
            ) === 'completed'
        ) {
            return;
        }

        $now = current_time('mysql', true);

        $result = $wpdb->update(
            Database::table(
                'lesson_progress'
            ),
            [
                'status' =>
                    'in_progress',
                'started_at' =>
                    !empty(
                        $current['started_at']
                    )
                        ? (string) $current[
                            'started_at'
                        ]
                        : $now,
                'last_activity_at' =>
                    $now,
                'updated_at' =>
                    $now,
            ],
            ['id' => $id]
        );

        if ($result === false) {
            throw new RuntimeException(
                'lesson_progress_start_failed'
            );
        }
    }

    public function recordMetrics(
        int $id,
        int $watchedSeconds,
        int $lastPositionSeconds,
        int $durationSeconds,
        float $completionPercent
    ): void {
        global $wpdb;

        $current = $this->findById($id);

        if (!is_array($current)) {
            throw new RuntimeException(
                'lesson_progress_missing'
            );
        }

        if (
            (string) (
                $current['status']
                ?? ''
            ) === 'completed'
        ) {
            return;
        }

        if (
            $watchedSeconds < 0
            || $lastPositionSeconds < 0
            || $durationSeconds < 0
        ) {
            throw new RuntimeException(
                'lesson_progress_metrics_invalid'
            );
        }

        if (
            $durationSeconds > 0
            && $lastPositionSeconds
                > $durationSeconds
        ) {
            throw new RuntimeException(
                'lesson_progress_position_invalid'
            );
        }

        if (
            $completionPercent < 0
            || $completionPercent > 100
        ) {
            throw new RuntimeException(
                'lesson_progress_percent_invalid'
            );
        }

        $now = current_time('mysql', true);

        $result = $wpdb->update(
            Database::table(
                'lesson_progress'
            ),
            [
                'status' =>
                    'in_progress',
                'watched_seconds' =>
                    $watchedSeconds,
                'last_position_seconds' =>
                    $lastPositionSeconds,
                'duration_seconds' =>
                    $durationSeconds,
                'completion_percent' =>
                    $completionPercent,
                'started_at' =>
                    !empty(
                        $current['started_at']
                    )
                        ? (string) $current[
                            'started_at'
                        ]
                        : $now,
                'last_activity_at' =>
                    $now,
                'updated_at' =>
                    $now,
            ],
            ['id' => $id]
        );

        if ($result === false) {
            throw new RuntimeException(
                'lesson_progress_metrics_update_failed'
            );
        }
    }

    public function markCompleted(
        int $id,
        ?string $completedAt = null
    ): void {
        global $wpdb;

        $current = $this->findById($id);

        if (!is_array($current)) {
            throw new RuntimeException(
                'lesson_progress_missing'
            );
        }

        if (
            (string) (
                $current['status']
                ?? ''
            ) === 'completed'
        ) {
            return;
        }

        $now = current_time('mysql', true);
        $completedAt = $completedAt ?: $now;

        $result = $wpdb->update(
            Database::table(
                'lesson_progress'
            ),
            [
                'status' =>
                    'completed',
                'completion_percent' =>
                    100.00,
                'started_at' =>
                    !empty(
                        $current['started_at']
                    )
                        ? (string) $current[
                            'started_at'
                        ]
                        : $now,
                'last_activity_at' =>
                    $now,
                'completed_at' =>
                    $completedAt,
                'updated_at' =>
                    $now,
            ],
            ['id' => $id]
        );

        if ($result === false) {
            throw new RuntimeException(
                'lesson_progress_complete_failed'
            );
        }
    }

    public function delete(int $id): void
    {
        global $wpdb;

        $result = $wpdb->delete(
            Database::table(
                'lesson_progress'
            ),
            ['id' => $id],
            ['%d']
        );

        if ($result === false) {
            throw new RuntimeException(
                'lesson_progress_delete_failed'
            );
        }
    }
}