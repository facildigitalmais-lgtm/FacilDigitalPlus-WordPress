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