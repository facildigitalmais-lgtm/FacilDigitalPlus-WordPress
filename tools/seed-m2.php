<?php

declare(strict_types=1);

use FacilDigital\Core\Entitlements\EntitlementRepository;
use FacilDigital\Core\Entitlements\EntitlementService;
use FacilDigital\Core\PDFs\MasterPdfService;
use FacilDigital\Core\PDFs\PdfGenerationService;
use FacilDigital\Core\PDFs\PrivateStorage;
use FacilDigital\Core\Products\ProductMetadata;
use FacilDigital\Core\WooCommerce\CheckoutModule;

if (!defined('ABSPATH')) {
    exit(1);
}

require_once ABSPATH . 'wp-admin/includes/user.php';

$previous = get_option('fd_m2_seed', []);
if (is_array($previous) && $previous !== []) {
    require '/workspace/tools/cleanup-m2.php';
}

$product = new WC_Product_Simple();
$product->set_name('M2 Apostila PDF Protegida');
$product->set_slug('fd-m2-apostila-pdf-protegida');
$product->set_regular_price('14.50');
$product->set_status('publish');
$product->set_virtual(true);
$product->set_downloadable(false);
$productId = $product->save();

$metadata = [
    ProductMetadata::IS_APOSTILA => 'yes',
    ProductMetadata::POSITION_NAME => 'Teste M2',
    ProductMetadata::BOARD => 'Cesgranrio',
    ProductMetadata::EXAM_YEAR => '2026',
    ProductMetadata::PAGE_COUNT => '2',
    ProductMetadata::MATERIAL_VERSION => 'm2-1',
    ProductMetadata::HAS_SIMULATIONS => 'yes',
    ProductMetadata::DOWNLOAD_LIMIT => '2',
    ProductMetadata::GENERATE_PERSONALIZED_PDF => 'yes',
    ProductMetadata::WATERMARK_ENABLED => 'yes',
    ProductMetadata::PDF_PASSWORD_ENABLED => 'yes',
];

foreach ($metadata as $key => $value) {
    update_post_meta($productId, $key, $value);
}

$userId = wp_create_user(
    'fd_m2_student',
    wp_generate_password(24, true, true),
    'fd-m2-student@example.test'
);

if (is_wp_error($userId)) {
    $existing = get_user_by('login', 'fd_m2_student');
    $userId = $existing ? (int) $existing->ID : 0;
}

$otherUserId = wp_create_user(
    'fd_m2_other',
    wp_generate_password(24, true, true),
    'fd-m2-other@example.test'
);

if (is_wp_error($otherUserId)) {
    $existingOther = get_user_by('login', 'fd_m2_other');
    $otherUserId = $existingOther ? (int) $existingOther->ID : 0;
}

if ($userId <= 0 || $otherUserId <= 0) {
    throw new RuntimeException('M2 user seed failed.');
}

wp_update_user([
    'ID' => $userId,
    'display_name' => 'Aluno M2',
]);

$storage = new PrivateStorage();
$storage->ensureReady();
$sourceKey = 'temp/m2-source-' . bin2hex(random_bytes(6)) . '.pdf';
$sourcePath = $storage->path($sourceKey);

$sourcePdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false, false);
$sourcePdf->setPrintHeader(false);
$sourcePdf->setPrintFooter(false);
$sourcePdf->AddPage();
$sourcePdf->SetFont('helvetica', 'B', 20);
$sourcePdf->Write(10, 'Fácil Digital+ - Master M2');
$sourcePdf->Ln(15);
$sourcePdf->SetFont('helvetica', '', 11);
$sourcePdf->Write(7, 'Página 1 de validação do pipeline de apostilas.');
$sourcePdf->AddPage();
$sourcePdf->SetFont('helvetica', '', 11);
$sourcePdf->Write(7, 'Página 2 de validação do pipeline de apostilas.');
$sourcePdf->Output($sourcePath, 'F');

$masterService = new MasterPdfService();
$masterKey = $masterService->importFromPath(
    $productId,
    $sourcePath
);
@unlink($sourcePath);

$order = wc_create_order([
    'customer_id' => $userId,
]);

if (!$order instanceof WC_Order) {
    throw new RuntimeException('M2 order seed failed.');
}

$order->add_product(wc_get_product($productId), 1);
$order->set_payment_method('bacs');
$order->update_meta_data(
    CheckoutModule::ORDER_CPF_META,
    '52998224725'
);
$order->calculate_totals();
$order->save();
$order->payment_complete('fd-m2-test');
$order = wc_get_order($order->get_id());

if (!$order instanceof WC_Order) {
    throw new RuntimeException('M2 order reload failed.');
}

$service = new EntitlementService();
$service->grantForPaidOrder($order);
$repository = new EntitlementRepository();
$active = $repository->activeForUser($userId);
$entitlement = null;

foreach ($active as $row) {
    if (
        (int) ($row['product_id'] ?? 0) === $productId
        && (int) ($row['order_id'] ?? 0) === $order->get_id()
    ) {
        $entitlement = $row;
        break;
    }
}

if (!is_array($entitlement)) {
    throw new RuntimeException('M2 entitlement seed failed.');
}

$generator = new PdfGenerationService();
$pdf = $generator->generateForEntitlement(
    (int) $entitlement['id']
);

$seed = [
    'product_id' => $productId,
    'user_id' => $userId,
    'other_user_id' => $otherUserId,
    'order_id' => (int) $order->get_id(),
    'entitlement_id' => (int) $entitlement['id'],
    'pdf_id' => (int) ($pdf['id'] ?? 0),
    'master_key' => $masterKey,
];

update_option('fd_m2_seed', $seed, false);

echo wp_json_encode([
    'status' => 'seeded',
    'product_id' => $seed['product_id'],
    'user_id' => $seed['user_id'],
    'other_user_id' => $seed['other_user_id'],
    'order_id' => $seed['order_id'],
    'entitlement_id' => $seed['entitlement_id'],
    'pdf_id' => $seed['pdf_id'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
