<?php

declare(strict_types=1);

use FacilDigital\Core\Courses\CertificateRepository;
use FacilDigital\Core\Courses\CourseAccountModule;
use FacilDigital\Core\Courses\CourseDeliveryModule;
use FacilDigital\Core\Courses\CourseLearningService;
use FacilDigital\Core\Courses\CourseModuleRepository;
use FacilDigital\Core\Courses\CoursePrivateStorage;
use FacilDigital\Core\Courses\CourseProductService;
use FacilDigital\Core\Courses\CourseRepository;
use FacilDigital\Core\Courses\EnrollmentRepository;
use FacilDigital\Core\Courses\LessonRepository;
use FacilDigital\Core\Courses\LessonResourceRepository;
use FacilDigital\Core\Courses\ProgressRepository;

if (
    wp_get_environment_type()
    !== 'development'
) {
    throw new RuntimeException(
        'Este teste so pode rodar em development.'
    );
}

$userIds = [];
$productIds = [];
$orderIds = [];
$courseIds = [];
$moduleIds = [];
$lessonIds = [];
$enrollmentIds = [];
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

    echo 'PASS: '
        . $label
        . PHP_EOL;
};

$courses =
    new CourseRepository();

$modules =
    new CourseModuleRepository(
        $courses
    );

$lessons =
    new LessonRepository(
        $courses,
        $modules
    );

$resources =
    new LessonResourceRepository(
        $lessons
    );

$enrollments =
    new EnrollmentRepository(
        $courses
    );

$progress =
    new ProgressRepository(
        $enrollments,
        $lessons
    );

$certificates =
    new CertificateRepository(
        $enrollments
    );

$products =
    new CourseProductService(
        $courses
    );

$learning =
    new CourseLearningService(
        $courses,
        $modules,
        $lessons,
        $resources,
        $enrollments,
        $progress,
        $certificates
    );

$account =
    new CourseAccountModule(
        $learning
    );

$storage =
    new CoursePrivateStorage();

try {
    add_filter(
        'pre_wp_mail',
        '__return_true'
    );

    $suffix = strtolower(
        wp_generate_password(
            8,
            false,
            false
        )
    );

    $userId = wp_insert_user([
        'user_login' =>
            'fd_experience_'
            . $suffix,
        'user_pass' =>
            wp_generate_password(
                24,
                true,
                true
            ),
        'user_email' =>
            'experience-'
            . $suffix
            . '@example.com',
        'display_name' =>
            'Aluno Experiência LMS',
        'role' =>
            'customer',
    ]);

    if (is_wp_error($userId)) {
        throw new RuntimeException(
            'fixture_user_create_failed'
        );
    }

    $userId = (int) $userId;
    $userIds[] = $userId;

    $created =
        $products->createCourseProduct(
            'FD Experience '
            . $suffix,
            'fd-experience-'
            . $suffix,
            '59.90',
            [
                'short_description' =>
                    'Curso para validar a experiência final do aluno.',
                'workload_minutes' =>
                    120,
                'completion_threshold' =>
                    90,
                'navigation_mode' =>
                    'sequential',
                'certificate_enabled' =>
                    true,
                'status' =>
                    'published',
            ],
            [
                'status' =>
                    'draft',
            ]
        );

    $courseId =
        (int) $created['course_id'];

    $productId =
        (int) $created['product_id'];

    $courseIds[] =
        $courseId;

    $productIds[] =
        $productId;

    $moduleOne =
        $modules->create(
            $courseId,
            'Módulo 1',
            '',
            10
        );

    $moduleTwo =
        $modules->create(
            $courseId,
            'Módulo 2',
            '',
            20
        );

    $moduleIds[] = $moduleOne;
    $moduleIds[] = $moduleTwo;

    $lessonOne =
        $lessons->create(
            $courseId,
            $moduleOne,
            'Primeira aula',
            'primeira-aula-'
            . $suffix,
            [
                'lesson_type' =>
                    'text',
                'content' =>
                    '<p>Primeira aula.</p>',
                'is_required' =>
                    true,
                'sort_order' =>
                    10,
                'status' =>
                    'published',
            ]
        );

    $lessonTwo =
        $lessons->create(
            $courseId,
            $moduleOne,
            'Segunda aula',
            'segunda-aula-'
            . $suffix,
            [
                'lesson_type' =>
                    'text',
                'content' =>
                    '<p>Segunda aula.</p>',
                'is_required' =>
                    true,
                'sort_order' =>
                    20,
                'status' =>
                    'published',
            ]
        );

    $lessonThree =
        $lessons->create(
            $courseId,
            $moduleTwo,
            'Aula final',
            'aula-final-'
            . $suffix,
            [
                'lesson_type' =>
                    'text',
                'content' =>
                    '<p>Aula final.</p>',
                'is_required' =>
                    true,
                'sort_order' =>
                    10,
                'status' =>
                    'published',
            ]
        );

    $lessonIds[] = $lessonOne;
    $lessonIds[] = $lessonTwo;
    $lessonIds[] = $lessonThree;

    $order = wc_create_order([
        'customer_id' =>
            $userId,
    ]);

    if (
        is_wp_error($order)
        || !$order instanceof \WC_Order
    ) {
        throw new RuntimeException(
            'fixture_order_create_failed'
        );
    }

    $order->add_product(
        wc_get_product(
            $productId
        ),
        1
    );

    $order->calculate_totals();
    $order->save();

    $orderId =
        (int) $order->get_id();

    $orderIds[] =
        $orderId;

    $order->update_status(
        'processing'
    );

    $rows =
        $enrollments->forUser(
            $userId
        );

    $assert(
        count($rows) === 1,
        'fixture possui uma matricula'
    );

    $enrollmentId =
        (int) $rows[0]['id'];

    $enrollmentIds[] =
        $enrollmentId;

    $library =
        $learning->userCourses(
            $userId
        );

    $assert(
        count($library) === 1,
        'curso aparece na biblioteca do aluno'
    );

    $stats =
        (array) (
            $library[0]['stats']
            ?? []
        );

    $assert(
        (int) (
            $stats['module_count']
            ?? 0
        ) === 2
        && (int) (
            $stats['lesson_count']
            ?? 0
        ) === 3
        && (int) (
            $stats['required_count']
            ?? 0
        ) === 3,
        'biblioteca calcula modulos e aulas'
    );

    $assert(
        (int) (
            $library[0][
                'continue_lesson_id'
            ]
            ?? 0
        ) === $lessonOne,
        'retomada aponta para primeira aula pendente'
    );

    wp_set_current_user(
        $userId
    );

    ob_start();

    $account->renderCourses();

    $libraryHtml =
        (string) ob_get_clean();

    $assert(
        str_contains(
            $libraryHtml,
            'fd-course-library-card__media'
        )
        && str_contains(
            $libraryHtml,
            'fd-course-library-card__meta'
        )
        && str_contains(
            $libraryHtml,
            'role="progressbar"'
        )
        && str_contains(
            $libraryHtml,
            '2 módulos'
        )
        && str_contains(
            $libraryHtml,
            '3 aulas'
        ),
        'cards exibem capa metadados e progresso acessivel'
    );

    $stateOne =
        $learning->classroom(
            $userId,
            $enrollmentId,
            $lessonOne
        );

    $navigationOne =
        (array) (
            $stateOne['navigation']
            ?? []
        );

    $assert(
        ($navigationOne['previous']
            ?? null) === null,
        'primeira aula nao possui anterior'
    );

    $nextOne =
        (array) (
            $navigationOne['next']
            ?? []
        );

    $assert(
        (int) (
            $nextOne['id']
            ?? 0
        ) === $lessonTwo
        && !empty(
            $nextOne['locked']
        ),
        'proxima aula sequencial inicia bloqueada'
    );

    $_GET['aula'] =
        (string) $lessonOne;

    ob_start();

    $account->renderClassroom(
        (string) $enrollmentId
    );

    $classroomHtml =
        (string) ob_get_clean();

    $assert(
        str_contains(
            $classroomHtml,
            'fd-course-sidebar-toggle'
        )
        && str_contains(
            $classroomHtml,
            'aria-controls="fd-course-sidebar"'
        )
        && str_contains(
            $classroomHtml,
            'fd-course-live-progress'
        )
        && str_contains(
            $classroomHtml,
            'fd-course-lesson-nav'
        )
        && str_contains(
            $classroomHtml,
            'Conclua a aula atual para liberar.'
        ),
        'sala renderiza sidebar progresso e navegacao'
    );

    $learning->completeTextLesson(
        $userId,
        $enrollmentId,
        $lessonOne
    );

    $stateTwo =
        $learning->classroom(
            $userId,
            $enrollmentId,
            $lessonTwo
        );

    $navigationTwo =
        (array) (
            $stateTwo['navigation']
            ?? []
        );

    $previousTwo =
        (array) (
            $navigationTwo['previous']
            ?? []
        );

    $nextTwo =
        (array) (
            $navigationTwo['next']
            ?? []
        );

    $assert(
        (int) (
            $previousTwo['id']
            ?? 0
        ) === $lessonOne
        && (int) (
            $nextTwo['id']
            ?? 0
        ) === $lessonThree
        && !empty(
            $nextTwo['locked']
        ),
        'navegacao anterior e proxima respeita sequencia'
    );

    $learning->completeTextLesson(
        $userId,
        $enrollmentId,
        $lessonTwo
    );

    $stateThree =
        $learning->classroom(
            $userId,
            $enrollmentId,
            $lessonThree
        );

    $nextThree =
        (array) (
            (
                $stateThree[
                    'navigation'
                ]['next']
                ?? []
            )
        );

    $assert(
        $nextThree === [],
        'ultima aula nao possui proxima'
    );

    $learning->completeTextLesson(
        $userId,
        $enrollmentId,
        $lessonThree
    );

    $completedEnrollment =
        $enrollments->findById(
            $enrollmentId
        );

    $assert(
        is_array(
            $completedEnrollment
        )
        && (string) (
            $completedEnrollment[
                'status'
            ]
            ?? ''
        ) === 'completed',
        'curso finaliza ao concluir aulas obrigatorias'
    );

    $certificate =
        $certificates
            ->findByEnrollmentId(
                $enrollmentId
            );

    $assert(
        is_array($certificate),
        'conclusao cria certificado'
    );

    $certificateId =
        (int) (
            $certificate['id']
            ?? 0
        );

    $certificateIds[] =
        $certificateId;

    $assert(
        function_exists(
            'as_has_scheduled_action'
        )
        && as_has_scheduled_action(
            'facil_digital_generate_certificate',
            [$certificateId],
            'facil-digital-courses'
        ),
        'certificado concluido possui job no Action Scheduler'
    );

    $_GET['aula'] =
        (string) $lessonThree;

    ob_start();

    $account->renderClassroom(
        (string) $enrollmentId
    );

    $completedHtml =
        (string) ob_get_clean();

    $assert(
        str_contains(
            $completedHtml,
            'fd-course-completion'
        )
        && str_contains(
            $completedHtml,
            'Curso concluído'
        )
        && str_contains(
            $completedHtml,
            'Acompanhar certificado'
        ),
        'sala exibe estado final e CTA de certificado'
    );

    ob_start();

    $account->renderCertificates();

    $certificatesHtml =
        (string) ob_get_clean();

    $assert(
        str_contains(
            $certificatesHtml,
            'fd-certificate-status-copy'
        )
        && str_contains(
            $certificatesHtml,
            'Em preparação'
        ),
        'aba certificados explica estado pending'
    );

    $javascript =
        file_get_contents(
            FACIL_DIGITAL_CORE_DIR
            . 'assets/frontend/courses.js'
        );

    $css =
        file_get_contents(
            FACIL_DIGITAL_CORE_DIR
            . 'assets/frontend/courses.css'
        );

    $assert(
        is_string($javascript)
        && str_contains(
            $javascript,
            'is-sidebar-collapsed'
        )
        && str_contains(
            $javascript,
            'fd-course-toast'
        )
        && str_contains(
            $javascript,
            'updateProgressUi'
        ),
        'frontend possui sidebar persistente toast e progresso dinamico'
    );

    $assert(
        is_string($css)
        && str_contains(
            $css,
            '.fd-course-lesson-nav'
        )
        && str_contains(
            $css,
            '.fd-course-library-card__media'
        )
        && str_contains(
            $css,
            ':focus-visible'
        ),
        'CSS possui navegacao cards e foco acessivel'
    );

    $queryVars =
        apply_filters(
            'woocommerce_get_query_vars',
            []
        );

    $assert(
        isset(
            $queryVars['cursos'],
            $queryVars['curso'],
            $queryVars['certificados']
        ),
        'endpoints WooCommerce permanecem registrados'
    );

    echo 'COURSES_STUDENT_EXPERIENCE=PASS'
        . PHP_EOL;

    echo 'COURSES_CLASSROOM_NAVIGATION=PASS'
        . PHP_EOL;

    echo 'COURSES_ACTION_SCHEDULER_UI=PASS'
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
        array_reverse(
            array_unique(
                $certificateIds
            )
        )
        as $certificateId
    ) {
        if (
            function_exists(
                'as_unschedule_all_actions'
            )
        ) {
            as_unschedule_all_actions(
                'facil_digital_generate_certificate',
                [$certificateId],
                'facil-digital-courses'
            );
        }

        try {
            $certificate =
                $certificates->findById(
                    $certificateId
                );

            if (
                is_array($certificate)
                && !empty(
                    $certificate[
                        'storage_key'
                    ]
                )
            ) {
                $storage->delete(
                    (string) $certificate[
                        'storage_key'
                    ]
                );
            }
        } catch (Throwable) {
        }

        try {
            $certificates->delete(
                $certificateId
            );
        } catch (Throwable) {
        }
    }

    foreach (
        array_reverse(
            array_unique(
                $enrollmentIds
            )
        )
        as $enrollmentId
    ) {
        try {
            foreach (
                $progress->forEnrollment(
                    $enrollmentId
                )
                as $progressRow
            ) {
                $progress->delete(
                    (int) (
                        $progressRow['id']
                        ?? 0
                    )
                );
            }
        } catch (Throwable) {
        }

        try {
            $enrollments->delete(
                $enrollmentId
            );
        } catch (Throwable) {
        }
    }

    foreach ($orderIds as $orderId) {
        $storedOrder =
            wc_get_order(
                $orderId
            );

        if (
            $storedOrder
            instanceof \WC_Order
        ) {
            $storedOrder->delete(
                true
            );
        }
    }

    foreach (
        array_reverse($lessonIds)
        as $lessonId
    ) {
        try {
            $lessons->delete(
                $lessonId
            );
        } catch (Throwable) {
        }
    }

    foreach (
        array_reverse($moduleIds)
        as $moduleId
    ) {
        try {
            $modules->delete(
                $moduleId
            );
        } catch (Throwable) {
        }
    }

    foreach (
        array_reverse($courseIds)
        as $courseId
    ) {
        try {
            $courses->delete(
                $courseId
            );
        } catch (Throwable) {
        }
    }

    foreach (
        array_reverse($productIds)
        as $productId
    ) {
        $product =
            wc_get_product(
                $productId
            );

        if (
            $product
            instanceof \WC_Product
        ) {
            $product->delete(
                true
            );
        }
    }

    wp_set_current_user(0);

    unset(
        $_GET['aula']
    );

    if (
        !function_exists(
            'wp_delete_user'
        )
    ) {
        require_once ABSPATH
            . 'wp-admin/includes/user.php';
    }

    foreach ($userIds as $userId) {
        wp_delete_user(
            $userId
        );
    }

    remove_filter(
        'pre_wp_mail',
        '__return_true'
    );

    echo 'FIXTURES_CLEANUP=OK'
        . PHP_EOL;
}

if ($failure instanceof Throwable) {
    exit(1);
}