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
    $limit =
        max(
            1,
            $limit
        );

    $candidateIds =
        wc_get_related_products(
            $product->get_id(),
            max(
                12,
                $limit * 4
            ),
            [
                $product->get_id(),
            ]
        );

    $products = [];

    $seen = [
        (int) $product->get_id() =>
            true,
    ];

    $appendProduct =
        static function (
            int $productId
        ) use (
            &$products,
            &$seen,
            $limit
        ): void {
            if (
                isset($seen[$productId])
                || count($products) >= $limit
            ) {
                return;
            }

            $seen[$productId] =
                true;

            $related =
                wc_get_product(
                    $productId
                );

            if (
                !$related instanceof WC_Product
                || !$related->is_visible()
            ) {
                return;
            }

            if (
                fd_theme_core_product_metadata_available()
                && !\FacilDigital\Core\Products\ProductMetadata::isApostila(
                    $productId
                )
            ) {
                return;
            }

            $products[] =
                $related;
        };

    foreach ($candidateIds as $id) {
        $appendProduct(
            (int) $id
        );
    }

    /*
     * Quando os relacionados nativos do WooCommerce
     * nao forem suficientes, completa somente com
     * apostilas publicadas recentes.
     */
    if (count($products) < $limit) {
        $recentIds =
            wc_get_products(
                [
                    'status' =>
                        'publish',

                    'limit' =>
                        max(
                            12,
                            $limit * 4
                        ),

                    'exclude' =>
                        array_keys($seen),

                    'orderby' =>
                        'date',

                    'order' =>
                        'DESC',

                    'return' =>
                        'ids',
                ]
            );

        foreach ($recentIds as $id) {
            $appendProduct(
                (int) $id
            );

            if (count($products) >= $limit) {
                break;
            }
        }
    }

    return array_slice(
        $products,
        0,
        $limit
    );
}

function fd_theme_product_stock_label(
    WC_Product $product
): string {
    if (!$product->is_in_stock()) {
        return __(
            'Indisponível',
            'facil-digital'
        );
    }

    return __(
        'Disponível',
        'facil-digital'
    );
}


function fd_theme_single_add_to_cart_text(
    string $text
): string {
    global $product;

    if (
        !$product instanceof WC_Product
        || !fd_theme_core_product_metadata_available()
        || !\FacilDigital\Core\Products\ProductMetadata::isApostila(
            (int) $product->get_id()
        )
    ) {
        return $text;
    }

    return __(
        'Comprar apostila',
        'facil-digital'
    );
}

add_filter(
    'woocommerce_product_single_add_to_cart_text',
    'fd_theme_single_add_to_cart_text',
    20
);
