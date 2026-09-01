<?php

declare(strict_types=1);

namespace FacilDigital\Core\WooCommerce;

use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Core\Capabilities;
use FacilDigital\Core\Products\ProductMetadata;
use FacilDigital\Core\Security\Cpf;
use WC_Order;
use WP_Error;

final class CheckoutModule implements ModuleInterface
{
    /**
     * Meta legado/compatível usado pelo checkout clássico.
     */
    public const ORDER_CPF_META = '_fd_billing_cpf';

    /**
     * Campo oficial da Additional Checkout Fields API.
     */
    public const BLOCK_CPF_FIELD = 'facil-digital/cpf';

    public function register(): void
    {
        /*
         * Checkout Block moderno.
         */
        add_action(
            'woocommerce_init',
            [$this, 'registerBlockCpfField'],
            20
        );

        /*
         * Checkout clássico / shortcode.
         */
        add_filter(
            'woocommerce_checkout_fields',
            [$this, 'filterCheckoutFields']
        );

        /*
         * A conta continuará obrigatória para apostilas protegidas.
         */
        add_filter(
            'woocommerce_checkout_registration_enabled',
            [$this, 'enableRegistration']
        );

        add_filter(
            'woocommerce_checkout_registration_required',
            [$this, 'requireRegistration']
        );

        /*
         * Validação do checkout clássico.
         */
        add_action(
            'woocommerce_after_checkout_validation',
            [$this, 'validateCheckout'],
            10,
            2
        );

        /*
         * Persistência do CPF no checkout clássico.
         */
        add_action(
            'woocommerce_checkout_create_order',
            [$this, 'saveOrderCpf'],
            20,
            2
        );

        /*
         * Admin sempre exibe somente CPF mascarado.
         */
        add_action(
            'woocommerce_admin_order_data_after_billing_address',
            [$this, 'renderMaskedCpf']
        );
    }

    /**
     * Registra CPF usando a API moderna do Checkout Block.
     *
     * O campo fica no grupo "order":
     * - é salvo no pedido;
     * - não precisa ser salvo no perfil do cliente;
     * - reduz duplicação desnecessária de PII.
     */
    public function registerBlockCpfField(): void
    {
        if (
            !function_exists(
                'woocommerce_register_additional_checkout_field'
            )
        ) {
            return;
        }

        woocommerce_register_additional_checkout_field([
            'id' => self::BLOCK_CPF_FIELD,

            'label' => __(
                'CPF',
                'facil-digital-core'
            ),

            'location' => 'order',

            'type' => 'text',

            /*
             * A loja atualmente comercializa apostilas e o CPF
             * participa da proteção do PDF.
             *
             * Quando houver outros tipos de produtos, poderemos
             * evoluir para condição baseada no carrinho.
             */
            'required' => true,

            'attributes' => [
                'autocomplete' => 'off',

                'maxLength' => 14,

                'pattern' => '[0-9.\-]{11,14}',

                'title' => __(
                    'Informe um CPF válido.',
                    'facil-digital-core'
                ),
            ],

            'sanitize_callback' => static function (
                mixed $value
            ): string {
                if (!is_scalar($value)) {
                    return '';
                }

                return Cpf::normalize(
                    (string) $value
                );
            },

            'validate_callback' => static function (
                mixed $value
            ) {
                $cpf = is_scalar($value)
                    ? (string) $value
                    : '';

                if (Cpf::isValid($cpf)) {
                    return null;
                }

                return new WP_Error(
                    'fd_invalid_cpf',
                    __(
                        'Informe um CPF válido para proteger sua apostila.',
                        'facil-digital-core'
                    )
                );
            },
        ]);
    }

    /**
     * Checkout clássico.
     *
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public function filterCheckoutFields(
        array $fields
    ): array {
        if (
            !$this->cartContainsProtectedApostila()
        ) {
            return $fields;
        }

        if (
            !isset($fields['billing'])
            || !is_array($fields['billing'])
        ) {
            $fields['billing'] = [];
        }

        $fields['billing']['billing_cpf'] = [
            'type' => 'text',

            'label' => __(
                'CPF',
                'facil-digital-core'
            ),

            'placeholder' => '000.000.000-00',

            'required' => true,

            'class' => [
                'form-row-wide',
            ],

            'priority' => 35,

            'autocomplete' => 'off',

            'custom_attributes' => [
                'inputmode' => 'numeric',
                'maxlength' => '14',
            ],
        ];

        return $fields;
    }

    public function enableRegistration(
        bool $enabled
    ): bool {
        return $this->cartContainsProtectedApostila()
            ? true
            : $enabled;
    }

    public function requireRegistration(
        bool $required
    ): bool {
        return $this->cartContainsProtectedApostila()
            ? true
            : $required;
    }

    /**
     * Checkout clássico.
     *
     * @param array<string, mixed> $data
     */
    public function validateCheckout(
        array $data,
        WP_Error $errors
    ): void {
        if (
            !$this->cartContainsProtectedApostila()
        ) {
            return;
        }

        $raw = isset($data['billing_cpf'])
            && is_scalar($data['billing_cpf'])
                ? (string) $data['billing_cpf']
                : '';

        if (Cpf::isValid($raw)) {
            return;
        }

        $errors->add(
            'fd_invalid_cpf',
            __(
                'Informe um CPF válido para proteger sua apostila.',
                'facil-digital-core'
            )
        );
    }

    /**
     * Checkout clássico.
     *
     * @param array<string, mixed> $data
     */
    public function saveOrderCpf(
        WC_Order $order,
        array $data
    ): void {
        $raw = isset($data['billing_cpf'])
            && is_scalar($data['billing_cpf'])
                ? (string) $data['billing_cpf']
                : '';

        $cpf = Cpf::normalize($raw);

        if (!Cpf::isValid($cpf)) {
            return;
        }

        $order->update_meta_data(
            self::ORDER_CPF_META,
            $cpf
        );
    }

    public function renderMaskedCpf(
        WC_Order $order
    ): void {
        if (
            !current_user_can(
                Capabilities::MANAGE_ENTITLEMENTS
            )
            && !current_user_can(
                'manage_woocommerce'
            )
        ) {
            return;
        }

        $cpf = self::getOrderCpf($order);

        if ($cpf === '') {
            return;
        }

        echo '<p><strong>';

        echo esc_html__(
            'CPF Fácil Digital+',
            'facil-digital-core'
        );

        echo ':</strong> ';

        echo esc_html(
            Cpf::mask($cpf)
        );

        echo '</p>';
    }

    /**
     * Ponto único para recuperar CPF do pedido.
     *
     * Suporta:
     * - checkout clássico;
     * - Checkout Block.
     */
    public static function getOrderCpf(
        WC_Order $order
    ): string {
        $legacy = $order->get_meta(
            self::ORDER_CPF_META,
            true
        );

        if (is_scalar($legacy)) {
            $cpf = Cpf::normalize(
                (string) $legacy
            );

            if ($cpf !== '') {
                return $cpf;
            }
        }

        /*
         * Additional Checkout Fields API:
         *
         * location=order
         * =>
         * _wc_other/{field-id}
         */
        $blockValue = $order->get_meta(
            '_wc_other/'
            . self::BLOCK_CPF_FIELD,
            true
        );

        if (is_scalar($blockValue)) {
            return Cpf::normalize(
                (string) $blockValue
            );
        }

        return '';
    }

    private function cartContainsProtectedApostila(): bool
    {
        if (
            !function_exists('WC')
            || WC()->cart === null
        ) {
            return false;
        }

        foreach (
            WC()->cart->get_cart()
            as $item
        ) {
            $productId =
                isset($item['product_id'])
                    ? (int) $item['product_id']
                    : 0;

            if (
                $productId > 0
                && ProductMetadata::isApostila(
                    $productId
                )
            ) {
                return true;
            }
        }

        return false;
    }
}
