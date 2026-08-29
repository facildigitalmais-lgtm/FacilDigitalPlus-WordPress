<?php

declare(strict_types=1);

use FacilDigital\Core\Questions\QuestionRepository;
use FacilDigital\Core\Questions\QuestionService;

$service = new QuestionService();
$repo = new QuestionRepository();
$created = [];
$quantity = 120;

try {
    $startInsert = microtime(true);
    for ($i = 1; $i <= $quantity; $i++) {
        $created[] = $service->create([
            'question_type' => 'multiple_choice',
            'statement' => 'Questão de carga M4 ' . $i,
            'explanation' => 'Fixture temporária de QA.',
            'board' => 'Banca Carga M4',
            'position_name' => 'Cargo Carga M4',
            'subject' => 'Carga M4',
            'topic' => $i % 2 === 0 ? 'Par' : 'Ímpar',
            'difficulty' => 'medium',
            'exam_year' => 2026,
            'status' => 'active',
            'options' => [
                ['option_key' => 'A', 'option_text' => 'Alternativa A', 'is_correct' => true],
                ['option_key' => 'B', 'option_text' => 'Alternativa B', 'is_correct' => false],
                ['option_key' => 'C', 'option_text' => 'Alternativa C', 'is_correct' => false],
                ['option_key' => 'D', 'option_text' => 'Alternativa D', 'is_correct' => false],
                ['option_key' => 'E', 'option_text' => 'Alternativa E', 'is_correct' => false],
            ],
        ], 1);
    }
    $insertMs = round((microtime(true) - $startInsert) * 1000, 2);

    $start = microtime(true);
    $page1 = $repo->list(['subject' => 'Carga M4'], 50, 0);
    $page2 = $repo->list(['subject' => 'Carga M4'], 50, 50);
    $count = $repo->count(['subject' => 'Carga M4']);
    $queryMs = round((microtime(true) - $start) * 1000, 2);

    if ($count !== $quantity || count($page1) !== 50 || count($page2) !== 50) {
        throw new RuntimeException('m4_load_pagination_failed');
    }
    if ($queryMs > 20000) {
        throw new RuntimeException('m4_load_query_too_slow');
    }

    echo wp_json_encode([
        'status' => 'ok',
        'questions' => $quantity,
        'insert_ms' => $insertMs,
        'query_ms' => $queryMs,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} finally {
    foreach ($created as $id) {
        try {
            $repo->delete((int) $id);
        } catch (Throwable) {
        }
    }
}
