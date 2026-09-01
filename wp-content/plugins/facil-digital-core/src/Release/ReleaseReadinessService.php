<?php

declare(strict_types=1);

namespace FacilDigital\Core\Release;

use FacilDigital\Core\Core\Database;
use FacilDigital\Core\PDFs\PrivateStorage;
use WC_Order;

final class ReleaseReadinessService
{
    /** @return array<string,mixed> */
    public function report(string $stage = 'sandbox'): array
    {
        $stage = strtolower(trim($stage));
        if (!in_array($stage, ['sandbox', 'production'], true)) {
            $stage = 'sandbox';
        }

        $checks = [];
        $add = static function (string $id, string $status, string $message) use (&$checks): void {
            $checks[] = [
                'id' => $id,
                'status' => $status,
                'message' => $message,
            ];
        };

        $environment = function_exists('wp_get_environment_type')
            ? wp_get_environment_type()
            : 'production';

        $home = (string) get_option('home');
        $siteurl = (string) get_option('siteurl');

        $add(
            'core_version',
            defined('FACIL_DIGITAL_CORE_VERSION') && version_compare(FACIL_DIGITAL_CORE_VERSION, '0.9.0', '>=') ? 'pass' : 'fail',
            defined('FACIL_DIGITAL_CORE_VERSION') ? FACIL_DIGITAL_CORE_VERSION : 'indefinida'
        );

        $add('woocommerce', class_exists('WooCommerce') ? 'pass' : 'fail', 'WooCommerce ativo');

        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $mpActive = function_exists('is_plugin_active')
            && is_plugin_active('woocommerce-mercadopago/woocommerce-mercadopago.php');
        $add('mercado_pago_official', $mpActive ? 'pass' : 'fail', 'Plugin oficial Mercado Pago ativo');

        $https = str_starts_with($home, 'https://') && str_starts_with($siteurl, 'https://');
        $add('https_urls', $https ? 'pass' : 'fail', 'home/siteurl em HTTPS');

        $homeHost = (string) wp_parse_url($home, PHP_URL_HOST);
        $siteHost = (string) wp_parse_url($siteurl, PHP_URL_HOST);
        $add('same_host', $homeHost !== '' && $homeHost === $siteHost ? 'pass' : 'fail', 'home/siteurl no mesmo host');

        $permalink = (string) get_option('permalink_structure');
        $add('permalinks', $permalink !== '' ? 'pass' : 'warn', $permalink !== '' ? 'permalinks amigaveis ativos' : 'permalinks simples');

        $actionScheduler = function_exists('as_enqueue_async_action') || function_exists('as_schedule_single_action');
        $add('action_scheduler', $actionScheduler ? 'pass' : 'fail', 'Action Scheduler disponivel');

        try {
            $storage = new PrivateStorage();
            $storage->ensureReady();
            $root = wp_normalize_path($storage->root());
            $webroot = wp_normalize_path(ABSPATH);
            $outside = !str_starts_with($root . '/', rtrim($webroot, '/') . '/');
            $writable = is_writable($root);
            $add('private_storage', $outside && $writable ? 'pass' : 'fail', 'storage privado fora da webroot e gravavel');
        } catch (\Throwable $e) {
            unset($e);
            $add('private_storage', 'fail', 'storage privado indisponivel');
        }

        $routes = rest_get_server()->get_routes();
        $add('rest_health', isset($routes['/facil-digital/v1/health']) ? 'pass' : 'fail', 'rota health registrada');

        $debug = defined('WP_DEBUG') && WP_DEBUG;
        $fileEditDisabled = defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT;
        $forceSslAdmin = defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN;

        if ($stage === 'production') {
            $add('environment', $environment === 'production' ? 'pass' : 'fail', 'WP_ENVIRONMENT_TYPE=production');
            $add('wp_debug', !$debug ? 'pass' : 'fail', 'WP_DEBUG desativado');
            $add('file_editor', $fileEditDisabled ? 'pass' : 'fail', 'editor de arquivos desativado');
            $add('ssl_admin', $forceSslAdmin ? 'pass' : 'fail', 'FORCE_SSL_ADMIN ativo');

            $developmentHost = $homeHost === ''
                || $homeHost === 'localhost'
                || str_ends_with($homeHost, '.localhost')
                || str_contains($homeHost, '.app.github.dev')
                || str_contains($homeHost, '127.0.0.1');
            $add('production_domain', !$developmentHost ? 'pass' : 'fail', 'dominio publico de producao');

            $searchVisible = (string) get_option('blog_public') === '1';
            $add('search_visibility', $searchVisible ? 'pass' : 'warn', 'visibilidade para mecanismos de busca');
        } else {
            $add('environment', $environment !== 'production' ? 'pass' : 'warn', 'sandbox preferencialmente fora de producao');
        }

        $failed = array_values(array_filter(
            $checks,
            static fn(array $check): bool => $check['status'] === 'fail'
        ));

        return [
            'stage' => $stage,
            'ready_automated' => $failed === [],
            'environment' => $environment,
            'home_host' => $homeHost,
            'checks' => $checks,
            'manual_gates' => $this->manualGates($stage),
        ];
    }

    /** @return list<string> */
    private function manualGates(string $stage): array
    {
        if ($stage === 'production') {
            return [
                'backup_com_restore_testado',
                'dns_ssl_e_email_confirmados',
                'conta_mercado_pago_real_vinculada',
                'credenciais_produtivas_fora_do_git',
                'compra_real_controlada_aprovada',
                'webhook_refletiu_pedido_pago',
                'entitlement_criado',
                'pdf_personalizado_pronto',
                'download_do_aluno_validado',
                'rollback_documentado',
            ];
        }

        return [
            'conta_teste_vendedor_criada',
            'conta_teste_comprador_distinta_criada',
            'loja_vinculada_a_conta_teste',
            'compra_teste_aprovada',
            'webhook_refletiu_pedido_pago',
            'entitlement_criado',
            'pdf_personalizado_pronto',
            'download_do_aluno_validado',
        ];
    }

    /** @return array<string,mixed> */
    public function paymentProof(int $orderId, bool $requirePdf = true): array
    {
        $order = function_exists('wc_get_order') ? wc_get_order($orderId) : false;
        if (!$order instanceof WC_Order) {
            return [
                'ready' => false,
                'order_id' => $orderId,
                'reason' => 'order_not_found',
            ];
        }

        $paymentMethod = strtolower((string) $order->get_payment_method());
        $isMercadoPago = str_contains($paymentMethod, 'mercado') && str_contains($paymentMethod, 'pago');
        $paid = $order->is_paid();
        $transactionId = trim((string) $order->get_transaction_id());

        /*
         * O plugin oficial Mercado Pago 8.x pode concluir o
         * pagamento via WC_Order::payment_complete() sem passar
         * o transaction_id nativo do WooCommerce.
         *
         * Nesse fluxo, o ID confirmado pelo Mercado Pago fica em
         * _Mercado_Pago_Payment_IDs.
         */
        $mercadoPagoPaymentIds = trim(
            (string) $order->get_meta(
                '_Mercado_Pago_Payment_IDs',
                true
            )
        );

        $transactionRecorded =
            $transactionId !== ''
            || $mercadoPagoPaymentIds !== '';

        $transactionSource =
            $transactionId !== ''
                ? 'woocommerce_transaction_id'
                : (
                    $mercadoPagoPaymentIds !== ''
                        ? 'mercado_pago_payment_ids'
                        : 'none'
                );

        global $wpdb;
        $entitlementsTable = Database::table('entitlements');
        $pdfTable = Database::table('pdf_files');

        $activeEntitlements = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$entitlementsTable} WHERE order_id = %d AND status = 'active'",
                $orderId
            )
        );

        $readyPdfs = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$pdfTable} WHERE order_id = %d AND status = 'ready'",
                $orderId
            )
        );

        $ready = $isMercadoPago
            && $paid
            && $transactionRecorded
            && $activeEntitlements > 0
            && (!$requirePdf || $readyPdfs > 0);

        return [
            'ready' => $ready,
            'order_id' => $orderId,
            'status' => $order->get_status(),
            'gateway_is_mercado_pago' => $isMercadoPago,
            'paid' => $paid,
            'transaction_recorded' => $transactionRecorded,
            'transaction_source' => $transactionSource,
            'mercado_pago_payment_ids_recorded' => $mercadoPagoPaymentIds !== '',
            'active_entitlements' => $activeEntitlements,
            'ready_pdfs' => $readyPdfs,
            'pdf_required' => $requirePdf,
        ];
    }
}
