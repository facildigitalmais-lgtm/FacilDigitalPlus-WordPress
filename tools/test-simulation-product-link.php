<?php

declare(strict_types=1);

use FacilDigital\Core\Contests\ContestModule;
use FacilDigital\Core\Core\Database;
use FacilDigital\Core\Products\ProductMetadata;
use FacilDigital\Core\Simulations\SimulationAccessService;
use FacilDigital\Core\Simulations\SimulationRepository;
use FacilDigital\Core\Simulations\SimulationService;

if (!defined('ABSPATH')) {
    exit(1);
}

if (wp_get_environment_type() !== 'development') {
    throw new RuntimeException('TESTE_RECUSADO_FORA_DE_DEVELOPMENT');
}

if (!function_exists('wc_get_product')) {
    throw new RuntimeException('WOOCOMMERCE_NAO_DISPONIVEL');
}

global $wpdb;

echo "==================================================" . PHP_EOL;
echo "SIMULATION PRODUCT LINK - TESTE FUNCIONAL" . PHP_EOL;
echo "==================================================" . PHP_EOL;

$simulationId = 0;
$questionId = 0;
$productA = 0;
$productB = 0;
$userA = 0;
$userB = 0;
$termId = 0;

$simulations = new SimulationRepository();
$service = new SimulationService();
$access = new SimulationAccessService();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }

    echo "PASS  {$message}" . PHP_EOL;
};

try {
    /*
     * Concurso temporario.
     */
    if (!taxonomy_exists(ContestModule::TAXONOMY)) {
        throw new RuntimeException('TAXONOMIA_CONCURSO_NAO_REGISTRADA');
    }

    $term = wp_insert_term(
        'FD TEST SIMULATION LINK ' . wp_generate_uuid4(),
        ContestModule::TAXONOMY
    );

    if (is_wp_error($term)) {
        throw new RuntimeException(
            'FALHA_CONCURSO: ' . $term->get_error_message()
        );
    }

    $termId = (int) $term['term_id'];

    /*
     * Produtos A e B.
     *
     * Ambos recebem exatamente o mesmo concurso/cargo.
     * Assim, sem vínculo explícito, ambos satisfazem
     * a regra legada.
     */
    $createProduct = static function (
        string $name,
        int $contestTermId
    ): int {
        $product = new WC_Product_Simple();

        $product->set_name($name);
        $product->set_status('publish');
        $product->set_regular_price('10.00');
        $product->set_price('10.00');
        $product->set_virtual(true);

        $id = (int) $product->save();

        if ($id <= 0) {
            throw new RuntimeException('PRODUCT_CREATE_FAILED');
        }

        update_post_meta(
            $id,
            ProductMetadata::IS_APOSTILA,
            'yes'
        );

        update_post_meta(
            $id,
            ProductMetadata::HAS_SIMULATIONS,
            'yes'
        );

        update_post_meta(
            $id,
            ProductMetadata::POSITION_NAME,
            'FD TEST CARGO'
        );

        $assigned = wp_set_object_terms(
            $id,
            [$contestTermId],
            ContestModule::TAXONOMY,
            false
        );

        if (is_wp_error($assigned)) {
            throw new RuntimeException(
                'PRODUCT_CONTEST_FAILED: '
                . $assigned->get_error_message()
            );
        }

        return $id;
    };

    $productA = $createProduct(
        'FD TEST APOSTILA A',
        $termId
    );

    $productB = $createProduct(
        'FD TEST APOSTILA B',
        $termId
    );

    $assert(
        ProductMetadata::isApostila($productA),
        'produto A e apostila'
    );

    $assert(
        ProductMetadata::isApostila($productB),
        'produto B e apostila'
    );

    /*
     * Usuarios comuns.
     */
    $suffix = strtolower(wp_generate_password(8, false, false));

    $userA = wp_create_user(
        'fd_test_a_' . $suffix,
        wp_generate_password(24, true, true),
        'fd_test_a_' . $suffix . '@example.test'
    );

    if (is_wp_error($userA)) {
        throw new RuntimeException(
            'USER_A_FAILED: ' . $userA->get_error_message()
        );
    }

    $userA = (int) $userA;

    $userB = wp_create_user(
        'fd_test_b_' . $suffix,
        wp_generate_password(24, true, true),
        'fd_test_b_' . $suffix . '@example.test'
    );

    if (is_wp_error($userB)) {
        throw new RuntimeException(
            'USER_B_FAILED: ' . $userB->get_error_message()
        );
    }

    $userB = (int) $userB;

    /*
     * Entitlements controlados.
     *
     * Nao ha compra, checkout ou gateway.
     * Sao fixtures exclusivas do banco DEV.
     */
    $entitlements = Database::table('entitlements');
    $now = current_time('mysql', true);

    foreach ([
        [$userA, $productA, 990001],
        [$userB, $productB, 990002],
    ] as [$userId, $productId, $orderId]) {
        $ok = $wpdb->insert(
            $entitlements,
            [
                'user_id' => $userId,
                'product_id' => $productId,
                'order_id' => $orderId,
                'order_item_id' => null,
                'status' => 'active',
                'source' => 'test',
                'granted_at' => $now,
                'revoked_at' => null,
                'expires_at' => null,
                'revocation_reason' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                '%d',
                '%d',
                '%d',
                null,
                '%s',
                '%s',
                '%s',
                null,
                null,
                null,
                '%s',
                '%s',
            ]
        );

        if ($ok === false) {
            throw new RuntimeException(
                'ENTITLEMENT_INSERT_FAILED: '
                . $wpdb->last_error
            );
        }
    }

    /*
     * Questao ativa minima.
     */
    $questions = Database::table('questions');

    $ok = $wpdb->insert(
        $questions,
        [
            'contest_term_id' => $termId,
            'question_type' => 'multiple_choice',
            'statement' => 'Questao temporaria do teste de vinculo.',
            'explanation' => null,
            'board' => 'FD TEST',
            'position_name' => 'FD TEST CARGO',
            'subject' => 'FD TEST',
            'topic' => 'FD TEST',
            'difficulty' => 'medium',
            'exam_year' => 2026,
            'status' => 'active',
            'image_attachment_id' => null,
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );

    if ($ok === false) {
        throw new RuntimeException(
            'QUESTION_INSERT_FAILED: '
            . $wpdb->last_error
        );
    }

    $questionId = (int) $wpdb->insert_id;

    /*
     * Simulado inicialmente SEM produto vinculado.
     */
    $payload = [
        'title' => 'FD TEST SIMULATION LINK',
        'slug' => 'fd-test-simulation-link-' . $suffix,
        'description' => 'Fixture DEV',
        'contest_term_id' => $termId,
        'position_name' => 'FD TEST CARGO',
        'duration_seconds' => 600,
        'attempt_limit' => 3,
        'minimum_score' => 60,
        'show_answer_key' => true,
        'comment_policy' => 'after_finish',
        'ranking_enabled' => false,
        'selection_mode' => 'manual',
        'question_ids' => [$questionId],
        'product_ids' => [],
        'status' => 'published',
    ];

    $simulationId = $service->create(
        $payload,
        0
    );

    $assert(
        $simulationId > 0,
        'simulado criado'
    );

    $row = $simulations->findById($simulationId);

    $assert(
        is_array($row)
        && ($row['product_ids'] ?? []) === [],
        'simulado inicia sem vinculo explicito'
    );

    /*
     * CENARIO A:
     * fallback legado.
     *
     * Os dois produtos possuem mesmo concurso/cargo.
     */
    $assert(
        $access->canAccess($userA, $simulationId),
        'legado permite usuario A'
    );

    $assert(
        $access->canAccess($userB, $simulationId),
        'legado permite usuario B'
    );

    /*
     * Vincula SOMENTE produto A.
     */
    $payload['product_ids'] = [$productA];

    $service->update(
        $simulationId,
        $payload,
        0
    );

    $row = $simulations->findById($simulationId);

    $assert(
        is_array($row)
        && ($row['product_ids'] ?? []) === [$productA],
        'repository persiste somente produto A'
    );

    /*
     * CENARIO B:
     * entitlement do produto explicitamente vinculado.
     */
    $assert(
        $access->canAccess($userA, $simulationId),
        'vinculo explicito permite usuario A'
    );

    /*
     * CENARIO C:
     * B teria acesso pelo legado, pois concurso/cargo
     * sao iguais. Com vínculo explicito deve ser negado.
     */
    $assert(
        !$access->canAccess($userB, $simulationId),
        'vinculo explicito bloqueia produto B'
    );

    /*
     * HAS_SIMULATIONS continua sendo chave geral.
     */
    update_post_meta(
        $productA,
        ProductMetadata::HAS_SIMULATIONS,
        'no'
    );

    $assert(
        !$access->canAccess($userA, $simulationId),
        'HAS_SIMULATIONS=no bloqueia produto vinculado'
    );

    update_post_meta(
        $productA,
        ProductMetadata::HAS_SIMULATIONS,
        'yes'
    );

    $assert(
        $access->canAccess($userA, $simulationId),
        'HAS_SIMULATIONS=yes restaura acesso'
    );

    /*
     * CENARIO D:
     * remover todos os vínculos restaura legado.
     */
    $payload['product_ids'] = [];

    $service->update(
        $simulationId,
        $payload,
        0
    );

    $row = $simulations->findById($simulationId);

    $assert(
        is_array($row)
        && ($row['product_ids'] ?? []) === [],
        'vinculos removidos'
    );

    $assert(
        $access->canAccess($userB, $simulationId),
        'fallback legado restaurado'
    );

    echo PHP_EOL;
    echo "SIMULATION_PRODUCT_LINK_FUNCTIONAL=PASS" . PHP_EOL;
} finally {
    /*
     * Cleanup de todas as fixtures DEV.
     */
    if ($simulationId > 0) {
        try {
            $service->delete($simulationId);
        } catch (Throwable $e) {
            unset($e);

            $wpdb->delete(
                Database::table('simulation_products'),
                ['simulation_id' => $simulationId],
                ['%d']
            );

            $wpdb->delete(
                Database::table('simulation_questions'),
                ['simulation_id' => $simulationId],
                ['%d']
            );

            $wpdb->delete(
                Database::table('simulations'),
                ['id' => $simulationId],
                ['%d']
            );
        }
    }

    if ($questionId > 0) {
        $wpdb->delete(
            Database::table('question_options'),
            ['question_id' => $questionId],
            ['%d']
        );

        $wpdb->delete(
            Database::table('questions'),
            ['id' => $questionId],
            ['%d']
        );
    }

    foreach ([
        [$userA, $productA],
        [$userB, $productB],
    ] as [$userId, $productId]) {
        if ($userId > 0) {
            $wpdb->delete(
                Database::table('entitlements'),
                ['user_id' => $userId],
                ['%d']
            );
        }

        if ($productId > 0) {
            wp_delete_post(
                $productId,
                true
            );
        }
    }

    if ($userA > 0 || $userB > 0) {
        require_once ABSPATH . 'wp-admin/includes/user.php';

        if ($userA > 0) {
            wp_delete_user($userA);
        }

        if ($userB > 0) {
            wp_delete_user($userB);
        }
    }

    if ($termId > 0) {
        wp_delete_term(
            $termId,
            ContestModule::TAXONOMY
        );
    }

    echo "FIXTURES_CLEANUP=OK" . PHP_EOL;
}
