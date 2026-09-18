<?php

declare(strict_types=1);

use FacilDigital\Core\Core\Database;
use FacilDigital\Core\Emails\ApostilaEmailModule;
use FacilDigital\Core\Emails\ApostilaReadyEmail;
use FacilDigital\Core\Entitlements\EntitlementRepository;
use FacilDigital\Core\PDFs\PdfFileRepository;

if (wp_get_environment_type() !== 'development') {
    throw new RuntimeException('Este teste so pode rodar em development.');
}

global $wpdb;

$userIds = [];
$productIds = [];
$orderIds = [];
$entitlementIds = [];
$captured = [];

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
    &$orderIds,
    &$entitlementIds
): array {
    $email = 'fd-email-' . strtolower($suffix) . '-' . wp_generate_password(6, false, false) . '@example.com';

    $userId = wp_insert_user([
        'user_login' => 'fd_email_' . strtolower($suffix) . '_' . wp_generate_password(8, false, false),
        'user_pass' => wp_generate_password(24, true, true),
        'user_email' => $email,
        'display_name' => 'FD Email ' . $suffix,
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
    $product->set_name('FD EMAIL TEST ' . $suffix);
    $product->set_status('private');
    $product->set_catalog_visibility('hidden');
    $product->set_regular_price('1.00');
    $productId = (int) $product->save();

    if ($productId <= 0) {
        throw new RuntimeException('Falha ao criar produto fixture.');
    }

    $productIds[] = $productId;

    $order = wc_create_order([
        'customer_id' => $userId,
    ]);

    if (is_wp_error($order) || !$order instanceof WC_Order) {
        throw new RuntimeException('Falha ao criar pedido fixture.');
    }

    $order->set_billing_first_name('Aluno');
    $order->set_billing_last_name('Teste');
    $order->set_billing_email($email);
    $order->update_meta_data('_fd_test_cpf', '52998224725');
    $order->save();

    $orderId = (int) $order->get_id();
    $orderIds[] = $orderId;

    $entitlements = new EntitlementRepository();
    $entitlementId = $entitlements->grant(
        $userId,
        $productId,
        $orderId,
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
            'order_id' => $orderId,
        ],
        'test-email-1',
        sprintf(
            'generated/dev-email-test/%d-%d.pdf',
            $userId,
            $productId
        ),
        'FD-EMAIL-' . $productId,
        false,
        $passwordEnabled
    );

    $pdfId = (int) ($pdf['id'] ?? 0);

    if ($pdfId <= 0) {
        throw new RuntimeException('Falha ao criar PDF fixture.');
    }

    $files->markReady(
        $pdfId,
        123,
        str_repeat('b', 64)
    );

    return [
        'user_id' => $userId,
        'product_id' => $productId,
        'order_id' => $orderId,
        'entitlement_id' => $entitlementId,
        'pdf_id' => $pdfId,
        'email' => $email,
        'product_name' => 'FD EMAIL TEST ' . $suffix,
    ];
};

$mailFilter = static function ($return, array $atts) use (&$captured) {
    $captured[] = $atts;
    return true;
};

add_filter('pre_wp_mail', $mailFilter, 999, 2);

try {
    $assert(
        has_action('facil_digital_pdf_ready') !== false,
        'listener do evento pdf_ready registrado'
    );

    $assert(
        has_action('facil_digital_send_apostila_ready_email') !== false,
        'worker assincrono de email registrado'
    );

    $assert(
        has_filter('woocommerce_email_classes') !== false,
        'filtro de classes de email registrado'
    );

    $emails = WC()->mailer()->get_emails();

    $assert(
        isset($emails[ApostilaReadyEmail::class])
        && $emails[ApostilaReadyEmail::class] instanceof ApostilaReadyEmail,
        'email customizado registrado no WooCommerce'
    );

    $module = new ApostilaEmailModule();

    $protected = $createFixture('PROTEGIDA', true);

    $assert(
        function_exists('as_has_scheduled_action')
        && function_exists('as_unschedule_all_actions'),
        'Action Scheduler disponivel para fila de email'
    );

    do_action(
        'facil_digital_pdf_ready',
        (int) $protected['pdf_id']
    );

    $queued = as_has_scheduled_action(
        'facil_digital_send_apostila_ready_email',
        [(int) $protected['pdf_id']],
        'facil-digital'
    );

    as_unschedule_all_actions(
        'facil_digital_send_apostila_ready_email',
        [(int) $protected['pdf_id']],
        'facil-digital'
    );

    $assert(
        $queued,
        'evento pdf_ready enfileira email assincrono'
    );

    $assert(
        !as_has_scheduled_action(
            'facil_digital_send_apostila_ready_email',
            [(int) $protected['pdf_id']],
            'facil-digital'
        ),
        'fila assincrona da fixture removida'
    );

    $module->sendReadyEmail(
        (int) $protected['pdf_id']
    );

    $assert(
        count($captured) === 1,
        'primeiro envio protegido interceptado uma vez'
    );

    $first = $captured[0];

    $to = $first['to'] ?? '';
    $subject = (string) ($first['subject'] ?? '');
    $message = (string) ($first['message'] ?? '');

    $recipientList = is_array($to)
        ? $to
        : array_map('trim', explode(',', (string) $to));

    $assert(
        in_array($protected['email'], $recipientList, true),
        'destinatario usa email de cobranca do pedido'
    );

    $assert(
        str_contains($subject, 'Sua apostila já está disponível'),
        'assunto correto'
    );

    $assert(
        str_contains($message, $protected['product_name']),
        'conteudo inclui nome da apostila'
    );

    $assert(
        str_contains($message, 'Senha do PDF:'),
        'apostila protegida inclui orientacao de senha'
    );

    $assert(
        str_contains($message, '11 números'),
        'apostila protegida informa formato do CPF'
    );

    $assert(
        !str_contains($message, '52998224725'),
        'conteudo nao expoe CPF real'
    );

    $module->sendReadyEmail(
        (int) $protected['pdf_id']
    );

    $assert(
        count($captured) === 1,
        'idempotencia impede segundo envio do mesmo pdf_id'
    );

    $protectedOrder = wc_get_order(
        (int) $protected['order_id']
    );

    $sentMeta = $protectedOrder instanceof WC_Order
        ? $protectedOrder->get_meta(
            '_fd_apostila_ready_email_sent',
            true
        )
        : null;

    $assert(
        is_array($sentMeta)
        && isset($sentMeta[(string) $protected['pdf_id']]),
        'idempotencia gravada no pedido'
    );

    $plain = $createFixture('SEM-SENHA', false);

    $module->sendReadyEmail(
        (int) $plain['pdf_id']
    );

    $assert(
        count($captured) === 2,
        'apostila sem senha tambem envia notificacao'
    );

    $secondMessage = (string) ($captured[1]['message'] ?? '');

    $assert(
        str_contains($secondMessage, $plain['product_name']),
        'email sem senha inclui nome da apostila'
    );

    $assert(
        !str_contains($secondMessage, 'Senha do PDF:'),
        'apostila sem senha omite orientacao de CPF'
    );

    $assert(
        !str_contains($secondMessage, '52998224725'),
        'email sem senha tambem nao expoe CPF'
    );

    echo 'APOSTILA_READY_EMAIL_FUNCTIONAL=PASS' . PHP_EOL;
} finally {
    remove_filter('pre_wp_mail', $mailFilter, 999);

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

    foreach ($orderIds as $orderId) {
        $order = wc_get_order($orderId);

        if ($order instanceof WC_Order) {
            $order->delete(true);
        }
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
