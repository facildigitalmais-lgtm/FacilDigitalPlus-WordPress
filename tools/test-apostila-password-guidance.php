<?php

declare(strict_types=1);

use FacilDigital\Core\Core\Database;
use FacilDigital\Core\Entitlements\EntitlementRepository;
use FacilDigital\Core\PDFs\PdfFileRepository;
use FacilDigital\Core\Students\AccountModule;

if (wp_get_environment_type() !== 'development') {
    throw new RuntimeException('Este teste so pode rodar em development.');
}

global $wpdb;

$originalUserId = get_current_user_id();
$userIds = [];
$productIds = [];
$entitlementIds = [];

$assert = static function (bool $condition, string $label): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }

    echo 'PASS: ' . $label . PHP_EOL;
};

$createFixture = static function (
    string $suffix,
    bool $passwordEnabled
) use (
    &$userIds,
    &$productIds,
    &$entitlementIds
): array {
    $userId = wp_insert_user([
        'user_login' => 'fd_pw_' . strtolower($suffix) . '_' . wp_generate_password(8, false, false),
        'user_pass' => wp_generate_password(24, true, true),
        'display_name' => 'FD Teste ' . $suffix,
        'role' => 'customer',
    ]);

    if (is_wp_error($userId)) {
        throw new RuntimeException(
            'Falha ao criar usuario fixture: ' . $userId->get_error_message()
        );
    }

    $userId = (int) $userId;
    $userIds[] = $userId;

    $product = new WC_Product_Simple();
    $product->set_name('FD TEST ' . $suffix);
    $product->set_status('private');
    $product->set_catalog_visibility('hidden');
    $product->set_regular_price('1.00');
    $productId = (int) $product->save();

    if ($productId <= 0) {
        throw new RuntimeException('Falha ao criar produto fixture.');
    }

    $productIds[] = $productId;

    update_post_meta($productId, '_fd_is_apostila', 'yes');
    update_post_meta($productId, '_fd_material_version', 'test-1');
    update_post_meta($productId, '_fd_download_limit', '5');
    update_post_meta(
        $productId,
        '_fd_pdf_password_enabled',
        $passwordEnabled ? 'yes' : 'no'
    );

    $entitlements = new EntitlementRepository();
    $entitlementId = $entitlements->grant(
        $userId,
        $productId,
        900000 + $productId,
        1,
        'development_test'
    );

    if ($entitlementId <= 0) {
        throw new RuntimeException('Falha ao criar entitlement fixture.');
    }

    $entitlementIds[] = $entitlementId;

    $files = new PdfFileRepository();
    $pdf = $files->ensurePending(
        [
            'id' => $entitlementId,
            'user_id' => $userId,
            'product_id' => $productId,
            'order_id' => 900000 + $productId,
        ],
        'test-1',
        sprintf(
            'generated/dev-test/%d-%d.pdf',
            $userId,
            $productId
        ),
        'FD-DEV-' . $productId,
        false,
        $passwordEnabled
    );

    $pdfId = (int) ($pdf['id'] ?? 0);

    if ($pdfId <= 0) {
        throw new RuntimeException('Falha ao criar pdf fixture.');
    }

    $files->markReady(
        $pdfId,
        123,
        str_repeat('a', 64)
    );

    return [
        'user_id' => $userId,
        'product_id' => $productId,
        'entitlement_id' => $entitlementId,
        'pdf_id' => $pdfId,
    ];
};

$renderForUser = static function (int $userId): string {
    wp_set_current_user($userId);

    ob_start();

    try {
        (new AccountModule())->renderApostilas();

        return (string) ob_get_clean();
    } catch (Throwable $exception) {
        ob_end_clean();
        throw $exception;
    }
};

try {
    $protected = $createFixture('PROTEGIDA', true);
    $plain = $createFixture('SEM-SENHA', false);

    $protectedHtml = $renderForUser(
        (int) $protected['user_id']
    );

    $assert(
        str_contains(
            $protectedHtml,
            'Senha para abrir suas apostilas'
        ),
        'protegida mostra aviso geral'
    );

    $assert(
        str_contains(
            $protectedHtml,
            'Senha do PDF:'
        ),
        'protegida mostra aviso no card'
    );

    $assert(
        str_contains(
            $protectedHtml,
            'CPF informado na compra, somente os 11 números.'
        ),
        'protegida informa formato da senha'
    );

    $assert(
        str_contains(
            $protectedHtml,
            'Baixar apostila'
        ),
        'protegida preserva botao de download'
    );

    $assert(
        str_contains(
            $protectedHtml,
            'FD TEST PROTEGIDA'
        ),
        'protegida renderiza produto correto'
   );

    $plainHtml = $renderForUser(
        (int) $plain['user_id']
    );

    $assert(
        !str_contains(
            $plainHtml,
            'Senha para abrir suas apostilas'
        ),
        'sem senha nao mostra aviso geral'
    );

    $assert(
        !str_contains(
            $plainHtml,
            'Senha do PDF:'
        ),
        'sem senha nao mostra aviso no card'
    );

    $assert(
        str_contains(
            $plainHtml,
            'Baixar apostila'
        ),
        'sem senha preserva botao de download'
    );

    $assert(
        str_contains(
            $plainHtml,
            'FD TEST SEM-SENHA'
        ),
        'sem senha renderiza produto correto'
    );

    echo 'APOSTILA_PASSWORD_GUIDANCE_FUNCTIONAL=PASS' . PHP_EOL;
} finally {
    wp_set_current_user($originalUserId);

    foreach ($entitlementIds as $entitlementId) {
        $wpdb->delete(
            Database::table('downloads'),
            ['entitlement_id' => $entitlementId],
            ['%d']
        );

        $wpdb->delete(
            Database::table('pdf_files'),
            ['entitlement_id' => $entitlementId],
            ['%d']
        );
        $wpdb->delete(
            Database::table('entitlements'),
            ['id' => $entitlementId],
            ['%d']
        );
    }

    foreach ($productIds as $productId) {
        wp_delete_post($productId, true);
    }

    if (!function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }

    foreach ($userIds as $userId) {
        wp_delete_user($userId);
    }

    echo 'FIXTURES_CLEANUP=OK' . PHP_EOL;
}
