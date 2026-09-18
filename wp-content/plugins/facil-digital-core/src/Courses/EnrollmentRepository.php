<?php

declare(strict_types=1);

namespace FacilDigital\Core\Courses;

use FacilDigital\Core\Core\Database;
use RuntimeException;

final class EnrollmentRepository
{
    public function __construct(
        private readonly CourseRepository $courses =
            new CourseRepository()
    ) {
    }

    public function grant(
        int $userId,
        int $courseId,
        int $productId,
        int $orderId,
        ?int $orderItemId = null,
        string $source = 'woocommerce'
    ): int {
        global $wpdb;

        if (
            $userId <= 0
            || get_userdata($userId) === false
        ) {
            throw new RuntimeException(
                'course_enrollment_user_invalid'
            );
        }

        $course = $this->courses->findById(
            $courseId
        );

        if (!is_array($course)) {
            throw new RuntimeException(
                'course_enrollment_course_invalid'
            );
        }

        if (
            $productId <= 0
            || (int) (
                $course['product_id']
                ?? 0
            ) !== $productId
        ) {
            throw new RuntimeException(
                'course_enrollment_product_invalid'
            );
        }

        if ($orderId <= 0) {
            throw new RuntimeException(
                'course_enrollment_order_invalid'
            );
        }

        if (
            $orderItemId !== null
            && $orderItemId <= 0
        ) {
            throw new RuntimeException(
                'course_enrollment_order_item_invalid'
            );
        }

        $source = sanitize_key($source);

        if ($source === '') {
            throw new RuntimeException(
                'course_enrollment_source_invalid'
            );
        }

        $existing =
            $this->findForUserCourseOrder(
                $userId,
                $courseId,
                $orderId
            );

        if (is_array($existing)) {
            return (int) (
                $existing['id']
                ?? 0
            );
        }

        $now = current_time('mysql', true);

        $inserted = $wpdb->insert(
            Database::table(
                'course_enrollments'
            ),
            [
                'user_id' =>
                    $userId,
                'course_id' =>
                    $courseId,
                'product_id' =>
                    $productId,
                'order_id' =>
                    $orderId,
                'order_item_id' =>
                    $orderItemId,
                'status' =>
                    'active',
                'source' =>
                    $source,
                'enrolled_at' =>
                    $now,
                'completed_at' =>
                    null,
                'revoked_at' =>
                    null,
                'expires_at' =>
                    null,
                'revocation_reason' =>
                    null,
                'created_at' =>
                    $now,
                'updated_at' =>
                    $now,
            ]
        );

        if ($inserted === false) {
            $concurrent =
                $this->findForUserCourseOrder(
                    $userId,
                    $courseId,
                    $orderId
                );

            if (is_array($concurrent)) {
                return (int) (
                    $concurrent['id']
                    ?? 0
                );
            }

            throw new RuntimeException(
                'course_enrollment_create_failed'
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
            'course_enrollments'
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
    public function findForUserCourseOrder(
        int $userId,
        int $courseId,
        int $orderId
    ): ?array {
        global $wpdb;

        $table = Database::table(
            'course_enrollments'
        );

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE user_id = %d
                   AND course_id = %d
                   AND order_id = %d
                 LIMIT 1",
                $userId,
                $courseId,
                $orderId
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
    public function forUser(
        int $userId,
        ?string $status = null
    ): array {
        global $wpdb;

        $table = Database::table(
            'course_enrollments'
        );

        if ($status === null) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table}
                     WHERE user_id = %d
                     ORDER BY enrolled_at DESC, id DESC",
                    $userId
                ),
                ARRAY_A
            );
        } else {
            $status = sanitize_key($status);

            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table}
                     WHERE user_id = %d
                       AND status = %s
                     ORDER BY enrolled_at DESC, id DESC",
                    $userId,
                    $status
                ),
                ARRAY_A
            );
        }

        return is_array($rows)
            ? $rows
            : [];
    }

    public function markCompleted(
        int $id,
        ?string $completedAt = null
    ): void {
        global $wpdb;

        if ($this->findById($id) === null) {
            throw new RuntimeException(
                'course_enrollment_missing'
            );
        }

        $now = current_time('mysql', true);

        $result = $wpdb->update(
            Database::table(
                'course_enrollments'
            ),
            [
                'status' =>
                    'completed',
                'completed_at' =>
                    $completedAt ?: $now,
                'revoked_at' =>
                    null,
                'revocation_reason' =>
                    null,
                'updated_at' =>
                    $now,
            ],
            ['id' => $id]
        );

        if ($result === false) {
            throw new RuntimeException(
                'course_enrollment_complete_failed'
            );
        }
    }

    public function revoke(
        int $id,
        string $reason
    ): void {
        global $wpdb;

        if ($this->findById($id) === null) {
            throw new RuntimeException(
                'course_enrollment_missing'
            );
        }

        $reason = sanitize_text_field($reason);

        if ($reason === '') {
            throw new RuntimeException(
                'course_enrollment_revocation_reason_invalid'
            );
        }

        $now = current_time('mysql', true);

        $result = $wpdb->update(
            Database::table(
                'course_enrollments'
            ),
            [
                'status' =>
                    'revoked',
                'revoked_at' =>
                    $now,
                'revocation_reason' =>
                    $reason,
                'updated_at' =>
                    $now,
            ],
            ['id' => $id]
        );

        if ($result === false) {
            throw new RuntimeException(
                'course_enrollment_revoke_failed'
            );
        }
    }

    public function reactivate(int $id): void
    {
        global $wpdb;

        if ($this->findById($id) === null) {
            throw new RuntimeException(
                'course_enrollment_missing'
            );
        }

        $result = $wpdb->update(
            Database::table(
                'course_enrollments'
            ),
            [
                'status' =>
                    'active',
                'completed_at' =>
                    null,
                'revoked_at' =>
                    null,
                'revocation_reason' =>
                    null,
                'updated_at' =>
                    current_time(
                        'mysql',
                        true
                    ),
            ],
            ['id' => $id]
        );

        if ($result === false) {
            throw new RuntimeException(
                'course_enrollment_reactivate_failed'
            );
        }
    }

    public function delete(int $id): void
    {
        global $wpdb;

        $result = $wpdb->delete(
            Database::table(
                'course_enrollments'
            ),
            ['id' => $id],
            ['%d']
        );

        if ($result === false) {
            throw new RuntimeException(
                'course_enrollment_delete_failed'
            );
        }
    }
}