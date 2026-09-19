<?php

declare(strict_types=1);

use FacilDigital\Core\Core\Database;
use FacilDigital\Core\Courses\CourseAdminModule;
use FacilDigital\Core\Courses\CourseBuilderService;
use FacilDigital\Core\Courses\CourseModuleRepository;
use FacilDigital\Core\Courses\CourseProductService;
use FacilDigital\Core\Courses\CourseRepository;
use FacilDigital\Core\Courses\LessonRepository;
use FacilDigital\Core\Courses\LessonResourceRepository;

if (wp_get_environment_type() !== 'development') {
    throw new RuntimeException(
        'Este teste so pode rodar em development.'
    );
}

global $wpdb;

$courseIds = [];
$productIds = [];
$moduleIds = [];
$lessonIds = [];
$resourceIds = [];
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
$products = new CourseProductService(
    $courses
);
$builder = new CourseBuilderService(
    $courses,
    $products,
    $modules,
    $lessons,
    $resources
);

try {
    $assert(
        method_exists(
            $lessons,
            'moveToModule'
        ),
        'LessonRepository possui moveToModule'
    );

    $assert(
        !method_exists(
            $resources,
            'moveToModule'
        )
        && !method_exists(
            \FacilDigital\Core\Courses\ProgressRepository::class,
            'moveToModule'
        ),
        'moveToModule pertence somente ao LessonRepository'
    );

    $suffix = strtolower(
        wp_generate_password(
            8,
            false,
            false
        )
    );

    $courseId = $builder->saveCourse(
        0,
        [
            'title' =>
                'FD Builder Course '
                . $suffix,
            'slug' =>
                'fd-builder-' . $suffix,
            'short_description' =>
                'Resumo academico',
            'description' =>
                '<p>Conteudo valido</p><script>alert(1)</script>',
            'workload_minutes' =>
                480,
            'completion_threshold' =>
                95,
            'navigation_mode' =>
                'free',
            'certificate_enabled' =>
                1,
            'status' =>
                'draft',
            'regular_price' =>
                '129.90',
            'catalog_visibility' =>
                'visible',
            'product_short_description' =>
                'Resumo comercial',
            'product_description' =>
                '<p>Descricao comercial</p>',
        ]
    );

    $courseIds[] = $courseId;

    $course = $courses->findById(
        $courseId
    );

    $assert(
        is_array($course)
        && $courseId > 0,
        'Course Builder cria curso'
    );

    $productId = (int) (
        $course['product_id']
        ?? 0
    );

    $productIds[] = $productId;

    $assert(
        $productId > 0
        && $products->isCourseProduct(
            $productId
        ),
        'Course Builder cria produto WooCommerce'
    );

    $assert(
        !str_contains(
            (string) (
                $course['description']
                ?? ''
            ),
            '<script'
        ),
        'descricao academica sanitizada'
    );

    $builder->saveCourse(
        $courseId,
        [
            'title' =>
                'FD Builder Updated '
                . $suffix,
            'slug' =>
                'fd-builder-' . $suffix,
            'short_description' =>
                'Resumo atualizado',
            'description' =>
                '<p>Descricao atualizada</p>',
            'workload_minutes' =>
                600,
            'completion_threshold' =>
                90,
            'navigation_mode' =>
                'sequential',
            'certificate_enabled' =>
                1,
            'status' =>
                'published',
            'regular_price' =>
                '159.90',
            'catalog_visibility' =>
                'catalog',
            'product_short_description' =>
                'Comercial atualizado',
            'product_description' =>
                '<p>Produto atualizado</p>',
        ]
    );

    $course = $courses->findById(
        $courseId
    );

    $product = wc_get_product(
        $productId
    );

    $assert(
        is_array($course)
        && (string) $course['status']
            === 'published'
        && (string) $course[
            'navigation_mode'
        ] === 'sequential'
        && $product instanceof WC_Product
        && $product->get_status()
            === 'publish'
        && (float) $product
            ->get_regular_price()
            === 159.9,
        'Course Builder atualiza curso e produto'
    );

    $moduleA = $builder->saveModule(
        $courseId,
        0,
        [
            'title' =>
                'Modulo A',
            'description' =>
                'Primeiro modulo',
            'sort_order' =>
                20,
        ]
    );

    $moduleIds[] = $moduleA;

    $moduleB = $builder->saveModule(
        $courseId,
        0,
        [
            'title' =>
                'Modulo B',
            'description' =>
                'Segundo modulo',
            'sort_order' =>
                10,
        ]
    );

    $moduleIds[] = $moduleB;

    $assert(
        count(
            $modules->forCourse(
                $courseId
            )
        ) === 2,
        'Builder cria modulos'
    );

    $lessonA = $builder->saveLesson(
        $courseId,
        $moduleA,
        0,
        [
            'title' =>
                'Aula de Video',
            'slug' =>
                'video-' . $suffix,
            'lesson_type' =>
                'video',
            'youtube_video_id' =>
                'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'content' =>
                '<p>Apoio</p>',
            'transcript' =>
                '<p>Transcricao</p>',
            'duration_seconds' =>
                300,
            'is_required' =>
                1,
            'sort_order' =>
                20,
            'status' =>
                'published',
        ]
    );

    $lessonIds[] = $lessonA;

    $lessonB = $builder->saveLesson(
        $courseId,
        $moduleA,
        0,
        [
            'title' =>
                'Aula Texto',
            'slug' =>
                'texto-' . $suffix,
            'lesson_type' =>
                'text',
            'content' =>
                '<p>Leitura</p><script>bad()</script>',
            'duration_seconds' =>
                120,
            'is_required' =>
                1,
            'sort_order' =>
                10,
            'status' =>
                'published',
        ]
    );

    $lessonIds[] = $lessonB;

    $videoLesson =
        $lessons->findById(
            $lessonA
        );

    $assert(
        is_array($videoLesson)
        && (string) (
            $videoLesson[
                'youtube_video_id'
            ]
            ?? ''
        ) === 'dQw4w9WgXcQ',
        'URL do YouTube normalizada para video_id'
    );

    $textLesson =
        $lessons->findById(
            $lessonB
        );

    $assert(
        is_array($textLesson)
        && !str_contains(
            (string) (
                $textLesson['content']
                ?? ''
            ),
            '<script'
        ),
        'conteudo de aula sanitizado'
    );

    $expectRuntimeException(
        static function () use (
            $builder,
            $courseId,
            $moduleA,
            $suffix
        ): void {
            $builder->saveLesson(
                $courseId,
                $moduleA,
                0,
                [
                    'title' =>
                        'Video Invalido',
                    'slug' =>
                        'video-invalid-'
                        . $suffix,
                    'lesson_type' =>
                        'video',
                    'youtube_video_id' =>
                        'nao-e-youtube',
                ]
            );
        },
        'course_lesson_youtube_invalid',
        'Builder rejeita video YouTube invalido'
    );

    $resourceId =
        $builder->saveResource(
            $courseId,
            $lessonA,
            0,
            [
                'title' =>
                    'PDF da aula',
                'storage_key' =>
                    'courses/test/material.pdf',
                'original_filename' =>
                    'material.pdf',
                'mime_type' =>
                    'application/pdf',
                'file_size' =>
                    2048,
                'sha256' =>
                    str_repeat('a', 64),
                'sort_order' =>
                    10,
                'status' =>
                    'active',
            ]
        );

    $resourceIds[] = $resourceId;

    $assert(
        count(
            $resources->forLesson(
                $lessonA
            )
        ) === 1,
        'Builder cadastra recurso de aula'
    );

    $curriculum =
        $builder->curriculum(
            $courseId
        );

    $assert(
        count($curriculum) === 2,
        'Builder monta curriculo'
    );

    $builder->reorder(
        $courseId,
        [
            $moduleA,
            $moduleB,
        ],
        [
            $moduleA => [
                $lessonA,
            ],
            $moduleB => [
                $lessonB,
            ],
        ]
    );

    $orderedModules =
        $modules->forCourse(
            $courseId
        );

    $movedLesson =
        $lessons->findById(
            $lessonB
        );

    $assert(
        (int) (
            $orderedModules[0]['id']
            ?? 0
        ) === $moduleA
        && (int) (
            $movedLesson['module_id']
            ?? 0
        ) === $moduleB,
        'Builder reordena modulos e move aulas'
    );

    $expectRuntimeException(
        static function () use (
            $builder,
            $courseId,
            $moduleA
        ): void {
            $builder->deleteModule(
                $courseId,
                $moduleA
            );
        },
        'course_module_not_empty',
        'Builder protege modulo com aulas'
    );

    $expectRuntimeException(
        static function () use (
            $builder,
            $courseId,
            $lessonA
        ): void {
            $builder->deleteLesson(
                $courseId,
                $lessonA
            );
        },
        'course_lesson_has_resources',
        'Builder protege aula com recursos'
    );

    $builder->deleteResource(
        $courseId,
        $resourceId
    );

    $resourceIds = [];

    $builder->deleteLesson(
        $courseId,
        $lessonA
    );

    $lessonIds = array_values(
        array_diff(
            $lessonIds,
            [$lessonA]
        )
    );

    $builder->deleteModule(
        $courseId,
        $moduleA
    );

    $moduleIds = array_values(
        array_diff(
            $moduleIds,
            [$moduleA]
        )
    );

    $assert(
        $modules->findById(
            $moduleA
        ) === null,
        'Builder remove modulo vazio'
    );

    $adminSource = file_get_contents(
        FACIL_DIGITAL_CORE_DIR
        . 'src/Courses/CourseAdminModule.php'
    );

    $assert(
        is_string($adminSource)
        && str_contains(
            $adminSource,
            'check_admin_referer'
        )
        && str_contains(
            $adminSource,
            'current_user_can'
        )
        && str_contains(
            $adminSource,
            'Capabilities::ACCESS_ADMIN'
        ),
        'admin possui nonce e capability checks'
    );

    $assert(
        has_action(
            'admin_post_fd_course_save'
        ) !== false
        && has_action(
            'admin_post_fd_course_module_save'
        ) !== false
        && has_action(
            'admin_post_fd_course_lesson_save'
        ) !== false
        && has_action(
            'admin_post_fd_course_resource_save'
        ) !== false
        && has_action(
            'admin_post_fd_course_reorder'
        ) !== false,
        'handlers do Course Builder registrados'
    );

    $assert(
        is_readable(
            FACIL_DIGITAL_CORE_DIR
            . 'assets/admin/courses.css'
        )
        && is_readable(
            FACIL_DIGITAL_CORE_DIR
            . 'assets/admin/courses.js'
        ),
        'assets administrativos presentes'
    );

    $entitlements =
        Database::table(
            'entitlements'
        );

    $entitlementCount =
        (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$entitlements}
                 WHERE product_id = %d",
                $productId
            )
        );

    $assert(
        $entitlementCount === 0,
        'Course Builder nao interfere em entitlements'
    );

    echo 'COURSES_ADMIN_SECURITY=PASS'
        . PHP_EOL;

    echo 'COURSES_CURRICULUM_BUILDER=PASS'
        . PHP_EOL;

    echo 'COURSES_ADMIN_BUILDER=PASS'
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

    foreach (
        array_reverse($productIds)
        as $productId
    ) {
        $product = wc_get_product(
            $productId
        );

        if ($product instanceof WC_Product) {
            $product->delete(true);
        }
    }

    echo 'FIXTURES_CLEANUP=OK'
        . PHP_EOL;
}

if ($failure instanceof Throwable) {
    exit(1);
}