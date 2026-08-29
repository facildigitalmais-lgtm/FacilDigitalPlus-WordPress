<?php

declare(strict_types=1);

use FacilDigital\Core\Core\Database;
use FacilDigital\Core\PDFs\PdfGenerationModule;
use FacilDigital\Core\PDFs\PrivateStorage;
use FacilDigital\Core\Products\ProductMetadata;

if (!defined('ABSPATH')) {
    exit(1);
}

require_once ABSPATH . 'wp-admin/includes/user.php';

$seed = get_option('fd_m2_seed', []);
if (!is_array($seed) || $seed === []) {
    echo "M2 cleanup: nada a remover.\n";
    return;
}

$productId = (int) ($seed['product_id'] ?? 0);
$userId = (int) ($seed['user_id'] ?? 0);
$otherUserId = (int) ($seed['other_user_id'] ?? 0);
$orderId = (int) ($seed['order_id'] ?? 0);
$entitlementId = (int) ($seed['entitlement_id'] ?? 0);

if (
    function_exists('as_unschedule_all_actions')
    && $entitlementId > 0
) {
    as_unschedule_all_actions(
        PdfGenerationModule::ACTION,
        [$entitlementId],
        PdfGenerationModule::GROUP
    );
}

$storage = new PrivateStorage();
$pdfTable = Database::table('pdf_files');
$downloadTable = Database::table('downloads');
$entitlementTable = Database::table('entitlements');

global $wpdb;

if ($productId > 0) {
    $keys = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT storage_key FROM {$pdfTable} WHERE product_id = %d",
            $productId
        )
    );

    foreach ($keys as $key) {
        if (is_string($key) && $key !== '') {
            $storage->delete($key);
        }
    }

    $masterKey = ProductMetadata::get(
        $productId,
        ProductMetadata::MASTER_PDF_KEY
    );
    if ($masterKey !== '') {
        $storage->delete($masterKey);
    }
}

if ($productId > 0) {
    $wpdb->delete($downloadTable, ['product_id' => $productId]);
    $wpdb->delete($pdfTable, ['product_id' => $productId]);
    $wpdb->delete($entitlementTable, ['product_id' => $productId]);
}

if ($orderId > 0) {
    $order = wc_get_order($orderId);
    if ($order instanceof WC_Order) {
        $order->delete(true);
    }
}

if ($productId > 0) {
    wp_delete_post($productId, true);
}

if ($userId > 0) {
    wp_delete_user($userId);
}

if ($otherUserId > 0) {
    wp_delete_user($otherUserId);
}

delete_option('fd_m2_seed');

echo "M2 cleanup concluido.\n";
