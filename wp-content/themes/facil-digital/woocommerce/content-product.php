<?php
/**
 * Product content inside catalog loops.
 *
 * @package FacilDigital
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

global $product;

if (
    !$product instanceof WC_Product
    || !$product->is_visible()
) {
    return;
}

?>

<li
    <?php
    wc_product_class(
        'fd-catalog-item',
        $product
    );
    ?>
    data-product-id="<?php
        echo esc_attr(
            (string) $product->get_id()
        );
    ?>"
>
    <?php
    get_template_part(
        'template-parts/components/product-card',
        null,
        [
            'product' =>
                $product,
        ]
    );
    ?>
</li>
