<?php

declare(strict_types=1);

use FacilDigital\Core\Core\Database;
use FacilDigital\Core\Courses\CoursePrivateStorage;

$failed = false;

$check = static function (
    bool $condition,
    string $label
) use (&$failed): void {
    if ($condition) {
        echo 'PASS: '
            . $label
            . PHP_EOL;

        return;
    }

    $failed = true;

    echo 'FAIL: '
        . $label
        . PHP_EOL;
};

echo '=================================================='
    . PHP_EOL;

echo 'FACIL DIGITAL+ COURSES - RUNTIME READINESS'
    . PHP_EOL;

echo '=================================================='
    . PHP_EOL;

$check(
    Database::installedVersion()
        === Database::SCHEMA_VERSION,
    'schema instalado corresponde ao declarado'
);

echo 'SCHEMA='
    . Database::installedVersion()
    . PHP_EOL;

$check(
    class_exists('TCPDF'),
    'TCPDF disponivel no autoload'
);

$check(
    function_exists(
        'as_enqueue_async_action'
    )
    && function_exists(
        'as_has_scheduled_action'
    )
    && function_exists(
        'as_get_scheduled_actions'
    ),
    'Action Scheduler API disponivel'
);

$pendingCertificateActions = [];

if (
    function_exists(
        'as_get_scheduled_actions'
    )
    && class_exists(
        'ActionScheduler_Store'
    )
) {
    $pendingCertificateActions =
        as_get_scheduled_actions(
            [
                'hook' =>
                    'facil_digital_generate_certificate',
                'group' =>
                    'facil-digital-courses',
                'status' =>
                    ActionScheduler_Store::STATUS_PENDING,
                'per_page' =>
                    100,
            ],
            'ids'
        );
}

echo 'CERTIFICATE_PENDING_ACTIONS='
    . count(
        is_array(
            $pendingCertificateActions
        )
            ? $pendingCertificateActions
            : []
    )
    . PHP_EOL;

$queryVars =
    apply_filters(
        'woocommerce_get_query_vars',
        []
    );

$check(
    isset(
        $queryVars['cursos'],
        $queryVars['curso'],
        $queryVars['certificados']
    ),
    'endpoints WooCommerce registrados'
);

$publicQueryVars =
    apply_filters(
        'query_vars',
        []
    );

$check(
    in_array(
        'fd_verify_certificate',
        $publicQueryVars,
        true
    ),
    'query var de verificacao publica registrada'
);

$check(
    has_action(
        'woocommerce_order_status_processing'
    ) !== false
    && has_action(
        'woocommerce_order_status_completed'
    ) !== false
    && has_action(
        'woocommerce_order_status_refunded'
    ) !== false,
    'hooks comerciais de cursos registrados'
);

$check(
    has_action(
        'wp_ajax_fd_course_progress'
    ) !== false
    && has_action(
        'wp_ajax_fd_course_complete_text'
    ) !== false
    && has_action(
        'wp_ajax_nopriv_fd_course_progress'
    ) === false
    && has_action(
        'wp_ajax_nopriv_fd_course_complete_text'
    ) === false,
    'tracking exige usuario autenticado'
);

$javascript = file_get_contents(
    FACIL_DIGITAL_CORE_DIR
    . 'assets/frontend/courses.js'
);

$check(
    is_string($javascript)
    && str_contains(
        $javascript,
        'origin:'
    )
    && str_contains(
        $javascript,
        'window.location'
    )
    && str_contains(
        $javascript,
        '.origin'
    ),
    'player YouTube declara origin'
);

$storage =
    new CoursePrivateStorage();

$reflection =
    new ReflectionClass($storage);

$rootMethod =
    $reflection->getMethod(
        'root'
    );

$rootMethod->setAccessible(true);

$privateRoot =
    (string) $rootMethod->invoke(
        $storage
    );

$publicRoot = rtrim(
    wp_normalize_path(
        ABSPATH
    ),
    '/'
);

$check(
    $privateRoot !== ''
    && $privateRoot !== $publicRoot
    && !str_starts_with(
        $privateRoot,
        $publicRoot . '/'
    ),
    'storage LMS fica fora do webroot'
);

echo 'COURSE_PRIVATE_ROOT='
    . $privateRoot
    . PHP_EOL;

echo 'DISABLE_WP_CRON='
    . (
        defined('DISABLE_WP_CRON')
        && DISABLE_WP_CRON
            ? 'yes'
            : 'no'
    )
    . PHP_EOL;

global $wp_rewrite;

$rules =
    $wp_rewrite->wp_rewrite_rules();

$verificationRewrite = false;

if (is_array($rules)) {
    foreach (
        array_keys($rules)
        as $pattern
    ) {
        if (
            str_contains(
                (string) $pattern,
                'verificar-certificado'
            )
        ) {
            $verificationRewrite = true;
            break;
        }
    }
}

$check(
    $verificationRewrite,
    'rewrite publico do certificado presente'
);

if ($failed) {
    echo 'COURSES_RUNTIME_READINESS=FAIL'
        . PHP_EOL;

    exit(1);
}

echo 'COURSES_RUNTIME_READINESS=PASS'
    . PHP_EOL;