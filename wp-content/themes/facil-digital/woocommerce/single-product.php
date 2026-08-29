<?php
/**
 * Single product template.
 *
 * Base WooCommerce template compatibility:
 * single-product.php @version 1.6.4.
 *
 * @package FacilDigital
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

get_header('shop');

do_action(
    'woocommerce_before_main_content'
);

while (have_posts()) :
    the_post();

    $product =
        wc_get_product(
            get_the_ID()
        );

    if (!$product) {
        continue;
    }

    $GLOBALS['product'] =
        $product;

    do_action(
        'woocommerce_before_single_product'
    );

    if (post_password_required()) {
        echo get_the_password_form();

        continue;
    }

    ?>

    <article
        id="product-<?php the_ID(); ?>"
        <?php
        wc_product_class(
            'fd-product-page',
            $product
        );
        ?>
    >
        <?php
        /*
         * Callbacks visuais padrao foram removidos.
         * Hooks de extensoes permanecem disponiveis.
         */
        do_action(
            'woocommerce_before_single_product_summary'
        );

        get_template_part(
            'template-parts/product/summary',
            null,
            [
                'product' =>
                    $product,
            ]
        );

        /*
         * Mantemos o action para extensoes e,
         * especialmente, WC_Structured_Data em
         * prioridade 60.
         */
        do_action(
            'woocommerce_single_product_summary'
        );

        get_template_part(
            'template-parts/product/benefits',
            null,
            [
                'product' =>
                    $product,
            ]
        );

        get_template_part(
            'template-parts/product/details',
            null,
            [
                'product' =>
                    $product,
            ]
        );

        get_template_part(
            'template-parts/product/simulations',
            null,
            [
                'product' =>
                    $product,
            ]
        );

        get_template_part(
            'template-parts/product/faq',
            null,
            [
                'product' =>
                    $product,
            ]
        );

        get_template_part(
            'template-parts/product/related',
            null,
            [
                'product' =>
                    $product,
            ]
        );

        do_action(
            'woocommerce_after_single_product_summary'
        );
        ?>
    </article>

    <?php

    do_action(
        'woocommerce_after_single_product'
    );

endwhile;

do_action(
    'woocommerce_after_main_content'
);

get_footer('shop');
