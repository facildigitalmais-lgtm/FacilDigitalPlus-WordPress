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
        'WC_Product'
    )
) {
    return;
}

$productId =
    (int) $product->get_id();

$url =
    get_permalink(
        $productId
    );

if (!is_string($url)) {
    return;
}

?>

<article class="fd-product-card">
    <a
        class="fd-product-card__image-link"
        href="<?php
            echo esc_url($url);
        ?>"
        tabindex="-1"
        aria-hidden="true"
    >
        <div class="fd-product-card__image-wrap">
            <?php
            echo wp_kses_post(
                $product->get_image(
                    'woocommerce_thumbnail',
                    [
                        'class' =>
                            'fd-product-card__image',

                        'loading' =>
                            'lazy',
                    ]
                )
            );
            ?>
        </div>
    </a>

    <div class="fd-product-card__content">
        <span class="fd-product-card__type">
            Apostila digital
        </span>

        <h3 class="fd-product-card__title">
            <a
                href="<?php
                    echo esc_url(
                        $url
                    );
                ?>"
            >
                <?php
                echo esc_html(
                    $product->get_name()
                );
                ?>
            </a>
        </h3>

        <div class="fd-product-card__price">
            <?php
            $price =
                $product
                    ->get_price_html();

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

        <a
            class="fd-button fd-button--primary fd-product-card__button"
            href="<?php
                echo esc_url(
                    $url
                );
            ?>"
        >
            Ver apostila
        </a>
    </div>
</article>