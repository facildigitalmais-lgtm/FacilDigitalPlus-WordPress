<?php
/**
 * Product content inside catalog loops.
 *
 * Base WooCommerce template compatibility:
 * content-product.php @version 9.4.0.
 *
 * @package FacilDigital
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

global $product;

if (
    !is_a(
        $product,
        WC_Product::class
    )
    || !$product->is_visible()
) {
    return;
}

?>

<li
    <?php
    wc_product_class(
        'fd-catalog-card',
        $product
    );
    ?>
    data-product-id="<?php
        echo esc_attr(
            (string) $product
                ->get_id()
        );
    ?>"
>
    <?php
    do_action(
        'woocommerce_before_shop_loop_item'
    );
    ?>

    <div class="fd-catalog-card__media">
        <?php
        do_action(
            'woocommerce_before_shop_loop_item_title'
        );
        ?>
    </div>

    <div class="fd-catalog-card__body">
        <span class="fd-catalog-card__type">
            <?php
            echo esc_html__(
                'Apostila digital',
                'facil-digital'
            );
            ?>
        </span>

        <?php
        do_action(
            'woocommerce_shop_loop_item_title'
        );

        do_action(
            'woocommerce_after_shop_loop_item_title'
        );

        do_action(
            'woocommerce_after_shop_loop_item'
        );
        ?>
    </div>
</li>
