<?php

declare(strict_types=1);

use FacilDigital\Core\Core\Database;
use FacilDigital\Core\Core\Installer;

if (wp_get_environment_type() !== 'development') {
    throw new RuntimeException(
        'Este teste so pode rodar em development.'
    );
}

global $wpdb;

$assert = static function (
    bool $condition,
    string $label
): void {
    if (!$condition) {
        fwrite(
            STDERR,
            'FAIL: ' . $label . PHP_EOL
        );
        exit(1);
    }

    echo 'PASS: ' . $label . PHP_EOL;
};

$expectedTables = [
    'questions' => 'fd_questions',
    'question_options' => 'fd_question_options',
    'simulations' => 'fd_simulations',
    'simulation_questions' => 'fd_simulation_questions',
    'simulation_products' => 'fd_simulation_products',
    'attempts' => 'fd_attempts',
    'attempt_answers' => 'fd_attempt_answers',
    'entitlements' => 'fd_entitlements',
    'pdf_files' => 'fd_pdf_files',
    'downloads' => 'fd_downloads',
    'courses' => 'fd_courses',
    'course_modules' => 'fd_course_modules',
    'course_lessons' => 'fd_course_lessons',
    'lesson_resources' => 'fd_lesson_resources',
    'course_enrollments' => 'fd_course_enrollments',
    'lesson_progress' => 'fd_lesson_progress',
    'certificates' => 'fd_certificates',
];

$legacyKeys = [
    'questions',
    'question_options',
    'simulations',
    'simulation_questions',
    'simulation_products',
    'attempts',
    'attempt_answers',
    'entitlements',
    'pdf_files',
    'downloads',
];

$lmsKeys = [
    'courses',
    'course_modules',
    'course_lessons',
    'lesson_resources',
    'course_enrollments',
    'lesson_progress',
    'certificates',
];

$assert(
    Database::SCHEMA_VERSION === '1.2.0',
    'schema declarado em 1.2.0'
);

$assert(
    Database::installedVersion() === '1.2.0',
    'schema instalado em 1.2.0'
);

$tables = Database::tables();

$assert(
    array_keys($tables)
    === array_keys($expectedTables),
    'mapa possui as 10 tabelas legadas e 7 LMS'
);

$assert(
    count($tables) === 17,
    'mapa do Core possui exatamente 17 tabelas'
);

$assert(
    Database::missingTables() === [],
    'nenhuma tabela do Core esta ausente'
);

foreach ($expectedTables as $key => $suffix) {
    $expectedPhysicalName =
        $wpdb->prefix . $suffix;

    $assert(
        ($tables[$key] ?? '')
        === $expectedPhysicalName,
        'nome fisico correto para ' . $key
    );

    $found = $wpdb->get_var(
        $wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $wpdb->esc_like(
                $expectedPhysicalName
            )
        )
    );

    $assert(
        $found === $expectedPhysicalName,
        'tabela existente: ' . $key
    );
}

$physicalTables = $wpdb->get_col(
    $wpdb->prepare(
        'SHOW TABLES LIKE %s',
        $wpdb->esc_like(
            $wpdb->prefix . 'fd_'
        ) . '%'
    )
);

sort($physicalTables);

$expectedPhysicalTables = [];

foreach ($expectedTables as $suffix) {
    $expectedPhysicalTables[] =
        $wpdb->prefix . $suffix;
}

sort($expectedPhysicalTables);

$assert(
    $physicalTables === $expectedPhysicalTables,
    'banco DEV possui exatamente as 17 tabelas fd_ esperadas'
);

foreach ($legacyKeys as $key) {
    $assert(
        isset($tables[$key]),
        'tabela legada preservada no mapa: ' . $key
    );
}

foreach ($lmsKeys as $key) {
    $assert(
        isset($tables[$key]),
        'tabela LMS registrada no mapa: ' . $key
    );
}

$expectedColumns = [
    'courses' => [
        'id',
        'product_id',
        'title',
        'slug',
        'short_description',
        'description',
        'workload_minutes',
        'completion_threshold',
        'navigation_mode',
        'certificate_enabled',
        'status',
        'created_by',
        'published_at',
        'created_at',
        'updated_at',
    ],
    'course_modules' => [
        'id',
        'course_id',
        'title',
        'description',
        'sort_order',
        'status',
        'created_at',
        'updated_at',
    ],
    'course_lessons' => [
        'id',
        'course_id',
        'module_id',
        'title',
        'slug',
        'lesson_type',
        'content',
        'transcript',
        'youtube_video_id',
        'duration_seconds',
        'is_required',
        'sort_order',
        'status',
        'created_at',
        'updated_at',
    ],
    'lesson_resources' => [
        'id',
        'course_id',
        'lesson_id',
        'title',
        'storage_key',
        'original_filename',
        'mime_type',
        'file_size',
        'sha256',
        'sort_order',
        'status',
        'created_at',
        'updated_at',
    ],
    'course_enrollments' => [
        'id',
        'user_id',
        'course_id',
        'product_id',
        'order_id',
        'order_item_id',
        'status',
        'source',
        'enrolled_at',
        'completed_at',
        'revoked_at',
        'expires_at',
        'revocation_reason',
        'created_at',
        'updated_at',
    ],
    'lesson_progress' => [
        'id',
        'enrollment_id',
        'user_id',
        'course_id',
        'lesson_id',
        'status',
        'watched_seconds',
        'last_position_seconds',
        'duration_seconds',
        'completion_percent',
        'started_at',
        'last_activity_at',
        'completed_at',
        'created_at',
        'updated_at',
    ],
    'certificates' => [
        'id',
        'enrollment_id',
        'user_id',
        'course_id',
        'verification_code',
        'student_name_snapshot',
        'course_title_snapshot',
        'workload_minutes_snapshot',
        'completed_at',
        'issued_at',
        'storage_key',
        'file_size',
        'sha256',
        'status',
        'generation_attempts',
        'error_code',
        'created_at',
        'updated_at',
    ],
];

$columnSnapshot = [];

foreach ($expectedColumns as $key => $columns) {
    $rows = $wpdb->get_results(
        'SHOW COLUMNS FROM '
        . Database::table($key),
        ARRAY_A
    );

    $actualColumns =
        array_column(
            $rows,
            'Field'
        );

    $assert(
        $actualColumns === $columns,
        'colunas corretas para ' . $key
    );

    $columnSnapshot[$key] =
        $actualColumns;
}

$expectedUniqueIndexes = [
    'courses' => [
        'product_id',
        'slug',
    ],
    'course_lessons' => [
        'course_slug',
    ],
    'course_enrollments' => [
        'user_course_order',
    ],
    'lesson_progress' => [
        'enrollment_lesson',
    ],
    'certificates' => [
        'enrollment_id',
        'verification_code',
    ],
];

foreach (
    $expectedUniqueIndexes
    as $key => $indexNames
) {
    $indexes = $wpdb->get_results(
        'SHOW INDEX FROM '
        . Database::table($key),
        ARRAY_A
    );

    $uniqueIndexes = [];

    foreach ($indexes as $index) {
        if (
            (int) ($index['Non_unique'] ?? 1)
            === 0
        ) {
            $uniqueIndexes[] =
                (string) (
                    $index['Key_name']
                    ?? ''
                );
        }
    }

    foreach ($indexNames as $indexName) {
        $assert(
            in_array(
                $indexName,
                $uniqueIndexes,
                true
            ),
            'indice unico '
            . $indexName
            . ' em '
            . $key
        );
    }
}

Installer::installSchema();

$assert(
    Database::missingTables() === [],
    'segunda execucao preserva todas as tabelas'
);

foreach ($expectedColumns as $key => $columns) {
    $rows = $wpdb->get_results(
        'SHOW COLUMNS FROM '
        . Database::table($key),
        ARRAY_A
    );

    $actualColumns =
        array_column(
            $rows,
            'Field'
        );

    $assert(
        $actualColumns
        === $columnSnapshot[$key],
        'segunda execucao preserva schema de '
        . $key
    );
}

echo 'COURSES_SCHEMA_IDEMPOTENT=PASS'
    . PHP_EOL;

echo 'COURSES_SCHEMA_FOUNDATION=PASS'
    . PHP_EOL;

echo 'SCHEMA_TEST_NO_FIXTURES=OK'
    . PHP_EOL;