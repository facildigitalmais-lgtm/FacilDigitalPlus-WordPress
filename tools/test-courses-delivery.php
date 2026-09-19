<?php

declare(strict_types=1);

use FacilDigital\Core\Courses\CertificateGenerationService;
use FacilDigital\Core\Courses\CertificateRepository;
use FacilDigital\Core\Courses\CourseDeliveryModule;
use FacilDigital\Core\Courses\CourseModuleRepository;
use FacilDigital\Core\Courses\CoursePrivateStorage;
use FacilDigital\Core\Courses\CourseProductService;
use FacilDigital\Core\Courses\CourseRepository;
use FacilDigital\Core\Courses\CourseResourceService;
use FacilDigital\Core\Courses\EnrollmentRepository;
use FacilDigital\Core\Courses\LessonRepository;
use FacilDigital\Core\Courses\LessonResourceRepository;

if (wp_get_environment_type() !== 'development') {
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
$resourceIds = [];
$certificateIds = [];
$tempFiles = [];
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

$certificates =
    new CertificateRepository();

$storage =
    new CoursePrivateStorage();

$products =
    new CourseProductService(
        $courses
    );

$resourceService =
    new CourseResourceService(
        $resources,
        $lessons,
        $enrollments,
        $storage
    );

$certificateService =
    new CertificateGenerationService(
        $certificates,
        $enrollments,
        $storage
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

    $userId = wp_insert_user([
        'user_login' =>
            'fd_delivery_student_'
            . $suffix,
        'user_pass' =>
            wp_generate_password(
                24,
                true,
                true
            ),
        'user_email' =>
            'delivery-student-'
            . $suffix
            . '@example.com',
        'display_name' =>
            'Aluno Certificado',
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

    $otherUserId = wp_insert_user([
        'user_login' =>
            'fd_delivery_other_'
            . $suffix,
        'user_pass' =>
            wp_generate_password(
                24,
                true,
                true
            ),
        'user_email' =>
            'delivery-other-'
            . $suffix
            . '@example.com',
        'display_name' =>
            'Outro Aluno',
        'role' =>
            'customer',
    ]);

    if (is_wp_error($otherUserId)) {
        throw new RuntimeException(
            'fixture_other_user_create_failed'
        );
    }

    $otherUserId =
        (int) $otherUserId;

    $userIds[] =
        $otherUserId;

    $created =
        $products->createCourseProduct(
            'FD Delivery Course '
            . $suffix,
            'fd-delivery-'
            . $suffix,
            '79.90',
            [
                'workload_minutes' =>
                    90,
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

    $productId =
        (int) $created['product_id'];

    $courseIds[] =
        $courseId;

    $productIds[] =
        $productId;

    $moduleId =
        $modules->create(
            $courseId,
            'Modulo de Recursos',
            '',
            10
        );

    $moduleIds[] =
        $moduleId;

    $lessonId =
        $lessons->create(
            $courseId,
            $moduleId,
            'Aula com Material',
            'aula-material-'
            . $suffix,
            [
                'lesson_type' =>
                    'text',
                'content' =>
                    '<p>Conteudo.</p>',
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

    $userEnrollments =
        $enrollments->forUser(
            $userId
        );

    $assert(
        count($userEnrollments)
        === 1,
        'fixture possui matricula ativa'
    );

    $enrollmentId =
        (int) $userEnrollments[0]['id'];

    $enrollmentIds[] =
        $enrollmentId;

    $tmpFile = wp_tempnam(
        'fd-course-resource.txt'
    );

    if (!is_string($tmpFile)) {
        throw new RuntimeException(
            'fixture_temp_file_failed'
        );
    }

    $tempFiles[] =
        $tmpFile;

    $resourceContents =
        "Material privado Facil Digital+\n"
        . $suffix
        . "\n";

    file_put_contents(
        $tmpFile,
        $resourceContents
    );

    $resourceId =
        $resourceService->upload(
            $courseId,
            $lessonId,
            'Material complementar',
            [
                'name' =>
                    'material-aula.txt',
                'type' =>
                    'text/plain',
                'tmp_name' =>
                    $tmpFile,
                'error' =>
                    UPLOAD_ERR_OK,
                'size' =>
                    filesize($tmpFile),
            ],
            10,
            'active'
        );

    $resourceIds[] =
        $resourceId;

    $resource =
        $resources->findById(
            $resourceId
        );

    $assert(
        is_array($resource)
        && (int) (
            $resource['file_size']
            ?? 0
        ) === strlen(
            $resourceContents
        )
        && preg_match(
            '/^[a-f0-9]{64}$/',
            (string) (
                $resource['sha256']
                ?? ''
            )
        ) === 1,
        'upload persiste tamanho e SHA-256'
    );

    $storageKey = (string) (
        $resource['storage_key']
        ?? ''
    );

    $assert(
        str_starts_with(
            $storageKey,
            'courses/resources/'
        )
        && !str_contains(
            $storageKey,
            'material-aula'
        ),
        'storage key usa nome fisico aleatorio'
    );

    $resourcePath =
        $storage->path(
            $storageKey
        );

    $assert(
        is_file($resourcePath)
        && !str_starts_with(
            $resourcePath,
            rtrim(
                ABSPATH,
                '/\\'
            )
            . DIRECTORY_SEPARATOR
        ),
        'recurso armazenado fora do webroot'
    );

    $authorization =
        $resourceService->authorize(
            $userId,
            $resourceId
        );

    $assert(
        $authorization['path']
        === $resourcePath,
        'aluno matriculado autoriza recurso privado'
    );

    $expectRuntimeException(
        static function () use (
            $resourceService,
            $otherUserId,
            $resourceId
        ): void {
            $resourceService->authorize(
                $otherUserId,
                $resourceId
            );
        },
        'course_resource_access_denied',
        'outro aluno nao acessa recurso privado'
    );

    $expectRuntimeException(
        static function () use (
            $storage
        ): void {
            $storage->path(
                'courses/../segredo.txt'
            );
        },
        'course_storage_key_invalid',
        'path traversal em storage key e rejeitado'
    );

    file_put_contents(
        $resourcePath,
        $resourceContents
        . 'tamper'
    );

    $expectRuntimeException(
        static function () use (
            $resourceService,
            $userId,
            $resourceId
        ): void {
            $resourceService->authorize(
                $userId,
                $resourceId
            );
        },
        'course_storage_integrity_failed',
        'hash divergente bloqueia recurso adulterado'
    );

    file_put_contents(
        $resourcePath,
        $resourceContents
    );

    $resourceService->authorize(
        $userId,
        $resourceId
    );

    echo 'COURSES_RESOURCE_PRIVATE_DELIVERY=PASS'
        . PHP_EOL;

    $enrollments->markCompleted(
        $enrollmentId
    );

    $certificateId =
        $certificates->createPending(
            $enrollmentId,
            'Aluno Certificado',
            'FD Delivery Course '
            . $suffix,
            90
        );

    $certificateIds[] =
        $certificateId;

    $ready =
        $certificateService->generate(
            $certificateId
        );

    $assert(
        (string) (
            $ready['status']
            ?? ''
        ) === 'ready'
        && preg_match(
            '/^[a-f0-9]{64}$/',
            (string) (
                $ready['sha256']
                ?? ''
            )
        ) === 1,
        'certificado PDF gerado e marcado como ready'
    );

    $certificatePath =
        $storage->path(
            (string) $ready[
                'storage_key'
            ]
        );

    $header = file_get_contents(
        $certificatePath,
        false,
        null,
        0,
        5
    );

    $assert(
        $header === '%PDF-',
        'arquivo de certificado possui assinatura PDF'
    );

    $firstStorageKey =
        (string) $ready[
            'storage_key'
        ];

    $firstHash =
        (string) $ready[
            'sha256'
        ];

    $secondReady =
        $certificateService->generate(
            $certificateId
        );

    $assert(
        (string) (
            $secondReady['storage_key']
            ?? ''
        ) === $firstStorageKey
        && (string) (
            $secondReady['sha256']
            ?? ''
        ) === $firstHash,
        'geracao de certificado e idempotente'
    );

    $certificateAuthorization =
        $certificateService
            ->authorizeDownload(
                $userId,
                $certificateId
            );

    $assert(
        $certificateAuthorization[
            'path'
        ] === $certificatePath,
        'dono autoriza download do certificado'
    );

    $expectRuntimeException(
        static function () use (
            $certificateService,
            $otherUserId,
            $certificateId
        ): void {
            $certificateService
                ->authorizeDownload(
                    $otherUserId,
                    $certificateId
                );
        },
        'certificate_access_denied',
        'outro aluno nao baixa certificado'
    );

    echo 'COURSES_CERTIFICATE_GENERATION=PASS'
        . PHP_EOL;

    $verificationCode = (string) (
        $ready['verification_code']
        ?? ''
    );

    $verified =
        $certificateService->verification(
            $verificationCode
        );

    $assert(
        is_array($verified)
        && (int) (
            $verified['id']
            ?? 0
        ) === $certificateId,
        'codigo publico verifica certificado valido'
    );

    $assert(
        $certificateService->verification(
            'CODIGO-INEXISTENTE'
        ) === null,
        'codigo publico inexistente nao valida'
    );

    $certificateContents =
        file_get_contents(
            $certificatePath
        );

    if (
        !is_string(
            $certificateContents
        )
    ) {
        throw new RuntimeException(
            'fixture_certificate_read_failed'
        );
    }

    file_put_contents(
        $certificatePath,
        $certificateContents
        . 'tamper'
    );

    $assert(
        $certificateService->verification(
            $verificationCode
        ) === null,
        'certificado adulterado falha na verificacao publica'
    );

    file_put_contents(
        $certificatePath,
        $certificateContents
    );

    $assert(
        $certificateService->verification(
            $verificationCode
        ) !== null,
        'certificado restaurado volta a validar'
    );

    $queryVars = apply_filters(
        'query_vars',
        []
    );

    $assert(
        in_array(
            'fd_verify_certificate',
            $queryVars,
            true
        )
        && has_action(
            'facil_digital_certificate_pending'
        ) !== false
        && has_action(
            'facil_digital_generate_certificate'
        ) !== false,
        'rota publica e fila de certificado registradas'
    );

    $assert(
        CourseDeliveryModule::resourceUrl(
            $resourceId
        ) !== ''
        && CourseDeliveryModule::certificateUrl(
            $certificateId
        ) !== ''
        && CourseDeliveryModule::verificationUrl(
            $verificationCode
        ) !== '',
        'URLs protegidas e de verificacao geradas'
    );

    echo 'COURSES_CERTIFICATE_VERIFICATION=PASS'
        . PHP_EOL;

    echo 'COURSES_DELIVERY_SECURITY=PASS'
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
            $resourceIds
        )
        as $resourceId
    ) {
        try {
            $resource =
                $resources->findById(
                    $resourceId
                );

            if (is_array($resource)) {
                $resourceService->delete(
                    (int) $resource[
                        'course_id'
                    ],
                    $resourceId
                );
            }
        } catch (Throwable) {
        }
    }

    foreach (
        array_reverse(
            $certificateIds
        )
        as $certificateId
    ) {
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
            $enrollmentIds
        )
        as $enrollmentId
    ) {
        try {
            $enrollments->delete(
                $enrollmentId
            );
        } catch (Throwable) {
        }
    }

    foreach (
        $orderIds
        as $orderId
    ) {
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
        $lessonIds
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
        $moduleIds
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
        $courseIds
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
        $productIds
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

    if (!function_exists(
        'wp_delete_user'
    )) {
        require_once ABSPATH
            . 'wp-admin/includes/user.php';
    }

    foreach (
        $userIds
        as $userId
    ) {
        wp_delete_user(
            $userId
        );
    }

    foreach (
        $tempFiles
        as $tempFile
    ) {
        if (is_file($tempFile)) {
            @unlink($tempFile);
        }
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