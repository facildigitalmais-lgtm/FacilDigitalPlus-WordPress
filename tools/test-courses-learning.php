<?php

declare(strict_types=1);

use FacilDigital\Core\Core\Database;
use FacilDigital\Core\Courses\CertificateRepository;
use FacilDigital\Core\Courses\CourseAccountModule;
use FacilDigital\Core\Courses\CourseCommerceModule;
use FacilDigital\Core\Courses\CourseLearningService;
use FacilDigital\Core\Courses\CourseModuleRepository;
use FacilDigital\Core\Courses\CourseProductService;
use FacilDigital\Core\Courses\CourseRepository;
use FacilDigital\Core\Courses\EnrollmentRepository;
use FacilDigital\Core\Courses\LessonRepository;
use FacilDigital\Core\Courses\LessonResourceRepository;
use FacilDigital\Core\Courses\ProgressRepository;

if (wp_get_environment_type() !== 'development') {
    throw new RuntimeException(
        'Este teste so pode rodar em development.'
    );
}

global $wpdb;
global $wp_filter;

$userIds = [];
$productIds = [];
$orderIds = [];
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

$hasModuleCallback =
    static function (
        string $hook,
        string $class,
        string $method
    ): bool {
        global $wp_filter;

        if (
            !isset($wp_filter[$hook])
            || !is_object(
                $wp_filter[$hook]
            )
        ) {
            return false;
        }

        foreach (
            $wp_filter[$hook]->callbacks
            as $priorityCallbacks
        ) {
            foreach (
                $priorityCallbacks
                as $callback
            ) {
                $function =
                    $callback['function']
                    ?? null;

                if (
                    is_array($function)
                    && isset(
                        $function[0],
                        $function[1]
                    )
                    && is_object(
                        $function[0]
                    )
                    && $function[0]
                        instanceof $class
                    && $function[1]
                        === $method
                ) {
                    return true;
                }
            }
        }

        return false;
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

$progress = new ProgressRepository(
    $enrollments,
    $lessons
);

$certificates = new CertificateRepository(
    $enrollments
);

$products = new CourseProductService(
    $courses
);

$commerce = new CourseCommerceModule(
    $courses,
    $enrollments
);

$learning = new CourseLearningService(
    $courses,
    $modules,
    $lessons,
    $resources,
    $enrollments,
    $progress,
    $certificates
);

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

    $userId = wp_insert_user(
        [
            'user_login' =>
                'fd_course_student_'
                . $suffix,
            'user_pass' =>
                wp_generate_password(
                    24,
                    true,
                    true
                ),
            'user_email' =>
                'course-student-'
                . $suffix
                . '@example.com',
            'display_name' =>
                'Aluno Curso Teste',
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

    $otherUserId = wp_insert_user(
        [
            'user_login' =>
                'fd_course_other_'
                . $suffix,
            'user_pass' =>
                wp_generate_password(
                    24,
                    true,
                    true
                ),
            'user_email' =>
                'course-other-'
                . $suffix
                . '@example.com',
            'display_name' =>
                'Outro Aluno',
            'role' =>
                'customer',
        ]
    );

    if (is_wp_error($otherUserId)) {
        throw new RuntimeException(
            'fixture_other_user_create_failed'
        );
    }

    $otherUserId =
        (int) $otherUserId;

    $userIds[] = $otherUserId;

    $created =
        $products->createCourseProduct(
            'FD Learning Course '
                . $suffix,
            'fd-learning-'
                . $suffix,
            '99.90',
            [
                'short_description' =>
                    'Curso funcional de teste',
                'workload_minutes' =>
                    60,
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

    $courseProductId =
        (int) $created['product_id'];

    $courseIds[] = $courseId;
    $productIds[] = $courseProductId;

    $normalProduct =
        new WC_Product_Simple();

    $normalProduct->set_name(
        'FD Normal Product '
        . $suffix
    );

    $normalProduct->set_regular_price(
        '10.00'
    );

    $normalProduct->set_price(
        '10.00'
    );

    $normalProduct->set_status(
        'draft'
    );

    $normalProductId =
        (int) $normalProduct->save();

    $productIds[] =
        $normalProductId;

    $moduleId = $modules->create(
        $courseId,
        'Modulo Unico',
        '',
        10
    );

    $moduleIds[] = $moduleId;

    $videoLessonId =
        $lessons->create(
            $courseId,
            $moduleId,
            'Video Aula',
            'video-aula-'
                . $suffix,
            [
                'lesson_type' =>
                    'video',
                'youtube_video_id' =>
                    'dQw4w9WgXcQ',
                'duration_seconds' =>
                    30,
                'is_required' =>
                    true,
                'sort_order' =>
                    10,
                'status' =>
                    'published',
            ]
        );

    $lessonIds[] =
        $videoLessonId;

    $textLessonId =
        $lessons->create(
            $courseId,
            $moduleId,
            'Leitura Final',
            'leitura-final-'
                . $suffix,
            [
                'lesson_type' =>
                    'text',
                'content' =>
                    '<p>Conteudo final.</p>',
                'is_required' =>
                    true,
                'sort_order' =>
                    20,
                'status' =>
                    'published',
            ]
        );

    $lessonIds[] =
        $textLessonId;

    $order = wc_create_order(
        [
            'customer_id' =>
                $userId,
        ]
    );

    if (
        is_wp_error($order)
        || !$order instanceof WC_Order
    ) {
        throw new RuntimeException(
            'fixture_order_create_failed'
        );
    }

    $order->add_product(
        wc_get_product(
            $courseProductId
        ),
        1
    );

    $order->add_product(
        wc_get_product(
            $normalProductId
        ),
        1
    );

    $order->calculate_totals();
    $order->save();

    $orderId =
        (int) $order->get_id();

    $orderIds[] = $orderId;

    $order->update_status(
        'processing'
    );

    $studentEnrollments =
        $enrollments->forUser(
            $userId
        );

    $assert(
        count($studentEnrollments)
        === 1,
        'pedido pago cria somente matricula de produto curso'
    );

    $enrollment =
        $studentEnrollments[0];

    $enrollmentId =
        (int) $enrollment['id'];

    $enrollmentIds[] =
        $enrollmentId;

    $assert(
        (int) (
            $enrollment['course_id']
            ?? 0
        ) === $courseId
        && (int) (
            $enrollment['product_id']
            ?? 0
        ) === $courseProductId
        && (int) (
            $enrollment['order_id']
            ?? 0
        ) === $orderId,
        'matricula referencia curso produto e pedido'
    );

    $commerce->grantFromOrder(
        $orderId
    );

    $assert(
        count(
            $enrollments->forUser(
                $userId
            )
        ) === 1,
        'sincronizacao de pedido e idempotente'
    );

    echo 'COURSES_COMMERCE_ENROLLMENT=PASS'
        . PHP_EOL;

    $library =
        $learning->userCourses(
            $userId
        );

    $assert(
        count($library) === 1
        && (int) (
            $library[0][
                'continue_lesson_id'
            ]
            ?? 0
        ) === $videoLessonId,
        'area do aluno encontra curso e primeira aula'
    );

    $classroom =
        $learning->classroom(
            $userId,
            $enrollmentId,
            $videoLessonId
        );

    $assert(
        (int) (
            $classroom['lesson']['id']
            ?? 0
        ) === $videoLessonId,
        'aluno autorizado abre primeira aula'
    );

    $expectRuntimeException(
        static function () use (
            $learning,
            $userId,
            $enrollmentId,
            $textLessonId
        ): void {
            $learning->classroom(
                $userId,
                $enrollmentId,
                $textLessonId
            );
        },
        'course_lesson_locked',
        'navegacao sequencial bloqueia aula seguinte'
    );

    $expectRuntimeException(
        static function () use (
            $learning,
            $otherUserId,
            $enrollmentId,
            $videoLessonId
        ): void {
            $learning->classroom(
                $otherUserId,
                $enrollmentId,
                $videoLessonId
            );
        },
        'course_access_denied',
        'matricula nao pode ser acessada por outro usuario'
    );

    $learning->recordVideoProgress(
        $userId,
        $enrollmentId,
        $videoLessonId,
        10,
        30,
        10
    );

    $learning->recordVideoProgress(
        $userId,
        $enrollmentId,
        $videoLessonId,
        20,
        30,
        10
    );

    $videoResult =
        $learning->recordVideoProgress(
            $userId,
            $enrollmentId,
            $videoLessonId,
            30,
            30,
            10
        );

    $assert(
        !empty(
            $videoResult[
                'lesson_completed'
            ]
        ),
        'video conclui ao atingir threshold'
    );

    $videoProgress =
        $progress
            ->findForEnrollmentLesson(
                $enrollmentId,
                $videoLessonId
            );

    if (is_array($videoProgress)) {
        $progressIds[] =
            (int) $videoProgress['id'];
    }

    $classroom =
        $learning->classroom(
            $userId,
            $enrollmentId,
            $textLessonId
        );

    $assert(
        (int) (
            $classroom['lesson']['id']
            ?? 0
        ) === $textLessonId,
        'segunda aula desbloqueia apos conclusao da primeira'
    );

    $textResult =
        $learning->completeTextLesson(
            $userId,
            $enrollmentId,
            $textLessonId
        );

    $assert(
        !empty(
            $textResult[
                'lesson_completed'
            ]
        )
        && !empty(
            $textResult[
                'course_completed'
            ]
        ),
        'aula de texto conclui curso'
    );

    $textProgress =
        $progress
            ->findForEnrollmentLesson(
                $enrollmentId,
                $textLessonId
            );

    if (is_array($textProgress)) {
        $progressIds[] =
            (int) $textProgress['id'];
    }

    $completedEnrollment =
        $enrollments->findById(
            $enrollmentId
        );

    $assert(
        is_array($completedEnrollment)
        && (string) (
            $completedEnrollment[
                'status'
            ]
            ?? ''
        ) === 'completed',
        'matricula marcada como concluida'
    );

    echo 'COURSES_LEARNING_PROGRESS=PASS'
        . PHP_EOL;

    $studentCertificates =
        $certificates->forUser(
            $userId
        );

    $assert(
        count($studentCertificates)
        === 1
        && (string) (
            $studentCertificates[0][
                'status'
            ]
            ?? ''
        ) === 'pending',
        'conclusao cria certificado pendente'
    );

    $certificateIds[] =
        (int) $studentCertificates[0]['id'];

    echo 'COURSES_CERTIFICATE_TRIGGER=PASS'
        . PHP_EOL;

    $queryVars =
        apply_filters(
            'woocommerce_get_query_vars',
            []
        );

    $assert(
        isset(
            $queryVars[
                CourseAccountModule::COURSES_ENDPOINT
            ],
            $queryVars[
                CourseAccountModule::CLASSROOM_ENDPOINT
            ],
            $queryVars[
                CourseAccountModule::CERTIFICATES_ENDPOINT
            ]
        ),
        'query vars de cursos registradas no WooCommerce'
    );

    $assert(
        has_action(
            'woocommerce_account_cursos_endpoint'
        ) !== false
        && has_action(
            'woocommerce_account_curso_endpoint'
        ) !== false
        && has_action(
            'woocommerce_account_certificados_endpoint'
        ) !== false,
        'endpoints da area do aluno registrados'
    );

    $assert(
        has_action(
            'wp_ajax_fd_course_progress'
        ) !== false
        && has_action(
            'wp_ajax_fd_course_complete_text'
        ) !== false,
        'AJAX autenticado de aprendizagem registrado'
    );

    $assert(
        has_action(
            'wp_ajax_nopriv_fd_course_progress'
        ) === false
        && has_action(
            'wp_ajax_nopriv_fd_course_complete_text'
        ) === false,
        'tracking nao possui endpoint anonimo'
    );

    $assert(
        $hasModuleCallback(
            'woocommerce_order_status_processing',
            CourseCommerceModule::class,
            'grantFromOrder'
        )
        && $hasModuleCallback(
            'woocommerce_order_status_refunded',
            CourseCommerceModule::class,
            'revokeFromOrder'
        ),
        'hooks comerciais do curso registrados'
    );

    $assert(
        is_readable(
            FACIL_DIGITAL_CORE_DIR
            . 'assets/frontend/courses.css'
        )
        && is_readable(
            FACIL_DIGITAL_CORE_DIR
            . 'assets/frontend/courses.js'
        ),
        'assets frontend dos cursos presentes'
    );

    echo 'COURSES_ACCOUNT_ENDPOINTS=PASS'
        . PHP_EOL;

    $entitlementsTable =
        Database::table(
            'entitlements'
        );

    $entitlementCount =
        (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$entitlementsTable}
                 WHERE product_id = %d",
                $courseProductId
            )
        );

    $assert(
        $entitlementCount === 0,
        'curso continua isolado de entitlement de apostila'
    );

    $order->update_status(
        'refunded'
    );

    $revoked =
        $enrollments->findById(
            $enrollmentId
        );

    $assert(
        is_array($revoked)
        && (string) (
            $revoked['status']
            ?? ''
        ) === 'revoked',
        'pedido reembolsado revoga acesso ao curso'
    );

    $expectRuntimeException(
        static function () use (
            $learning,
            $userId,
            $enrollmentId,
            $videoLessonId
        ): void {
            $learning->classroom(
                $userId,
                $enrollmentId,
                $videoLessonId
            );
        },
        'course_access_denied',
        'matricula revogada bloqueia sala de aula'
    );
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
        $wpdb->delete(
            Database::table(
                'certificates'
            ),
            ['id' => $certificateId],
            ['%d']
        );
    }

    foreach (
        array_reverse(
            array_unique(
                $progressIds
            )
        )
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
        array_reverse(
            array_unique(
                $enrollmentIds
            )
        )
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
        array_reverse($orderIds)
        as $orderId
    ) {
        $storedOrder =
            wc_get_order($orderId);

        if ($storedOrder instanceof WC_Order) {
            $storedOrder->delete(true);
        }
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
        $product =
            wc_get_product($productId);

        if ($product instanceof WC_Product) {
            $product->delete(true);
        }
    }

    if (
        !function_exists(
            'wp_delete_user'
        )
    ) {
        require_once ABSPATH
            . 'wp-admin/includes/user.php';
    }

    foreach ($userIds as $userId) {
        wp_delete_user($userId);
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