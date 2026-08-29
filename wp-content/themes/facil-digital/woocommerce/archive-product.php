<?php
/**
 * Product archive template.
 *
 * Base WooCommerce template compatibility:
 * archive-product.php @version 8.6.0.
 *
 * @package FacilDigital
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

get_header('shop');

do_action(
    'woocommerce_before_main_content'
);

get_template_part(
    'template-parts/catalog/header'
);

if (woocommerce_product_loop()) {
    /*
     * Preserva notices e extensoes de terceiros.
     * Result count e ordering padrao foram removidos
     * em inc/catalog.php porque temos toolbar propria.
     */
    do_action(
        'woocommerce_before_shop_loop'
    );

    get_template_part(
        'template-parts/catalog/toolbar'
    );

    woocommerce_product_loop_start();

    if (
        wc_get_loop_prop(
            'total'
        )
    ) {
        while (have_posts()) {
            the_post();

            do_action(
                'woocommerce_shop_loop'
            );

            wc_get_template_part(
                'content',
                'product'
            );
        }
    }

    woocommerce_product_loop_end();

    do_action(
        'woocommerce_after_shop_loop'
    );
} else {
    get_template_part(
        'template-parts/catalog/empty'
    );
}

do_action(
    'woocommerce_after_main_content'
);

get_footer('shop');
