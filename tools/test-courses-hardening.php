<?php

declare(strict_types=1);

use FacilDigital\Core\Core\Database;
use FacilDigital\Core\Courses\CertificateGenerationService;
use FacilDigital\Core\Courses\CertificateRepository;
use FacilDigital\Core\Courses\CourseCommerceModule;
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

global $wpdb;

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

$commerce =
    new CourseCommerceModule(
        $courses,
        $enrollments
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

$storage =
    new CoursePrivateStorage();

$certificateGeneration =
    new CertificateGenerationService(
        $certificates,
        $enrollments,
        $storage
    );

$findEnrollmentByOrder =
    static function (
        array $rows,
        int $orderId
    ): ?array {
        foreach ($rows as $row) {
            if (
                (int) (
                    $row['order_id']
                    ?? 0
                ) === $orderId
            ) {
                return $row;
            }
        }

        return null;
    };

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
            'fd_hardening_'
            . $suffix,
        'user_pass' =>
            wp_generate_password(
                24,
                true,
                true
            ),
        'user_email' =>
            'hardening-'
            . $suffix
            . '@example.com',
        'display_name' =>
            'Aluno Hardening LMS',
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
            'FD Hardening '
            . $suffix,
            'fd-hardening-'
            . $suffix,
            '69.90',
            [
                'short_description' =>
                    'Curso de teste do release candidate.',
                'workload_minutes' =>
                    60,
                'completion_threshold' =>
                    90,
                'navigation_mode' =>
                    'free',
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

    $courseIds[] =
        $courseId;

    $productIds[] =
        $courseProductId;

    $normalProduct =
        new \WC_Product_Simple();

    $normalProduct->set_name(
        'Produto não curso '
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

    $moduleId =
        $modules->create(
            $courseId,
            'Módulo Único',
            '',
            10
        );

    $moduleIds[] =
        $moduleId;

    $lessonId =
        $lessons->create(
            $courseId,
            $moduleId,
            'Aula Final',
            'aula-final-'
            . $suffix,
            [
                'lesson_type' =>
                    'text',
                'content' =>
                    '<p>Aula de teste.</p>',
                'is_required' =>
                    true,
                'sort_order' =>
                    10,
                'status' =>
                    'published',
            ]
        );

    $lessonIds[] =
        $lessonId;

    $orderA = wc_create_order([
        'customer_id' =>
            $userId,
    ]);

    if (
        is_wp_error($orderA)
        || !$orderA
            instanceof \WC_Order
    ) {
        throw new RuntimeException(
            'fixture_order_a_failed'
        );
    }

    $orderA->add_product(
        wc_get_product(
            $courseProductId
        ),
        1
    );

    $orderA->add_product(
        wc_get_product(
            $normalProductId
        ),
        1
    );

    $orderA->calculate_totals();
    $orderA->save();

    $orderAId =
        (int) $orderA->get_id();

    $orderIds[] =
        $orderAId;

    $orderA->update_status(
        'processing'
    );

    $commerce->grantFromOrder(
        $orderAId
    );

    $rows =
        $enrollments->forUser(
            $userId
        );

    $assert(
        count($rows) === 1,
        'pedido misto cria somente uma matricula de curso'
    );

    $enrollmentA =
        $findEnrollmentByOrder(
            $rows,
            $orderAId
        );

    $assert(
        is_array($enrollmentA),
        'primeiro pedido possui matricula auditavel'
    );

    $enrollmentAId =
        (int) $enrollmentA['id'];

    $enrollmentIds[] =
        $enrollmentAId;

    $orderA->update_status(
        'completed'
    );

    $commerce->grantFromOrder(
        $orderAId
    );

    $assert(
        count(
            $enrollments->forUser(
                $userId
            )
        ) === 1,
        'processing e completed sao idempotentes no mesmo pedido'
    );

    $orderB = wc_create_order([
        'customer_id' =>
            $userId,
    ]);

    if (
        is_wp_error($orderB)
        || !$orderB
            instanceof \WC_Order
    ) {
        throw new RuntimeException(
            'fixture_order_b_failed'
        );
    }

    $orderB->add_product(
        wc_get_product(
            $courseProductId
        ),
        1
    );

    $orderB->calculate_totals();
    $orderB->save();

    $orderBId =
        (int) $orderB->get_id();

    $orderIds[] =
        $orderBId;

    $orderB->update_status(
        'processing'
    );

    $commerce->grantFromOrder(
        $orderBId
    );

    $rows =
        $enrollments->forUser(
            $userId
        );

    $assert(
        count($rows) === 2,
        'segunda compra preserva matricula por pedido'
    );

    $enrollmentB =
        $findEnrollmentByOrder(
            $rows,
            $orderBId
        );

    $assert(
        is_array($enrollmentB),
        'segunda compra possui matricula auditavel'
    );

    $enrollmentBId =
        (int) $enrollmentB['id'];

    $enrollmentIds[] =
        $enrollmentBId;

    $library =
        $learning->userCourses(
            $userId
        );

    $assert(
        count($library) === 1,
        'compras repetidas exibem um unico card de curso'
    );

    echo 'COURSES_REPEAT_PURCHASE=PASS'
        . PHP_EOL;

    $orderA->update_status(
        'refunded'
    );

    $commerce->revokeFromOrder(
        $orderAId
    );

    $enrollmentA =
        $enrollments->findById(
            $enrollmentAId
        );

    $enrollmentB =
        $enrollments->findById(
            $enrollmentBId
        );

    $assert(
        is_array($enrollmentA)
        && (string) (
            $enrollmentA['status']
            ?? ''
        ) === 'revoked'
        && is_array($enrollmentB)
        && (string) (
            $enrollmentB['status']
            ?? ''
        ) === 'active',
        'reembolso revoga somente a matricula do pedido correspondente'
    );

    $library =
        $learning->userCourses(
            $userId
        );

    $assert(
        count($library) === 1
        && (int) (
            $library[0][
                'enrollment'
            ]['id']
            ?? 0
        ) === $enrollmentBId,
        'segunda compra mantem acesso apos reembolso da primeira'
    );

    $wpdb->update(
        Database::table(
            'course_enrollments'
        ),
        [
            'expires_at' =>
                gmdate(
                    'Y-m-d H:i:s',
                    time() - 3600
                ),
        ],
        [
            'id' =>
                $enrollmentBId,
        ]
    );

    $assert(
        $learning->userCourses(
            $userId
        ) === [],
        'matricula expirada nao aparece em Meus Cursos'
    );

    $expectRuntimeException(
        static function () use (
            $learning,
            $userId,
            $enrollmentBId,
            $lessonId
        ): void {
            $learning->classroom(
                $userId,
                $enrollmentBId,
                $lessonId
            );
        },
        'course_access_expired',
        'matricula expirada bloqueia sala de aula'
    );

    $wpdb->update(
        Database::table(
            'course_enrollments'
        ),
        [
            'expires_at' =>
                gmdate(
                    'Y-m-d H:i:s',
                    time() + 86400
                ),
        ],
        [
            'id' =>
                $enrollmentBId,
        ]
    );

    $assert(
        count(
            $learning->userCourses(
                $userId
            )
        ) === 1,
        'matricula valida volta a biblioteca'
    );

    echo 'COURSES_EXPIRATION_GUARD=PASS'
        . PHP_EOL;

    $orderB->update_status(
        'refunded'
    );

    $commerce->revokeFromOrder(
        $orderBId
    );

    $assert(
        $learning->userCourses(
            $userId
        ) === [],
        'todos os pedidos revogados removem acesso'
    );

    $orderB->update_status(
        'processing'
    );

    $commerce->grantFromOrder(
        $orderBId
    );

    $reactivated =
        $enrollments->findById(
            $enrollmentBId
        );

    $assert(
        is_array($reactivated)
        && (string) (
            $reactivated['status']
            ?? ''
        ) === 'active',
        'pedido pago novamente reativa matricula revogada'
    );

    $result =
        $learning->completeTextLesson(
            $userId,
            $enrollmentBId,
            $lessonId
        );

    $assert(
        !empty(
            $result[
                'course_completed'
            ]
        ),
        'conclusao continua funcional apos reativacao'
    );

    $certificate =
        $certificates
            ->findByEnrollmentId(
                $enrollmentBId
            );

    $assert(
        is_array($certificate),
        'conclusao gera certificado pendente'
    );

    $certificateId =
        (int) $certificate['id'];

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
        'certificado entra na fila do Action Scheduler'
    );

    do_action(
        'facil_digital_generate_certificate',
        $certificateId
    );

    $readyCertificate =
        $certificates->findById(
            $certificateId
        );

    $assert(
        is_array($readyCertificate)
        && (string) (
            $readyCertificate[
                'status'
            ]
            ?? ''
        ) === 'ready',
        'worker do certificado gera PDF pronto'
    );

    $certificateGeneration
        ->authorizeDownload(
            $userId,
            $certificateId
        );

    $orderB->update_status(
        'refunded'
    );

    $commerce->revokeFromOrder(
        $orderBId
    );

    $expectRuntimeException(
        static function () use (
            $certificateGeneration,
            $userId,
            $certificateId
        ): void {
            $certificateGeneration
                ->authorizeDownload(
                    $userId,
                    $certificateId
                );
        },
        'certificate_access_denied',
        'revogacao bloqueia download autenticado do certificado'
    );

    echo 'COURSES_REVOCATION_HARDENING=PASS'
        . PHP_EOL;

    echo 'COURSES_ACTION_SCHEDULER_WORKER=PASS'
        . PHP_EOL;

    echo 'COURSES_RELEASE_HARDENING=PASS'
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
        $order =
            wc_get_order(
                $orderId
            );

        if (
            $order
            instanceof \WC_Order
        ) {
            $order->delete(true);
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