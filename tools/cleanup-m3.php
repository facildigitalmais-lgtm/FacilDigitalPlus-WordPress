<?php

declare(strict_types=1);

use FacilDigital\Core\Core\Database;

$seed = get_option('fd_m3_seed', []);
if (!is_array($seed)) {
    $seed = [];
}

global $wpdb;
$attemptAnswers = Database::table('attempt_answers');
$attempts = Database::table('attempts');
$simulationQuestions = Database::table('simulation_questions');
$simulations = Database::table('simulations');
$questionOptions = Database::table('question_options');
$questions = Database::table('questions');
$entitlements = Database::table('entitlements');

$simulationIds = array_filter(array_map('intval', (array) ($seed['simulation_ids'] ?? [])));
$questionIds = array_filter(array_map('intval', (array) ($seed['question_ids'] ?? [])));

foreach ($simulationIds as $simulationId) {
    $attemptIds = $wpdb->get_col(
        $wpdb->prepare("SELECT id FROM {$attempts} WHERE simulation_id = %d", $simulationId)
    );
    foreach (array_map('intval', is_array($attemptIds) ? $attemptIds : []) as $attemptId) {
        $wpdb->delete($attemptAnswers, ['attempt_id' => $attemptId], ['%d']);
    }
    $wpdb->delete($attempts, ['simulation_id' => $simulationId], ['%d']);
    $wpdb->delete($simulationQuestions, ['simulation_id' => $simulationId], ['%d']);
    $wpdb->delete($simulations, ['id' => $simulationId], ['%d']);
}

foreach ($questionIds as $questionId) {
    $wpdb->delete($questionOptions, ['question_id' => $questionId], ['%d']);
    $wpdb->delete($questions, ['id' => $questionId], ['%d']);
}

foreach (array_filter(array_map('intval', (array) ($seed['entitlement_ids'] ?? []))) as $entitlementId) {
    $wpdb->delete($entitlements, ['id' => $entitlementId], ['%d']);
}

foreach (array_filter(array_map('intval', (array) ($seed['order_ids'] ?? []))) as $orderId) {
    $order = wc_get_order($orderId);
    if ($order instanceof WC_Order) {
        $order->delete(true);
    }
}

$productId = (int) ($seed['product_id'] ?? 0);
if ($productId > 0) {
    wp_delete_post($productId, true);
}

foreach (array_filter(array_map('intval', (array) ($seed['user_ids'] ?? []))) as $userId) {
    if (get_userdata($userId) instanceof WP_User) {
        wp_delete_user($userId);
    }
}

$termId = (int) ($seed['term_id'] ?? 0);
if ($termId > 0 && term_exists($termId, 'fd_concurso')) {
    wp_delete_term($termId, 'fd_concurso');
}

delete_option('fd_m3_seed');

echo "M3 cleanup concluido.\n";
