<?php

declare(strict_types=1);

use FacilDigital\Core\Contests\ContestModule;
use FacilDigital\Core\Entitlements\EntitlementRepository;
use FacilDigital\Core\Products\ProductMetadata;
use FacilDigital\Core\Questions\QuestionService;
use FacilDigital\Core\Simulations\SimulationService;

$existing = get_option('fd_m3_seed', []);
if (is_array($existing) && $existing !== []) {
    ob_start();
    require __DIR__ . '/cleanup-m3.php';
    ob_end_clean();
}

$term = wp_insert_term('M3 Concurso Teste', ContestModule::TAXONOMY, ['slug' => 'fd-m3-concurso-teste']);
if (is_wp_error($term)) {
    $found = get_term_by('slug', 'fd-m3-concurso-teste', ContestModule::TAXONOMY);
    if (!$found instanceof WP_Term) {
        throw new RuntimeException('m3_term_failed');
    }
    $termId = (int) $found->term_id;
} else {
    $termId = (int) $term['term_id'];
}

$product = new WC_Product_Simple();
$product->set_name('M3 Apostila Concurso Teste');
$product->set_slug('fd-m3-apostila-teste');
$product->set_status('publish');
$product->set_regular_price('14.50');
$product->set_price('14.50');
$product->set_virtual(true);
$product->set_downloadable(false);
$productId = $product->save();
update_post_meta($productId, '_fd_m3_seed', '1');
update_post_meta($productId, ProductMetadata::IS_APOSTILA, 'yes');
update_post_meta($productId, ProductMetadata::HAS_SIMULATIONS, 'yes');
update_post_meta($productId, ProductMetadata::POSITION_NAME, 'Cargo M3');
update_post_meta($productId, ProductMetadata::BOARD, 'Banca M3');
wp_set_object_terms($productId, [$termId], ContestModule::TAXONOMY, false);

$users = [];
foreach ([
    ['fd-m3-principal@example.invalid', 'Aluno M3 Principal'],
    ['fd-m3-sem-acesso@example.invalid', 'Aluno Sem Acesso'],
    ['fd-m3-ranking@example.invalid', 'Maria Oliveira'],
] as [$email, $display]) {
    $old = get_user_by('email', $email);
    if ($old instanceof WP_User) {
        wp_delete_user((int) $old->ID);
    }
    $id = wp_create_user(str_replace(['@', '.'], '-', $email), wp_generate_password(24, true, true), $email);
    if (is_wp_error($id)) {
        throw new RuntimeException('m3_user_failed');
    }
    wp_update_user(['ID' => $id, 'display_name' => $display, 'first_name' => strtok($display, ' ')]);
    $users[] = (int) $id;
}
[$userId, $unauthorizedUserId, $rankingUserId] = $users;

$orderIds = [];
$entitlementIds = [];
$entitlements = new EntitlementRepository();
foreach ([$userId, $rankingUserId] as $customerId) {
    $order = wc_create_order(['customer_id' => $customerId]);
    if (!$order instanceof WC_Order) {
        throw new RuntimeException('m3_order_failed');
    }
    $order->add_product(wc_get_product($productId), 1);
    $order->calculate_totals();
    $order->set_status('completed');
    $order->save();
    $orderIds[] = (int) $order->get_id();
    $entitlementIds[] = $entitlements->grant(
        $customerId,
        $productId,
        (int) $order->get_id(),
        null,
        'm3_test'
    );
}

$questionService = new QuestionService();
$questionIds = [];
$base = [
    'contest_term_id' => $termId,
    'position_name' => 'Cargo M3',
    'board' => 'Banca M3',
    'difficulty' => 'medium',
    'exam_year' => 2026,
    'status' => 'active',
];

$questionIds[] = $questionService->create(array_merge($base, [
    'question_type' => 'multiple_choice', 'subject' => 'Matemática', 'topic' => 'Aritmética',
    'statement' => 'M3 TEST 1 — Quanto é 2 + 2?', 'explanation' => 'A soma correta é quatro.',
    'options' => [
        ['option_key' => 'A', 'option_text' => '3', 'is_correct' => false],
        ['option_key' => 'B', 'option_text' => '4', 'is_correct' => true],
        ['option_key' => 'C', 'option_text' => '5', 'is_correct' => false],
        ['option_key' => 'D', 'option_text' => '6', 'is_correct' => false],
        ['option_key' => 'E', 'option_text' => '7', 'is_correct' => false],
    ],
]), 1);
$questionIds[] = $questionService->create(array_merge($base, [
    'question_type' => 'multiple_choice', 'subject' => 'Português', 'topic' => 'Gramática',
    'statement' => 'M3 TEST 2 — Selecione a alternativa correta.', 'explanation' => 'Alternativa A é a esperada no seed.',
    'options' => [
        ['option_key' => 'A', 'option_text' => 'Alternativa correta', 'is_correct' => true],
        ['option_key' => 'B', 'option_text' => 'Alternativa incorreta', 'is_correct' => false],
        ['option_key' => 'C', 'option_text' => 'Alternativa incorreta 2', 'is_correct' => false],
        ['option_key' => 'D', 'option_text' => 'Alternativa incorreta 3', 'is_correct' => false],
        ['option_key' => 'E', 'option_text' => 'Alternativa incorreta 4', 'is_correct' => false],
    ],
]), 1);
$questionIds[] = $questionService->create(array_merge($base, [
    'question_type' => 'multiple_choice', 'subject' => 'Conhecimentos Específicos', 'topic' => 'Operações',
    'statement' => 'M3 TEST 3 — Qual alternativa representa a resposta do teste?', 'explanation' => 'A resposta programada é C.',
    'options' => [
        ['option_key' => 'A', 'option_text' => 'Opção A', 'is_correct' => false],
        ['option_key' => 'B', 'option_text' => 'Opção B', 'is_correct' => false],
        ['option_key' => 'C', 'option_text' => 'Opção C', 'is_correct' => true],
        ['option_key' => 'D', 'option_text' => 'Opção D', 'is_correct' => false],
        ['option_key' => 'E', 'option_text' => 'Opção E', 'is_correct' => false],
    ],
]), 1);
$questionIds[] = $questionService->create(array_merge($base, [
    'question_type' => 'true_false', 'subject' => 'Segurança', 'topic' => 'Procedimentos',
    'statement' => 'M3 TEST 4 — O servidor deve validar o tempo do simulado.', 'explanation' => 'Correto: o cronômetro do navegador não é autoridade.',
    'options' => [
        ['option_key' => 'C', 'option_text' => 'Certo', 'is_correct' => true],
        ['option_key' => 'E', 'option_text' => 'Errado', 'is_correct' => false],
    ],
]), 1);
$questionIds[] = $questionService->create(array_merge($base, [
    'question_type' => 'true_false', 'subject' => 'Segurança', 'topic' => 'Autosave',
    'statement' => 'M3 TEST 5 — Respostas podem existir somente no JavaScript.', 'explanation' => 'Errado: o Core deve persistir as respostas.',
    'options' => [
        ['option_key' => 'C', 'option_text' => 'Certo', 'is_correct' => false],
        ['option_key' => 'E', 'option_text' => 'Errado', 'is_correct' => true],
    ],
]), 1);

$simulationService = new SimulationService();
$simulationId = $simulationService->create([
    'title' => 'M3 Simulado Concurso Teste',
    'slug' => 'fd-m3-simulado-teste',
    'description' => 'Simulado temporário usado pelo gate M3.',
    'contest_term_id' => $termId,
    'position_name' => 'Cargo M3',
    'duration_seconds' => 600,
    'attempt_limit' => 2,
    'minimum_score' => 60,
    'show_answer_key' => true,
    'comment_policy' => 'after_finish',
    'ranking_enabled' => true,
    'selection_mode' => 'manual',
    'question_ids' => $questionIds,
    'status' => 'published',
], 1);

$seed = [
    'product_id' => $productId,
    'term_id' => $termId,
    'user_id' => $userId,
    'unauthorized_user_id' => $unauthorizedUserId,
    'ranking_user_id' => $rankingUserId,
    'user_ids' => $users,
    'order_ids' => $orderIds,
    'entitlement_ids' => $entitlementIds,
    'question_ids' => $questionIds,
    'simulation_ids' => [$simulationId],
    'simulation_id' => $simulationId,
    'simulation_slug' => 'fd-m3-simulado-teste',
];
update_option('fd_m3_seed', $seed, false);

echo wp_json_encode(['status' => 'seeded'] + $seed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
