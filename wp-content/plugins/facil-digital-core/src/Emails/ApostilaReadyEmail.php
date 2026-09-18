<?php

declare(strict_types=1);

namespace FacilDigital\Core\Emails;

use WC_Email;
use WC_Order;

final class ApostilaReadyEmail extends WC_Email
{
    private int $productId = 0;
    private bool $passwordEnabled = false;

    public function __construct()
    {
        $this->id = 'facil_digital_apostila_ready';
        $this->customer_email = true;
        $this->title = __(
            'Apostila disponível',
            'facil-digital-core'
        );
        $this->description = __(
            'Enviado ao aluno quando a apostila personalizada está pronta para download.',
            'facil-digital-core'
        );

        parent::__construct();
    }

    public function get_default_subject(): string
    {
        return __(
            'Sua apostila já está disponível',
            'facil-digital-core'
        );
    }

    public function get_default_heading(): string
    {
        return __(
            'Sua apostila já está disponível',
            'facil-digital-core'
        );
    }

    public function trigger(int $orderId, int $productId, bool $passwordEnabled): bool
    {
        $order = wc_get_order($orderId);
        if (!$order instanceof WC_Order) {
            return false;
        }
        $this->setup_locale();
        try {
            $this->object = $order;
            $this->recipient = $order->get_billing_email();
            $this->productId = $productId;
            $this->passwordEnabled = $passwordEnabled;
            return $this->send_notification();
        } finally {
            $this->restore_locale();
        }
    }

    public function get_content_html(): string
    {
        $order = $this->object;
        if (!$order instanceof WC_Order) {
            return '';
        }
        $product = wc_get_product($this->productId);
        $productName = $product ? $product->get_name() : __('Apostila', 'facil-digital-core');
        $firstName = trim((string) $order->get_billing_first_name());
        if ($firstName === '') {
            $firstName = __('aluno', 'facil-digital-core');
        }
        $accountUrl = wc_get_account_endpoint_url('apostilas');
        ob_start();
        do_action('woocommerce_email_header', $this->get_heading(), $this);
        echo '<p>' . esc_html(sprintf(__('Olá, %s!', 'facil-digital-core'), $firstName)) . '</p>';
        echo '<p>' . esc_html__('Sua apostila está pronta e já pode ser acessada na Área do Aluno:', 'facil-digital-core') . ' <strong>' . esc_html($productName) . '</strong>.</p>';
        if ($this->passwordEnabled) {
            echo '<p><strong>' . esc_html__('Senha do PDF:', 'facil-digital-core') . '</strong> ' . esc_html__('use o CPF informado no momento da compra, digitando somente os 11 números, sem pontos e sem traço.', 'facil-digital-core') . '</p>';
        }
        echo '<p><a href="' . esc_url($accountUrl) . '">' . esc_html__('Acessar minhas apostilas', 'facil-digital-core') . '</a></p>';
        do_action('woocommerce_email_footer', $this);
        return (string) ob_get_clean();
    }

    public function get_content_plain(): string
    {
        $order = $this->object;
        if (!$order instanceof WC_Order) {
            return '';
        }
        $product = wc_get_product($this->productId);
        $productName = $product ? $product->get_name() : __('Apostila', 'facil-digital-core');
        $firstName = trim((string) $order->get_billing_first_name());
        if ($firstName === '') {
            $firstName = __('aluno', 'facil-digital-core');
        }
        $lines = [sprintf(__('Olá, %s!', 'facil-digital-core'), $firstName), '', sprintf(__('Sua apostila está pronta e já pode ser acessada na Área do Aluno: %s.', 'facil-digital-core'), $productName)];
        if ($this->passwordEnabled) {
            $lines[] = '';
            $lines[] = __('Senha do PDF: use o CPF informado no momento da compra, digitando somente os 11 números, sem pontos e sem traço.', 'facil-digital-core');
        }
        $lines[] = '';
        $lines[] = wc_get_account_endpoint_url('apostilas');
        return implode(PHP_EOL, $lines);
    }
}
