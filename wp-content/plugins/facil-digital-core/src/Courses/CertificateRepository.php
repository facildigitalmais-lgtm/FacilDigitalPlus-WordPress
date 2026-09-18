<?php

declare(strict_types=1);

namespace FacilDigital\Core\Courses;

use FacilDigital\Core\Core\Database;
use RuntimeException;

final class CertificateRepository
{
    public function __construct(
        private readonly EnrollmentRepository $enrollments =
            new EnrollmentRepository()
    ) {
    }

    public function createPending(
        int $enrollmentId,
        string $studentName,
        string $courseTitle,
        int $workloadMinutes,
        ?string $verificationCode = null
    ): int {
        global $wpdb;

        $enrollment =
            $this->enrollments->findById(
                $enrollmentId
            );

        if (!is_array($enrollment)) {
            throw new RuntimeException(
                'certificate_enrollment_invalid'
            );
        }

        if (
            (string) (
                $enrollment['status']
                ?? ''
            ) !== 'completed'
            || empty(
                $enrollment['completed_at']
            )
        ) {
            throw new RuntimeException(
                'certificate_enrollment_not_completed'
            );
        }

        $existing =
            $this->findByEnrollmentId(
                $enrollmentId
            );

        if (is_array($existing)) {
            return (int) (
                $existing['id']
                ?? 0
            );
        }

        $studentName =
            sanitize_text_field(
                $studentName
            );

        $courseTitle =
            sanitize_text_field(
                $courseTitle
            );

        if (
            $studentName === ''
            || $courseTitle === ''
        ) {
            throw new RuntimeException(
                'certificate_snapshot_invalid'
            );
        }

        if ($workloadMinutes < 0) {
            throw new RuntimeException(
                'certificate_workload_invalid'
            );
        }

        if ($verificationCode === null) {
            $verificationCode =
                $this->generateVerificationCode();
        } else {
            $verificationCode =
                $this->normalizeVerificationCode(
                    $verificationCode
                );

            $duplicate =
                $this->findByVerificationCode(
                    $verificationCode
                );

            if (is_array($duplicate)) {
                throw new RuntimeException(
                    'certificate_verification_code_duplicate'
                );
            }
        }

        $now = current_time('mysql', true);

        $inserted = $wpdb->insert(
            Database::table(
                'certificates'
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
                'verification_code' =>
                    $verificationCode,
                'student_name_snapshot' =>
                    $studentName,
                'course_title_snapshot' =>
                    $courseTitle,
                'workload_minutes_snapshot' =>
                    $workloadMinutes,
                'completed_at' =>
                    (string) $enrollment[
                        'completed_at'
                    ],
                'issued_at' =>
                    null,
                'storage_key' =>
                    null,
                'file_size' =>
                    null,
                'sha256' =>
                    null,
                'status' =>
                    'pending',
                'generation_attempts' =>
                    0,
                'error_code' =>
                    null,
                'created_at' =>
                    $now,
                'updated_at' =>
                    $now,
            ]
        );

        if ($inserted === false) {
            $concurrent =
                $this->findByEnrollmentId(
                    $enrollmentId
                );

            if (is_array($concurrent)) {
                return (int) (
                    $concurrent['id']
                    ?? 0
                );
            }

            throw new RuntimeException(
                'certificate_create_failed'
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
            'certificates'
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
    public function findByEnrollmentId(
        int $enrollmentId
    ): ?array {
        global $wpdb;

        $table = Database::table(
            'certificates'
        );

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE enrollment_id = %d
                 LIMIT 1",
                $enrollmentId
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
    public function findByVerificationCode(
        string $verificationCode
    ): ?array {
        global $wpdb;

        $verificationCode =
            $this->normalizeVerificationCode(
                $verificationCode
            );

        $table = Database::table(
            'certificates'
        );

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE verification_code = %s
                 LIMIT 1",
                $verificationCode
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
    public function forUser(int $userId): array
    {
        global $wpdb;

        $table = Database::table(
            'certificates'
        );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE user_id = %d
                 ORDER BY completed_at DESC, id DESC",
                $userId
            ),
            ARRAY_A
        );

        return is_array($rows)
            ? $rows
            : [];
    }

    public function recordGenerationAttempt(
        int $id
    ): int {
        global $wpdb;

        $current = $this->findById($id);

        if (!is_array($current)) {
            throw new RuntimeException(
                'certificate_missing'
            );
        }

        if (
            (string) (
                $current['status']
                ?? ''
            ) === 'ready'
        ) {
            return (int) (
                $current['generation_attempts']
                ?? 0
            );
        }

        $table = Database::table(
            'certificates'
        );

        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                 SET generation_attempts =
                         generation_attempts + 1,
                     status = %s,
                     error_code = NULL,
                     updated_at = %s
                 WHERE id = %d",
                'pending',
                current_time(
                    'mysql',
                    true
                ),
                $id
            )
        );

        if ($updated === false) {
            throw new RuntimeException(
                'certificate_attempt_failed'
            );
        }

        $after = $this->findById($id);

        return (int) (
            $after['generation_attempts']
            ?? 0
        );
    }

    public function markFailed(
        int $id,
        string $errorCode
    ): void {
        global $wpdb;

        $current = $this->findById($id);

        if (!is_array($current)) {
            throw new RuntimeException(
                'certificate_missing'
            );
        }

        if (
            (string) (
                $current['status']
                ?? ''
            ) === 'ready'
        ) {
            throw new RuntimeException(
                'certificate_already_ready'
            );
        }

        $errorCode = sanitize_key(
            $errorCode
        );

        if ($errorCode === '') {
            throw new RuntimeException(
                'certificate_error_code_invalid'
            );
        }

        $result = $wpdb->update(
            Database::table(
                'certificates'
            ),
            [
                'status' =>
                    'failed',
                'error_code' =>
                    $errorCode,
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
                'certificate_fail_update_failed'
            );
        }
    }

    public function markReady(
        int $id,
        string $storageKey,
        int $fileSize,
        string $sha256,
        ?string $issuedAt = null
    ): void {
        global $wpdb;

        $current = $this->findById($id);

        if (!is_array($current)) {
            throw new RuntimeException(
                'certificate_missing'
            );
        }

        if (
            (string) (
                $current['status']
                ?? ''
            ) === 'ready'
        ) {
            return;
        }

        $storageKey =
            $this->normalizeStorageKey(
                $storageKey
            );

        if ($fileSize < 0) {
            throw new RuntimeException(
                'certificate_file_size_invalid'
            );
        }

        $sha256 = strtolower(
            trim($sha256)
        );

        if (
            !preg_match(
                '/^[a-f0-9]{64}$/',
                $sha256
            )
        ) {
            throw new RuntimeException(
                'certificate_sha256_invalid'
            );
        }

        $now = current_time('mysql', true);

        $result = $wpdb->update(
            Database::table(
                'certificates'
            ),
            [
                'status' =>
                    'ready',
                'issued_at' =>
                    $issuedAt ?: $now,
                'storage_key' =>
                    $storageKey,
                'file_size' =>
                    $fileSize,
                'sha256' =>
                    $sha256,
                'error_code' =>
                    null,
                'updated_at' =>
                    $now,
            ],
            ['id' => $id]
        );

        if ($result === false) {
            throw new RuntimeException(
                'certificate_ready_update_failed'
            );
        }
    }

    public function delete(int $id): void
    {
        global $wpdb;

        $result = $wpdb->delete(
            Database::table(
                'certificates'
            ),
            ['id' => $id],
            ['%d']
        );

        if ($result === false) {
            throw new RuntimeException(
                'certificate_delete_failed'
            );
        }
    }

    private function generateVerificationCode(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code =
                'FD-'
                . strtoupper(
                    str_replace(
                        '-',
                        '',
                        wp_generate_uuid4()
                    )
                );

            if (
                $this->findByVerificationCode(
                    $code
                ) === null
            ) {
                return $code;
            }
        }

        throw new RuntimeException(
            'certificate_verification_code_generation_failed'
        );
    }

    private function normalizeVerificationCode(
        string $verificationCode
    ): string {
        $verificationCode =
            strtoupper(
                trim($verificationCode)
            );

        if (
            !preg_match(
                '/^[A-Z0-9-]{8,64}$/',
                $verificationCode
            )
        ) {
            throw new RuntimeException(
                'certificate_verification_code_invalid'
            );
        }

        return $verificationCode;
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
                'certificate_storage_key_invalid'
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
                    'certificate_storage_key_invalid'
                );
            }
        }

        return implode(
            '/',
            $segments
        );
    }
}