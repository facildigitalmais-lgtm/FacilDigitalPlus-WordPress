<?php

declare(strict_types=1);

use FacilDigital\Core\Core\Database;
use FacilDigital\Core\Courses\CertificateRepository;
use FacilDigital\Core\Courses\CourseModuleRepository;
use FacilDigital\Core\Courses\CourseRepository;
use FacilDigital\Core\Courses\EnrollmentRepository;
use FacilDigital\Core\Courses\LessonRepository;
use FacilDigital\Core\Courses\ProgressRepository;

if (wp_get_environment_type() !== 'development') {
    throw new RuntimeException(
        'Este teste so pode rodar em development.'
    );
}

global $wpdb;

$userIds = [];
$courseIds = [];
$moduleIds = [];
$lessonIds = [];
$enrollmentIds = [];
$progressIds = [];
$certificateIds = [];
$failure = null;

$assert = static function (
    bool $condition,
    string $label
): void {
    if (!$condition) {
        throw new RuntimeException(
            'FAIL: ' . $label
        );
    }

    echo 'PASS: ' . $label . PHP_EOL;
};

$expectRuntimeException =
    static function (
        callable $callback,
        string $message,
        string $label
    ) use ($assert): void {
        $matched = false;

        try {
            $callback();
        } catch (RuntimeException $exception) {
            $matched =
                $exception->getMessage()
                === $message;
        }

        $assert(
            $matched,
            $label
        );
    };

$courses = new CourseRepository();
$modules = new CourseModuleRepository(
    $courses
);
$lessons = new LessonRepository(
    $courses,
    $modules
);
$enrollments = new EnrollmentRepository(
    $courses
);
$progress = new ProgressRepository(
    $enrollments,
    $lessons
);
$certificates = new CertificateRepository(
    $enrollments
);

try {
    $suffix = strtolower(
        wp_generate_password(
            8,
            false,
            false
        )
    );

    $userId = wp_insert_user(
        [
            'user_login' =>
                'fd_lms_progress_' . $suffix,
            'user_pass' =>
                wp_generate_password(
                    24,
                    true,
                    true
                ),
            'user_email' =>
                'fd-progress-' . $suffix
                . '@example.com',
            'display_name' =>
                'FD LMS Progress Test',
            'role' =>
                'customer',
        ]
    );

    if (is_wp_error($userId)) {
        throw new RuntimeException(
            'fixture_user_create_failed'
        );
    }

    $userId = (int) $userId;
    $userIds[] = $userId;

    $productId =
        940000000 + wp_rand(
            1000,
            999999
        );

    $courseId = $courses->create(
        $productId,
        'FD LMS Progress Course',
        'fd-lms-progress-' . $suffix,
        [
            'workload_minutes' =>
                600,
        ]
    );

    $courseIds[] = $courseId;

    $moduleId = $modules->create(
        $courseId,
        'Modulo Principal'
    );

    $moduleIds[] = $moduleId;

    $lessonId = $lessons->create(
        $courseId,
        $moduleId,
        'Aula Principal',
        'aula-progress-' . $suffix,
        [
            'lesson_type' =>
                'video',
            'youtube_video_id' =>
                'dQw4w9WgXcQ',
            'duration_seconds' =>
                300,
            'status' =>
                'published',
        ]
    );

    $lessonIds[] = $lessonId;

    $orderId =
        950000000 + wp_rand(
            1000,
            999999
        );

    $enrollmentId = $enrollments->grant(
        $userId,
        $courseId,
        $productId,
        $orderId
    );

    $enrollmentIds[] = $enrollmentId;

    $progressId = $progress->getOrCreate(
        $enrollmentId,
        $lessonId
    );

    $progressIds[] = $progressId;

    $assert(
        $progressId > 0,
        'progresso criado'
    );

    $sameProgressId =
        $progress->getOrCreate(
            $enrollmentId,
            $lessonId
        );

    $assert(
        $sameProgressId === $progressId,
        'progresso idempotente por matricula e aula'
    );

    $row = $progress->findById(
        $progressId
    );

    $assert(
        is_array($row)
        && (int) (
            $row['user_id']
            ?? 0
        ) === $userId
        && (int) (
            $row['course_id']
            ?? 0
        ) === $courseId
        && (int) (
            $row['lesson_id']
            ?? 0
        ) === $lessonId
        && (string) (
            $row['status']
            ?? ''
        ) === 'not_started',
        'progresso possui relacoes corretas'
    );

    $progress->start(
        $progressId
    );

    $row = $progress->findById(
        $progressId
    );

    $assert(
        is_array($row)
        && (string) (
            $row['status']
            ?? ''
        ) === 'in_progress'
        && !empty(
            $row['started_at']
        ),
        'progresso iniciado'
    );

    $progress->recordMetrics(
        $progressId,
        120,
        90,
        300,
        40.00
    );

    $row = $progress->findById(
        $progressId
    );

    $assert(
        is_array($row)
        && (int) (
            $row['watched_seconds']
            ?? 0
        ) === 120
        && (int) (
            $row['last_position_seconds']
            ?? 0
        ) === 90
        && (int) (
            $row['duration_seconds']
            ?? 0
        ) === 300
        && (float) (
            $row['completion_percent']
            ?? 0
        ) === 40.0,
        'metricas de progresso persistidas'
    );

    $expectRuntimeException(
        static function () use (
            $progress,
            $progressId
        ): void {
            $progress->recordMetrics(
                $progressId,
                100,
                100,
                300,
                101
            );
        },
        'lesson_progress_percent_invalid',
        'percentual acima de 100 rejeitado'
    );

    $secondProductId =
        $productId + 1;

    $secondCourseId = $courses->create(
        $secondProductId,
        'FD LMS Progress Other Course',
        'fd-lms-progress-other-'
        . $suffix
    );

    $courseIds[] = $secondCourseId;

    $secondModuleId = $modules->create(
        $secondCourseId,
        'Outro Modulo'
    );

    $moduleIds[] = $secondModuleId;

    $secondLessonId = $lessons->create(
        $secondCourseId,
        $secondModuleId,
        'Outra Aula',
        'outra-progress-' . $suffix
    );

    $lessonIds[] = $secondLessonId;

    $expectRuntimeException(
        static function () use (
            $progress,
            $enrollmentId,
            $secondLessonId
        ): void {
            $progress->getOrCreate(
                $enrollmentId,
                $secondLessonId
            );
        },
        'lesson_progress_lesson_invalid',
        'progresso rejeita aula de outro curso'
    );

    $progress->markCompleted(
        $progressId
    );

    $row = $progress->findById(
        $progressId
    );

    $assert(
        is_array($row)
        && (string) (
            $row['status']
            ?? ''
        ) === 'completed'
        && (float) (
            $row['completion_percent']
            ?? 0
        ) === 100.0
        && !empty(
            $row['completed_at']
        ),
        'aula marcada como concluida'
    );

    $listedProgress =
        $progress->forEnrollment(
            $enrollmentId
        );

    $assert(
        count($listedProgress) === 1
        && (int) (
            $listedProgress[0]['id']
            ?? 0
        ) === $progressId,
        'progresso listado por matricula'
    );

    echo 'COURSES_PROGRESS_IDEMPOTENCY=PASS'
        . PHP_EOL;

    $expectRuntimeException(
        static function () use (
            $certificates,
            $enrollmentId
        ): void {
            $certificates->createPending(
                $enrollmentId,
                'Aluno Teste',
                'Curso Teste',
                600
            );
        },
        'certificate_enrollment_not_completed',
        'certificado bloqueado antes da conclusao do curso'
    );

    $enrollments->markCompleted(
        $enrollmentId
    );

    $certificateId =
        $certificates->createPending(
            $enrollmentId,
            'Aluno Teste',
            'FD LMS Progress Course',
            600
        );

    $certificateIds[] =
        $certificateId;

    $assert(
        $certificateId > 0,
        'certificado pendente criado'
    );

    $certificate =
        $certificates->findById(
            $certificateId
        );

    $assert(
        is_array($certificate)
        && (string) (
            $certificate[
                'student_name_snapshot'
            ]
            ?? ''
        ) === 'Aluno Teste'
        && (string) (
            $certificate[
                'course_title_snapshot'
            ]
            ?? ''
        ) === 'FD LMS Progress Course'
        && (int) (
            $certificate[
                'workload_minutes_snapshot'
            ]
            ?? 0
        ) === 600
        && (string) (
            $certificate['status']
            ?? ''
        ) === 'pending',
        'snapshots do certificado persistidos'
    );

    $verificationCode =
        (string) (
            $certificate[
                'verification_code'
            ]
            ?? ''
        );

    $assert(
        str_starts_with(
            $verificationCode,
            'FD-'
        ),
        'codigo de verificacao gerado'
    );

    $byCode =
        $certificates
            ->findByVerificationCode(
                $verificationCode
            );

    $assert(
        is_array($byCode)
        && (int) (
            $byCode['id']
            ?? 0
        ) === $certificateId,
        'certificado recuperado por codigo'
    );

    $sameCertificateId =
        $certificates->createPending(
            $enrollmentId,
            'Nome Alterado',
            'Curso Alterado',
            9999
        );

    $assert(
        $sameCertificateId
        === $certificateId,
        'certificado idempotente por matricula'
    );

    $certificate =
        $certificates->findById(
            $certificateId
        );

    $assert(
        is_array($certificate)
        && (string) (
            $certificate[
                'student_name_snapshot'
            ]
            ?? ''
        ) === 'Aluno Teste'
        && (string) (
            $certificate[
                'course_title_snapshot'
            ]
            ?? ''
        ) === 'FD LMS Progress Course'
        && (int) (
            $certificate[
                'workload_minutes_snapshot'
            ]
            ?? 0
        ) === 600,
        'snapshots permanecem imutaveis'
    );

    $attempts =
        $certificates
            ->recordGenerationAttempt(
                $certificateId
            );

    $assert(
        $attempts === 1,
        'primeira tentativa de geracao registrada'
    );

    $certificates->markFailed(
        $certificateId,
        'development_test'
    );

    $certificate =
        $certificates->findById(
            $certificateId
        );

    $assert(
        is_array($certificate)
        && (string) (
            $certificate['status']
            ?? ''
        ) === 'failed'
        && (string) (
            $certificate['error_code']
            ?? ''
        ) === 'development_test',
        'falha de certificado registrada'
    );

    $attempts =
        $certificates
            ->recordGenerationAttempt(
                $certificateId
            );

    $assert(
        $attempts === 2,
        'segunda tentativa de geracao registrada'
    );

    $certificate =
        $certificates->findById(
            $certificateId
        );

    $assert(
        is_array($certificate)
        && (string) (
            $certificate['status']
            ?? ''
        ) === 'pending'
        && (
            $certificate['error_code']
            ?? null
        ) === null,
        'retry retorna certificado para pending'
    );

    $expectRuntimeException(
        static function () use (
            $certificates,
            $certificateId
        ): void {
            $certificates->markReady(
                $certificateId,
                'certificates/test/cert.pdf',
                4096,
                'hash-invalido'
            );
        },
        'certificate_sha256_invalid',
        'hash invalido de certificado rejeitado'
    );

    $certificates->markReady(
        $certificateId,
        'certificates/test/cert.pdf',
        4096,
        str_repeat('c', 64)
    );

    $certificate =
        $certificates->findById(
            $certificateId
        );

    $assert(
        is_array($certificate)
        && (string) (
            $certificate['status']
            ?? ''
        ) === 'ready'
        && (string) (
            $certificate['storage_key']
            ?? ''
        ) === 'certificates/test/cert.pdf'
        && (int) (
            $certificate['file_size']
            ?? 0
        ) === 4096
        && (string) (
            $certificate['sha256']
            ?? ''
        ) === str_repeat('c', 64)
        && !empty(
            $certificate['issued_at']
        ),
        'certificado marcado como pronto'
    );

    $userCertificates =
        $certificates->forUser(
            $userId
        );

    $assert(
        count($userCertificates) === 1
        && (int) (
            $userCertificates[0]['id']
            ?? 0
        ) === $certificateId,
        'certificado listado para usuario'
    );

    echo 'COURSES_CERTIFICATE_IDEMPOTENCY=PASS'
        . PHP_EOL;

    echo 'COURSES_PROGRESS_CERTIFICATES=PASS'
        . PHP_EOL;
} catch (Throwable $exception) {
    $failure = $exception;

    fwrite(
        STDERR,
        $exception->getMessage()
        . PHP_EOL
    );
} finally {
    foreach (
        array_reverse($certificateIds)
        as $certificateId
    ) {
        $wpdb->delete(
            Database::table(
                'certificates'
            ),
            ['id' => $certificateId],
            ['%d']
        );
    }

    foreach (
        array_reverse($progressIds)
        as $progressId
    ) {
        $wpdb->delete(
            Database::table(
                'lesson_progress'
            ),
            ['id' => $progressId],
            ['%d']
        );
    }

    foreach (
        array_reverse($enrollmentIds)
        as $enrollmentId
    ) {
        $wpdb->delete(
            Database::table(
                'course_enrollments'
            ),
            ['id' => $enrollmentId],
            ['%d']
        );
    }

    foreach (
        array_reverse($lessonIds)
        as $lessonId
    ) {
        $wpdb->delete(
            Database::table(
                'course_lessons'
            ),
            ['id' => $lessonId],
            ['%d']
        );
    }

    foreach (
        array_reverse($moduleIds)
        as $moduleId
    ) {
        $wpdb->delete(
            Database::table(
                'course_modules'
            ),
            ['id' => $moduleId],
            ['%d']
        );
    }

    foreach (
        array_reverse($courseIds)
        as $courseId
    ) {
        $wpdb->delete(
            Database::table(
                'courses'
            ),
            ['id' => $courseId],
            ['%d']
        );
    }

    if (!function_exists('wp_delete_user')) {
        require_once ABSPATH
            . 'wp-admin/includes/user.php';
    }

    foreach ($userIds as $userId) {
        wp_delete_user($userId);
    }

    echo 'FIXTURES_CLEANUP=OK'
        . PHP_EOL;
}

if ($failure instanceof Throwable) {
    exit(1);
}