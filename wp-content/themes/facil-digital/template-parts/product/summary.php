<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$product =
    $args['product']
    ?? null;

if (
    !$product
    || !is_a(
        $product,
        WC_Product::class
    )
) {
    return;
}

$GLOBALS['product'] =
    $product;

$price =
    $product->get_price_html();

$shortDescription =
    $product
        ->get_short_description();

$stockHtml =
    wc_get_stock_html(
        $product
    );

?>

<section class="fd-product-primary">
    <div class="fd-product-primary__media">
        <?php
        if ($product->is_on_sale()) {
            woocommerce_show_product_sale_flash();
        }

        woocommerce_show_product_images();
        ?>
    </div>

    <div class="fd-product-primary__summary">
        <span class="fd-eyebrow">
            <?php
            echo esc_html__(
                'Apostila digital',
                'facil-digital'
            );
            ?>
        </span>

        <h1 class="fd-product-title">
            <?php
            echo esc_html(
                $product->get_name()
            );
            ?>
        </h1>

        <div class="fd-product-price">
            <?php
            if ($price !== '') {
                echo wp_kses_post(
                    $price
                );
            } else {
                echo esc_html__(
                    'Consulte o valor',
                    'facil-digital'
                );
            }
            ?>
        </div>

        <?php
        if (
            $shortDescription !== ''
        ) :
            ?>
            <div class="fd-product-excerpt">
                <?php
                echo wp_kses_post(
                    apply_filters(
                        'woocommerce_short_description',
                        $shortDescription
                    )
                );
                ?>
            </div>
        <?php endif; ?>

        <div class="fd-product-stock">
            <span
                class="<?php
                    echo $product->is_in_stock()
                        ? 'fd-product-stock__dot fd-product-stock__dot--available'
                        : 'fd-product-stock__dot fd-product-stock__dot--unavailable';
                ?>"
                aria-hidden="true"
            ></span>

            <span>
                <?php
                echo esc_html(
                    fd_theme_product_stock_label(
                        $product
                    )
                );
                ?>
            </span>

            <?php
            if ($stockHtml !== '') :
                ?>
                <span class="fd-product-stock__native">
                    <?php
                    echo wp_kses_post(
                        $stockHtml
                    );
                    ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="fd-product-purchase">
            <?php
            woocommerce_template_single_add_to_cart();
            ?>
        </div>

        <ul class="fd-product-trust">
            <li>
                <?php
                echo fd_theme_icon(
                    'check'
                );
                ?>
                <span>
                    <?php
                    echo esc_html__(
                        'Preco unico do produto',
                        'facil-digital'
                    );
                    ?>
                </span>
            </li>

            <li>
                <?php
                echo fd_theme_icon(
                    'user'
                );
                ?>
                <span>
                    <?php
                    echo esc_html__(
                        'Compra vinculada a sua conta',
                        'facil-digital'
                    );
                    ?>
                </span>
            </li>

            <li>
                <?php
                echo fd_theme_icon(
                    'lock'
                );
                ?>
                <span>
                    <?php
                    echo esc_html__(
                        'Checkout operado pelo WooCommerce',
                        'facil-digital'
                    );
                    ?>
                </span>
            </li>
        </ul>
    </div>
</section>
