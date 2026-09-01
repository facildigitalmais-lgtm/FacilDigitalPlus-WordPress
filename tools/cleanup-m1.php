<?php

declare(strict_types=1);

use FacilDigital\Core\Contests\ContestModule;
use FacilDigital\Core\Core\Database;

if (!defined('ABSPATH')) {
    exit;
}

if (wp_get_environment_type() !== 'development') {
    throw new RuntimeException(
        'M1 cleanup permitido somente em development.'
    );
}

$productIds = get_posts([
    'post_type' => 'product',
    'post_status' => 'any',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'meta_key' => '_fd_m1_seed',
    'meta_value' => '1',
    'no_found_rows' => true,
]);

foreach ($productIds as $productId) {
    wp_delete_post(
        (int) $productId,
        true
    );
}

global $wpdb;

$entitlements =
    Database::table('entitlements');

$orders = wc_get_orders([
    'limit' => -1,
    'type' => 'shop_order',
    'status' => array_keys(
        wc_get_order_statuses()
    ),
    'meta_key' => '_fd_m1_test_order',
    'meta_value' => '1',
    'return' => 'objects',
]);

foreach ($orders as $order) {
    if (!$order instanceof WC_Order) {
        continue;
    }

    $wpdb->delete(
        $entitlements,
        [
            'order_id' =>
                (int) $order->get_id(),
        ],
        [
            '%d',
        ]
    );

    $order->delete(true);
}

$users = get_users([
    'meta_key' => '_fd_m1_test_user',
    'meta_value' => '1',
    'fields' => 'ids',
]);

if (!function_exists('wp_delete_user')) {
    require_once ABSPATH
        . 'wp-admin/includes/user.php';
}

foreach ($users as $userId) {
    wp_delete_user(
        (int) $userId
    );
}

foreach (
    [
        'fd-m1-transpetro-2026',
        'fd-m1-correios-2026',
    ]
    as $slug
) {
    $term = get_term_by(
        'slug',
        $slug,
        ContestModule::TAXONOMY
    );

    if (!$term instanceof WP_Term) {
        continue;
    }

    wp_delete_term(
        (int) $term->term_id,
        ContestModule::TAXONOMY
    );
}

echo "M1 cleanup concluido." . PHP_EOL;
