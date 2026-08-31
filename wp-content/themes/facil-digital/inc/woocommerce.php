<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function fd_theme_cart_fragments(
    array $fragments
): array {
    $count =
        fd_theme_get_cart_count();

    ob_start();

    ?>
    <span
        class="fd-header-cart__count"
        aria-label="<?php
            echo esc_attr(
                sprintf(
                    _n(
                        '%d item no carrinho',
                        '%d itens no carrinho',
                        $count,
                        'facil-digital'
                    ),
                    $count
                )
            );
        ?>"
    >
        <?php
        echo esc_html(
            (string) $count
        );
        ?>
    </span>
    <?php

    $fragments[
        '.fd-header-cart__count'
    ] = (string) ob_get_clean();

    return $fragments;
}

add_filter(
    'woocommerce_add_to_cart_fragments',
    'fd_theme_cart_fragments'
);
/**
 * Contexto comercial da apostila dentro do carrinho.
 */
function fd_theme_cart_item_apostila_context(
    string $name,
    array $cartItem,
    string $cartItemKey
): string {
    unset($cartItemKey);

    $product =
        $cartItem['data']
        ?? null;

    if (
        !$product instanceof WC_Product
        || !function_exists(
            'fd_theme_core_product_metadata_available'
        )
        || !fd_theme_core_product_metadata_available()
    ) {
        return $name;
    }

    $productId =
        (int) $product->get_id();

    if (
        !\FacilDigital\Core\Products\ProductMetadata::isApostila(
            $productId
        )
    ) {
        return $name;
    }

    $parts = [];

    if (
        function_exists(
            'fd_theme_product_contest_names'
        )
    ) {
        $parts =
            fd_theme_product_contest_names(
                $productId
            );
    }

    if (
        function_exists(
            'fd_theme_product_meta'
        )
    ) {
        $position =
            fd_theme_product_meta(
                $productId,
                \FacilDigital\Core\Products\ProductMetadata::POSITION_NAME
            );

        if ($position !== '') {
            $parts[] =
                $position;
        }
    }

    $parts =
        array_values(
            array_unique(
                array_filter(
                    array_map(
                        'strval',
                        $parts
                    )
                )
            )
        );

    if ($parts === []) {
        return $name;
    }

    return
        $name
        . '<span class="fd-cart-item-context">'
        . esc_html(
            implode(
                ' • ',
                $parts
            )
        )
        . '</span>';
}

add_filter(
    'woocommerce_cart_item_name',
    'fd_theme_cart_item_apostila_context',
    20,
    3
);

/**
 * Bloco informativo sem interferir
 * no processamento nativo do WooCommerce.
 */
function fd_theme_cart_assurance(): void
{
    if (
        !function_exists('is_cart')
        || !is_cart()
    ) {
        return;
    }

    ?>
    <section
        class="fd-cart-assurance"
        aria-label="<?php
            echo esc_attr__(
                'Informações sobre sua compra',
                'facil-digital'
            );
        ?>"
    >
        <div>
            <?php
            echo fd_theme_icon(
                'check'
            );
            ?>

            <span>
                <?php
                echo esc_html__(
                    'Material digital',
                    'facil-digital'
                );
                ?>
            </span>
        </div>

        <div>
            <?php
            echo fd_theme_icon(
                'user'
            );
            ?>

            <span>
                <?php
                echo esc_html__(
                    'Acesso vinculado à sua conta',
                    'facil-digital'
                );
                ?>
            </span>
        </div>

        <div>
            <?php
            echo fd_theme_icon(
                'lock'
            );
            ?>

            <span>
                <?php
                echo esc_html__(
                    'Sem cobrança de frete',
                    'facil-digital'
                );
                ?>
            </span>
        </div>
    </section>
    <?php
}

add_action(
    'woocommerce_before_cart',
    'fd_theme_cart_assurance',
    5
);

function fd_theme_empty_cart_message(
    string $message
): string {
    unset($message);

    return __(
        'Seu carrinho está vazio. Explore as apostilas disponíveis e escolha o material para sua preparação.',
        'facil-digital'
    );
}

add_filter(
    'wc_empty_cart_message',
    'fd_theme_empty_cart_message',
    20
);

function fd_theme_return_to_shop_text(
    string $text
): string {
    unset($text);

    return __(
        'Ver apostilas',
        'facil-digital'
    );
}

add_filter(
    'woocommerce_return_to_shop_text',
    'fd_theme_return_to_shop_text',
    20
);

/**
 * Navegação principal da área do aluno.
 *
 * O Core adiciona Apostilas, Simulados e Resultados.
 * WooCommerce permanece responsável por pedidos
 * e dados da conta.
 */
function fd_theme_account_menu_items(
    array $items
): array {
    $labels = [
        'dashboard' =>
            __(
                'Visão geral',
                'facil-digital'
            ),

        'apostilas' =>
            __(
                'Minhas apostilas',
                'facil-digital'
            ),

        'simulados' =>
            __(
                'Simulados',
                'facil-digital'
            ),

        'resultados' =>
            __(
                'Resultados',
                'facil-digital'
            ),

        'orders' =>
            __(
                'Pedidos',
                'facil-digital'
            ),

        'downloads' =>
            __(
                'Downloads',
                'facil-digital'
            ),

        'edit-account' =>
            __(
                'Meus dados',
                'facil-digital'
            ),

        'seguranca' =>
            __(
                'Segurança',
                'facil-digital'
            ),

        'customer-logout' =>
            __(
                'Sair',
                'facil-digital'
            ),
    ];

    $order = [
        'dashboard',
        'apostilas',
        'simulados',
        'resultados',
        'orders',
        'downloads',
        'edit-account',
        'seguranca',
    ];

    $result = [];

    foreach ($order as $endpoint) {
        if ($endpoint === 'seguranca') {
            $result[$endpoint] =
                $labels[$endpoint];

            continue;
        }

        if (!isset($items[$endpoint])) {
            continue;
        }

        $result[$endpoint] =
            $labels[$endpoint]
            ?? $items[$endpoint];
    }

    /*
     * Mantemos endpoints adicionais de extensões,
     * mas retiramos "Endereços" da navegação
     * principal da plataforma digital.
     */
    foreach ($items as $endpoint => $label) {
        if (
            isset($result[$endpoint])
            || $endpoint === 'edit-address'
            || $endpoint === 'customer-logout'
        ) {
            continue;
        }

        $result[$endpoint] =
            $label;
    }

    if (
        isset(
            $items[
                'customer-logout'
            ]
        )
    ) {
        $result[
            'customer-logout'
        ] =
            $labels[
                'customer-logout'
            ];
    }

    return $result;
}

add_filter(
    'woocommerce_account_menu_items',
    'fd_theme_account_menu_items',
    100
);


/**
 * Segurança usa o mesmo formulário oficial do WooCommerce.
 *
 * Não registramos um segundo endpoint/processador de senha.
 */
function fd_theme_account_security_url(
    string $url,
    string $endpoint,
    string $value,
    string $permalink
): string {
    unset(
        $value,
        $permalink
    );

    if ($endpoint !== 'seguranca') {
        return $url;
    }

    return
        wc_get_account_endpoint_url(
            'edit-account'
        )
        . '#seguranca';
}

add_filter(
    'woocommerce_get_endpoint_url',
    'fd_theme_account_security_url',
    20,
    4
);


/**
 * Rótulos da listagem de pedidos da área do aluno.
 */
function fd_theme_account_orders_columns(
    array $columns
): array {
    $labels = [
        'order-number' =>
            __('Pedido', 'facil-digital'),

        'order-date' =>
            __('Data', 'facil-digital'),

        'order-status' =>
            __('Status', 'facil-digital'),

        'order-total' =>
            __('Total', 'facil-digital'),

        'order-actions' =>
            __('Ações', 'facil-digital'),
    ];

    foreach ($columns as $key => $label) {
        if (isset($labels[$key])) {
            $columns[$key] =
                $labels[$key];
        }
    }

    return $columns;
}

add_filter(
    'woocommerce_account_orders_columns',
    'fd_theme_account_orders_columns',
    100
);

/**
 * Ações dos pedidos.
 */
function fd_theme_account_order_actions(
    array $actions,
    WC_Order $order
): array {
    unset($order);

    $labels = [
        'view' =>
            __('Ver pedido', 'facil-digital'),

        'pay' =>
            __('Pagar', 'facil-digital'),

        'cancel' =>
            __('Cancelar', 'facil-digital'),
    ];

    foreach ($actions as $key => $action) {
        if (
            isset($labels[$key])
            && is_array($action)
        ) {
            $actions[$key]['name'] =
                $labels[$key];

            $actions[$key]['aria-label'] =
                $labels[$key];
        }
    }

    return $actions;
}

add_filter(
    'woocommerce_my_account_my_orders_actions',
    'fd_theme_account_order_actions',
    100,
    2
);

/**
 * Status Woo em PT-BR.
 *
 * Apenas os rótulos são alterados.
 * Os slugs/status internos permanecem intactos.
 */
function fd_theme_order_status_labels(
    array $statuses
): array {
    if (
        get_locale() !== 'pt_BR'
    ) {
        return $statuses;
    }

    $labels = [
        'wc-pending' =>
            __('Pagamento pendente', 'facil-digital'),

        'wc-processing' =>
            __('Processando', 'facil-digital'),

        'wc-on-hold' =>
            __('Aguardando', 'facil-digital'),

        'wc-completed' =>
            __('Concluído', 'facil-digital'),

        'wc-cancelled' =>
            __('Cancelado', 'facil-digital'),

        'wc-refunded' =>
            __('Reembolsado', 'facil-digital'),

        'wc-failed' =>
            __('Falhou', 'facil-digital'),

        'wc-checkout-draft' =>
            __('Rascunho do checkout', 'facil-digital'),
    ];

    foreach ($labels as $key => $label) {
        if (isset($statuses[$key])) {
            $statuses[$key] =
                $label;
        }
    }

    return $statuses;
}

add_filter(
    'wc_order_statuses',
    'fd_theme_order_status_labels',
    100
);

/**
 * Pequeno fallback para strings Woo que estão
 * sem pacote de tradução no ambiente atual.
 *
 * Mantemos o aviso de confirmação de e-mail:
 * apenas traduzimos sua apresentação.
 */
function fd_theme_woocommerce_ptbr_strings(
    string $translation,
    string $text,
    string $domain
): string {
    if (
        $domain !== 'woocommerce'
        || get_locale() !== 'pt_BR'
    ) {
        return $translation;
    }

    $strings = [
        'Confirm email address' =>
            'Confirme seu endereço de e-mail',

        'Confirm your email address to check for past orders and link them to your account.' =>
            'Confirme seu endereço de e-mail para localizar pedidos anteriores e vinculá-los à sua conta.',
    ];

    return
        $strings[$text]
        ?? $translation;
}

add_filter(
    'gettext_woocommerce',
    'fd_theme_woocommerce_ptbr_strings',
    20,
    3
);
