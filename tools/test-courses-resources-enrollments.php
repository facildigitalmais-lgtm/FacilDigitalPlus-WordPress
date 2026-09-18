<?php

declare(strict_types=1);

use FacilDigital\Core\Core\Database;
use FacilDigital\Core\Courses\CourseModuleRepository;
use FacilDigital\Core\Courses\CourseRepository;
use FacilDigital\Core\Courses\EnrollmentRepository;
use FacilDigital\Core\Courses\LessonRepository;
use FacilDigital\Core\Courses\LessonResourceRepository;

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
$resourceIds = [];
$enrollmentIds = [];
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
$resources = new LessonResourceRepository(
    $lessons
);
$enrollments = new EnrollmentRepository(
    $courses
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
                'fd_lms_enroll_' . $suffix,
            'user_pass' =>
                wp_generate_password(
                    24,
                    true,
                    true
                ),
            'user_email' =>
                'fd-lms-' . $suffix
                . '@example.com',
            'display_name' =>
                'FD LMS Enrollment Test',
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
        910000000 + wp_rand(
            1000,
            999999
        );

    $courseId = $courses->create(
        $productId,
        'FD LMS Resources Course',
        'fd-lms-resource-' . $suffix
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
        'aula-principal-' . $suffix,
        [
            'status' =>
                'published',
        ]
    );

    $lessonIds[] = $lessonId;

    $resourceB = $resources->create(
        $courseId,
        $lessonId,
        'Material B',
        'courses/test/material-b.pdf',
        'material-b.pdf',
        'application/pdf',
        [
            'file_size' =>
                2048,
            'sha256' =>
                str_repeat('b', 64),
            'sort_order' =>
                20,
        ]
    );

    $resourceIds[] = $resourceB;

    $resourceA = $resources->create(
        $courseId,
        $lessonId,
        'Material A',
        'courses/test/material-a.pdf',
        'material-a.pdf',
        'application/pdf',
        [
            'file_size' =>
                1024,
            'sha256' =>
                str_repeat('a', 64),
            'sort_order' =>
                10,
        ]
    );

    $resourceIds[] = $resourceA;

    $orderedResources =
        $resources->forLesson(
            $lessonId
        );

    $assert(
        count($orderedResources) === 2
        && (int) (
            $orderedResources[0]['id']
            ?? 0
        ) === $resourceA
        && (int) (
            $orderedResources[1]['id']
            ?? 0
        ) === $resourceB,
        'recursos ordenados por sort_order'
    );

    $resources->update(
        $resourceA,
        [
            'title' =>
                'Material A Atualizado',
            'sort_order' =>
                5,
            'status' =>
                'hidden',
        ]
    );

    $resource = $resources->findById(
        $resourceA
    );

    $assert(
        is_array($resource)
        && (string) (
            $resource['title']
            ?? ''
        ) === 'Material A Atualizado'
        && (int) (
            $resource['sort_order']
            ?? 0
        ) === 5
        && (string) (
            $resource['status']
            ?? ''
        ) === 'hidden',
        'recurso atualizado'
    );

    $expectRuntimeException(
        static function () use (
            $resources,
            $courseId,
            $lessonId
        ): void {
            $resources->create(
                $courseId,
                $lessonId,
                'Traversal',
                '../private/file.pdf',
                'file.pdf',
                'application/pdf'
            );
        },
        'lesson_resource_storage_key_invalid',
        'storage key insegura rejeitada'
    );

    $secondProductId =
        $productId + 1;

    $secondCourseId = $courses->create(
        $secondProductId,
        'FD LMS Resource Other Course',
        'fd-lms-resource-other-'
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
        'outra-aula-' . $suffix
    );

    $lessonIds[] = $secondLessonId;

    $expectRuntimeException(
        static function () use (
            $resources,
            $courseId,
            $secondLessonId
        ): void {
            $resources->create(
                $courseId,
                $secondLessonId,
                'Recurso Invalido',
                'courses/test/invalid.pdf',
                'invalid.pdf',
                'application/pdf'
            );
        },
        'lesson_resource_lesson_invalid',
        'recurso nao pode usar aula de outro curso'
    );

    echo 'COURSES_RESOURCE_INTEGRITY=PASS'
        . PHP_EOL;

    $orderId =
        920000000 + wp_rand(
            1000,
            999999
        );

    $enrollmentId = $enrollments->grant(
        $userId,
        $courseId,
        $productId,
        $orderId,
        930000001
    );

    $enrollmentIds[] = $enrollmentId;

    $assert(
        $enrollmentId > 0,
        'matricula criada'
    );

    $sameEnrollmentId =
        $enrollments->grant(
            $userId,
            $courseId,
            $productId,
            $orderId,
            930000001
        );

    $assert(
        $sameEnrollmentId === $enrollmentId,
        'matricula idempotente para mesmo pedido'
    );

    $enrollment =
        $enrollments->findById(
            $enrollmentId
        );

    $assert(
        is_array($enrollment)
        && (int) (
            $enrollment['user_id']
            ?? 0
        ) === $userId
        && (int) (
            $enrollment['course_id']
            ?? 0
        ) === $courseId
        && (string) (
            $enrollment['status']
            ?? ''
        ) === 'active',
        'matricula recuperada'
    );

    $activeEnrollments =
        $enrollments->forUser(
            $userId,
            'active'
        );

    $assert(
        count($activeEnrollments) === 1
        && (int) (
            $activeEnrollments[0]['id']
            ?? 0
        ) === $enrollmentId,
        'matricula ativa listada para usuario'
    );

    $expectRuntimeException(
        static function () use (
            $enrollments,
            $userId,
            $courseId,
            $productId,
            $orderId
        ): void {
            $enrollments->grant(
                $userId,
                $courseId,
                $productId + 999,
                $orderId + 1
            );
        },
        'course_enrollment_product_invalid',
        'produto diferente do curso rejeitado'
    );

    $enrollments->revoke(
        $enrollmentId,
        'development_test'
    );

    $enrollment =
        $enrollments->findById(
            $enrollmentId
        );

    $assert(
        is_array($enrollment)
        && (string) (
            $enrollment['status']
            ?? ''
        ) === 'revoked'
        && (string) (
            $enrollment['revocation_reason']
            ?? ''
        ) === 'development_test',
        'matricula revogada'
    );

    $enrollments->reactivate(
        $enrollmentId
    );

    $enrollment =
        $enrollments->findById(
            $enrollmentId
        );

    $assert(
        is_array($enrollment)
        && (string) (
            $enrollment['status']
            ?? ''
        ) === 'active'
        && (
            $enrollment['revoked_at']
            ?? null
        ) === null,
        'matricula reativada'
    );

    $enrollments->markCompleted(
        $enrollmentId
    );

    $enrollment =
        $enrollments->findById(
            $enrollmentId
        );

    $assert(
        is_array($enrollment)
        && (string) (
            $enrollment['status']
            ?? ''
        ) === 'completed'
        && !empty(
            $enrollment['completed_at']
        ),
        'matricula marcada como concluida'
    );

    echo 'COURSES_ENROLLMENT_IDEMPOTENCY=PASS'
        . PHP_EOL;

    foreach (
        array_reverse($enrollmentIds)
        as $storedEnrollmentId
    ) {
        $enrollments->delete(
            $storedEnrollmentId
        );
    }

    foreach (
        array_reverse($resourceIds)
        as $resourceId
    ) {
        $resources->delete(
            $resourceId
        );
    }

    foreach (
        array_reverse($lessonIds)
        as $storedLessonId
    ) {
        $lessons->delete(
            $storedLessonId
        );
    }

    foreach (
        array_reverse($moduleIds)
        as $storedModuleId
    ) {
        $modules->delete(
            $storedModuleId
        );
    }

    foreach (
        array_reverse($courseIds)
        as $storedCourseId
    ) {
        $courses->delete(
            $storedCourseId
        );
    }

    echo 'COURSES_RESOURCES_ENROLLMENTS=PASS'
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
        array_reverse($resourceIds)
        as $resourceId
    ) {
        $wpdb->delete(
            Database::table(
                'lesson_resources'
            ),
            ['id' => $resourceId],
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