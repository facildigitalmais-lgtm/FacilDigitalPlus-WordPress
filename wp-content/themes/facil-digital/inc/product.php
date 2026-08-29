<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function fd_theme_prepare_single_product_hooks(): void
{
    if (
        !function_exists('is_product')
        || !is_product()
    ) {
        return;
    }

    remove_action(
        'woocommerce_before_single_product_summary',
        'woocommerce_show_product_sale_flash',
        10
    );

    remove_action(
        'woocommerce_before_single_product_summary',
        'woocommerce_show_product_images',
        20
    );

    remove_action(
        'woocommerce_single_product_summary',
        'woocommerce_template_single_title',
        5
    );

    remove_action(
        'woocommerce_single_product_summary',
        'woocommerce_template_single_rating',
        10
    );

    remove_action(
        'woocommerce_single_product_summary',
        'woocommerce_template_single_price',
        10
    );

    remove_action(
        'woocommerce_single_product_summary',
        'woocommerce_template_single_excerpt',
        20
    );

    remove_action(
        'woocommerce_single_product_summary',
        'woocommerce_template_single_add_to_cart',
        30
    );

    remove_action(
        'woocommerce_single_product_summary',
        'woocommerce_template_single_meta',
        40
    );

    remove_action(
        'woocommerce_single_product_summary',
        'woocommerce_template_single_sharing',
        50
    );

    /*
     * Nao removemos WC_Structured_Data::generate_product_data
     * em prioridade 60. Assim o schema Product do WooCommerce
     * continua sendo gerado.
     */

    remove_action(
        'woocommerce_after_single_product_summary',
        'woocommerce_output_product_data_tabs',
        10
    );

    remove_action(
        'woocommerce_after_single_product_summary',
        'woocommerce_upsell_display',
        15
    );

    remove_action(
        'woocommerce_after_single_product_summary',
        'woocommerce_output_related_products',
        20
    );
}

add_action(
    'wp',
    'fd_theme_prepare_single_product_hooks',
    6
);

function fd_theme_get_related_product_objects(
    WC_Product $product,
    int $limit = 3
): array {
    $ids =
        wc_get_related_products(
            $product->get_id(),
            $limit,
            [
                $product->get_id(),
            ]
        );

    if ($ids === []) {
        $ids =
            wc_get_products(
                [
                    'status'  =>
                        'publish',

                    'limit'   =>
                        $limit,

                    'exclude' =>
                        [
                            $product
                                ->get_id(),
                        ],

                    'orderby' =>
                        'date',

                    'order' =>
                        'DESC',

                    'return' =>
                        'ids',
                ]
            );
    }

    $products = [];

    foreach ($ids as $id) {
        $related =
            wc_get_product(
                (int) $id
            );

        if (
            !$related
            || !$related->is_visible()
        ) {
            continue;
        }

        $products[] =
            $related;
    }

    return $products;
}

function fd_theme_product_stock_label(
    WC_Product $product
): string {
    if (!$product->is_in_stock()) {
        return __(
            'Indisponivel',
            'facil-digital'
        );
    }

    return __(
        'Disponivel',
        'facil-digital'
    );
}
