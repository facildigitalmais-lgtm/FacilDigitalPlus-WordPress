<?php

declare(strict_types=1);

use FacilDigital\Core\Core\Database;
use FacilDigital\Core\Courses\CourseModuleRepository;
use FacilDigital\Core\Courses\CourseRepository;
use FacilDigital\Core\Courses\LessonRepository;

if (wp_get_environment_type() !== 'development') {
    throw new RuntimeException(
        'Este teste so pode rodar em development.'
    );
}

global $wpdb;

$courseIds = [];
$moduleIds = [];
$lessonIds = [];
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
        string $expectedMessage,
        string $label
    ) use ($assert): void {
        $caught = false;

        try {
            $callback();
        } catch (RuntimeException $exception) {
            $caught =
                $exception->getMessage()
                === $expectedMessage;
        }

        $assert(
            $caught,
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

try {
    $suffix =
        strtolower(
            wp_generate_password(
                8,
                false,
                false
            )
        );

    $productId =
        900000000 + wp_rand(
            1000,
            999999
        );

    $courseId = $courses->create(
        $productId,
        'FD LMS TEST Curso',
        'fd-lms-test-' . $suffix,
        [
            'short_description' =>
                'Curso de teste',
            'description' =>
                '<p>Conteudo inicial</p>',
            'workload_minutes' =>
                120,
            'completion_threshold' =>
                95,
            'navigation_mode' =>
                'free',
            'certificate_enabled' =>
                true,
            'status' =>
                'draft',
        ]
    );

    $courseIds[] = $courseId;

    $assert(
        $courseId > 0,
        'curso criado'
    );

    $course = $courses->findById(
        $courseId
    );

    $assert(
        is_array($course)
        && (string) (
            $course['title']
            ?? ''
        ) === 'FD LMS TEST Curso',
        'curso recuperado por id'
    );

    $assert(
        (int) (
            $courses->findByProductId(
                $productId
            )['id']
            ?? 0
        ) === $courseId,
        'curso recuperado por product_id'
    );

    $assert(
        (int) (
            $courses->findBySlug(
                'fd-lms-test-' . $suffix
            )['id']
            ?? 0
        ) === $courseId,
        'curso recuperado por slug'
    );

    $courses->update(
        $courseId,
        [
            'title' =>
                'FD LMS TEST Curso Atualizado',
            'workload_minutes' =>
                180,
            'navigation_mode' =>
                'sequential',
            'status' =>
                'published',
        ]
    );

    $course = $courses->findById(
        $courseId
    );

    $assert(
        is_array($course)
        && (string) (
            $course['title']
            ?? ''
        )
        === 'FD LMS TEST Curso Atualizado'
        && (int) (
            $course['workload_minutes']
            ?? 0
        ) === 180
        && (string) (
            $course['navigation_mode']
            ?? ''
        ) === 'sequential'
        && (string) (
            $course['status']
            ?? ''
        ) === 'published',
        'curso atualizado'
    );

    $expectRuntimeException(
        static fn () =>
            $courses->update(
                $courseId,
                [
                    'completion_threshold' =>
                        101,
                ]
            ),
        'course_completion_threshold_invalid',
        'threshold invalido rejeitado'
    );

    $moduleA = $modules->create(
        $courseId,
        'Modulo A',
        'Primeiro modulo',
        20
    );

    $moduleIds[] = $moduleA;

    $moduleB = $modules->create(
        $courseId,
        'Modulo B',
        'Segundo modulo',
        10
    );

    $moduleIds[] = $moduleB;

    $orderedModules =
        $modules->forCourse(
            $courseId
        );

    $assert(
        count($orderedModules) === 2
        && (int) (
            $orderedModules[0]['id']
            ?? 0
        ) === $moduleB
        && (int) (
            $orderedModules[1]['id']
            ?? 0
        ) === $moduleA,
        'modulos ordenados por sort_order'
    );

    $modules->update(
        $moduleA,
        [
            'title' =>
                'Modulo A Atualizado',
            'sort_order' =>
                5,
        ]
    );

    $module = $modules->findById(
        $moduleA
    );

    $assert(
        is_array($module)
        && (string) (
            $module['title']
            ?? ''
        ) === 'Modulo A Atualizado'
        && (int) (
            $module['sort_order']
            ?? 0
        ) === 5,
        'modulo atualizado'
    );

    $lessonText = $lessons->create(
        $courseId,
        $moduleA,
        'Aula Texto',
        'aula-texto-' . $suffix,
        [
            'lesson_type' =>
                'text',
            'content' =>
                '<p>Leitura da aula.</p>',
            'sort_order' =>
                20,
            'status' =>
                'published',
        ]
    );

    $lessonIds[] = $lessonText;

    $lessonVideo = $lessons->create(
        $courseId,
        $moduleA,
        'Aula Video',
        'aula-video-' . $suffix,
        [
            'lesson_type' =>
                'video',
            'youtube_video_id' =>
                'dQw4w9WgXcQ',
            'duration_seconds' =>
                300,
            'sort_order' =>
                10,
            'status' =>
                'published',
        ]
    );

    $lessonIds[] = $lessonVideo;

    $orderedLessons =
        $lessons->forModule(
            $moduleA
        );

    $assert(
        count($orderedLessons) === 2
        && (int) (
            $orderedLessons[0]['id']
            ?? 0
        ) === $lessonVideo
        && (int) (
            $orderedLessons[1]['id']
            ?? 0
        ) === $lessonText,
        'aulas ordenadas por sort_order'
    );

    $lessons->update(
        $lessonText,
        [
            'title' =>
                'Aula Texto Atualizada',
            'lesson_type' =>
                'mixed',
            'transcript' =>
                '<p>Transcricao.</p>',
            'duration_seconds' =>
                60,
            'sort_order' =>
                1,
        ]
    );

    $lesson = $lessons->findById(
        $lessonText
    );

    $assert(
        is_array($lesson)
        && (string) (
            $lesson['title']
            ?? ''
        ) === 'Aula Texto Atualizada'
        && (string) (
            $lesson['lesson_type']
            ?? ''
        ) === 'mixed'
        && (int) (
            $lesson['duration_seconds']
            ?? 0
        ) === 60,
        'aula atualizada'
    );

    $expectRuntimeException(
        static function () use (
            $lessons,
            $courseId,
            $moduleA,
            $suffix
        ): void {
            $lessons->create(
                $courseId,
                $moduleA,
                'Slug duplicado',
                'aula-texto-' . $suffix
            );
        },
        'lesson_slug_duplicate',
        'slug de aula duplicado no curso rejeitado'
    );

    $secondProductId =
        $productId + 1;

    $secondCourseId =
        $courses->create(
            $secondProductId,
            'FD LMS TEST Outro Curso',
            'fd-lms-test-outro-'
            . $suffix
        );

    $courseIds[] = $secondCourseId;

    $foreignModule =
        $modules->create(
            $secondCourseId,
            'Modulo Estrangeiro'
        );

    $moduleIds[] = $foreignModule;

    $expectRuntimeException(
        static function () use (
            $lessons,
            $courseId,
            $foreignModule,
            $suffix
        ): void {
            $lessons->create(
                $courseId,
                $foreignModule,
                'Aula Invalida',
                'aula-invalida-' . $suffix
            );
        },
        'lesson_module_invalid',
        'aula nao pode usar modulo de outro curso'
    );

    echo 'COURSES_RELATION_INTEGRITY=PASS'
        . PHP_EOL;

    foreach (
        array_reverse($lessonIds)
        as $lessonId
    ) {
        $lessons->delete(
            $lessonId
        );
    }

    foreach (
        array_reverse($moduleIds)
        as $moduleId
    ) {
        $modules->delete(
            $moduleId
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

    $assert(
        $courses->findById(
            $courseId
        ) === null,
        'curso removido'
    );

    echo 'COURSES_REPOSITORIES_CRUD=PASS'
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
        as $storedCourseId
    ) {
        $wpdb->delete(
            Database::table(
                'courses'
            ),
            ['id' => $storedCourseId],
            ['%d']
        );
    }

    echo 'FIXTURES_CLEANUP=OK'
        . PHP_EOL;
}

if ($failure instanceof Throwable) {
    exit(1);
}