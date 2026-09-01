<?php

declare(strict_types=1);

use FacilDigital\Core\Core\Database;
use FacilDigital\Core\Entitlements\EntitlementRepository;
use FacilDigital\Core\Entitlements\EntitlementService;
use FacilDigital\Core\WooCommerce\OrderEntitlementHandler;

if (!defined('ABSPATH')) {
    exit;
}

if (wp_get_environment_type() !== 'development') {
    throw new RuntimeException(
        'Teste M1 permitido somente em development.'
    );
}

$productIds = get_posts([
    'post_type' => 'product',
    'post_status' => 'publish',
    'posts_per_page' => 1,
    'fields' => 'ids',
    'meta_key' => '_fd_m1_seed',
    'meta_value' => '1',
    'no_found_rows' => true,
]);

if ($productIds === []) {
    throw new RuntimeException(
        'Produto M1 ausente.'
    );
}

$product = wc_get_product(
    (int) $productIds[0]
);

if (!$product instanceof WC_Product) {
    throw new RuntimeException(
        'Produto M1 inválido.'
    );
}

$email =
    'fd-m1-entitlement@invalid.local';

$existingUser =
    get_user_by('email', $email);

if ($existingUser instanceof WP_User) {
    $userId = (int) $existingUser->ID;
} else {
    $userId = wc_create_new_customer(
        $email,
        'fd_m1_entitlement',
        wp_generate_password(24, true, true)
    );

    if (is_wp_error($userId)) {
        throw new RuntimeException(
            $userId->get_error_message()
        );
    }

    $userId = (int) $userId;
}

update_user_meta(
    $userId,
    '_fd_m1_test_user',
    '1'
);

$repository =
    new EntitlementRepository();

$service =
    new EntitlementService(
        $repository
    );

$handler =
    new OrderEntitlementHandler(
        $service
    );

$orderIds = [];

try {
    $order1 = wc_create_order([
        'customer_id' => $userId,
    ]);

    if (is_wp_error($order1)) {
        throw new RuntimeException(
            $order1->get_error_message()
        );
    }

    $order1->add_product(
        $product,
        1
    );

    $order1->set_payment_method('bacs');
    $order1->set_created_via('fd_m1_test');
    $order1->update_meta_data(
        '_fd_m1_test_order',
        '1'
    );
    $order1->calculate_totals();
    $order1->save();

    $orderIds[] =
        (int) $order1->get_id();

    $order1->payment_complete(
        'FD-M1-TXN-1'
    );

    $fresh1 =
        wc_get_order(
            $order1->get_id()
        );

    if (!$fresh1 instanceof WC_Order) {
        throw new RuntimeException(
            'Pedido pago não pôde ser recarregado.'
        );
    }

    if (
        !$fresh1->is_paid()
        || $fresh1->get_date_paid() === null
    ) {
        throw new RuntimeException(
            'WooCommerce não marcou o pedido como pago.'
        );
    }

    if (
        !$service->userCanAccessProduct(
            $userId,
            (int) $product->get_id()
        )
    ) {
        throw new RuntimeException(
            'Pagamento não gerou entitlement ativo.'
        );
    }

    $handler->handlePaidOrder(
        (int) $fresh1->get_id()
    );

    $handler->handlePaidOrder(
        (int) $fresh1->get_id()
    );

    if (
        $repository->countForOrder(
            (int) $fresh1->get_id()
        ) !== 1
    ) {
        throw new RuntimeException(
            'Idempotência de entitlement falhou.'
        );
    }

    $fresh1->update_status(
        'refunded',
        'M1 automated refund test',
        false
    );

    if (
        $service->userCanAccessProduct(
            $userId,
            (int) $product->get_id()
        )
    ) {
        throw new RuntimeException(
            'Refund não revogou entitlement.'
        );
    }

    $order2 = wc_create_order([
        'customer_id' => $userId,
    ]);

    if (is_wp_error($order2)) {
        throw new RuntimeException(
            $order2->get_error_message()
        );
    }

    $order2->add_product(
        $product,
        1
    );

    $order2->set_payment_method('bacs');
    $order2->set_created_via('fd_m1_test');
    $order2->update_meta_data(
        '_fd_m1_test_order',
        '1'
    );
    $order2->calculate_totals();
    $order2->save();

    $orderIds[] =
        (int) $order2->get_id();

    $order2->payment_complete(
        'FD-M1-TXN-2'
    );

    if (
        !$service->userCanAccessProduct(
            $userId,
            (int) $product->get_id()
        )
    ) {
        throw new RuntimeException(
            'Nova compra paga não restaurou o acesso.'
        );
    }

    /*
     * Revogar o pedido antigo novamente não pode afetar
     * o entitlement do novo pedido.
     */
    $handler->handleFailedOrder(
        (int) $fresh1->get_id()
    );

    if (
        !$service->userCanAccessProduct(
            $userId,
            (int) $product->get_id()
        )
    ) {
        throw new RuntimeException(
            'Revogação de pedido antigo removeu compra nova.'
        );
    }

    echo wp_json_encode(
        [
            'status' => 'ok',
            'user_id' => $userId,
            'product_id' => (int) $product->get_id(),
            'order_1' => (int) $fresh1->get_id(),
            'order_2' => (int) $order2->get_id(),
            'active_access' => true,
        ],
        JSON_PRETTY_PRINT
    );

    echo PHP_EOL;
} finally {
    global $wpdb;

    foreach ($orderIds as $orderId) {
        $wpdb->delete(
            Database::table('entitlements'),
            [
                'order_id' => $orderId,
            ],
            [
                '%d',
            ]
        );

        $order =
            wc_get_order($orderId);

        if ($order instanceof WC_Order) {
            $order->delete(true);
        }
    }

    if (!function_exists('wp_delete_user')) {
        require_once ABSPATH
            . 'wp-admin/includes/user.php';
    }

    wp_delete_user($userId);
}
