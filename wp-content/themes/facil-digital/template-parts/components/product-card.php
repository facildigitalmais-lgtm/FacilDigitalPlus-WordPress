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

$productId =
    (int) $product->get_id();

$url =
    get_permalink(
        $productId
    );

if (!is_string($url)) {
    return;
}

$context = [];

if (
    function_exists(
        'fd_theme_product_contest_names'
    )
) {
    $context =
        fd_theme_product_contest_names(
            $productId
        );
}

if (
    function_exists(
        'fd_theme_core_product_metadata_available'
    )
    && fd_theme_core_product_metadata_available()
    && function_exists(
        'fd_theme_product_meta'
    )
) {
    $position =
        fd_theme_product_meta(
            $productId,
            \FacilDigital\Core\Products\ProductMetadata::POSITION_NAME
        );

    if ($position !== '') {
        $context[] =
            $position;
    }
}

$context =
    array_values(
        array_unique(
            array_filter(
                array_map(
                    'strval',
                    $context
                )
            )
        )
    );

$ratingCount =
    (int) $product->get_rating_count();

$averageRating =
    (float) $product->get_average_rating();

?>

<article class="fd-product-card">
    <a
        class="fd-product-card__image-link"
        href="<?php
            echo esc_url(
                $url
            );
        ?>"
        tabindex="-1"
        aria-hidden="true"
    >
        <div class="fd-product-card__image-wrap">
            <?php if ($product->is_on_sale()) : ?>
                <span class="fd-product-card__badge">
                    <?php
                    echo esc_html__(
                        'Oferta',
                        'facil-digital'
                    );
                    ?>
                </span>
            <?php endif; ?>

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
            <?php
            echo esc_html__(
                'Apostila digital',
                'facil-digital'
            );
            ?>
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

        <?php if ($context !== []) : ?>
            <p class="fd-product-card__meta">
                <?php
                echo esc_html(
                    implode(
                        ' • ',
                        $context
                    )
                );
                ?>
            </p>
        <?php endif; ?>

        <?php
        if (
            $ratingCount > 0
            && function_exists(
                'wc_get_rating_html'
            )
        ) :
            ?>
            <div class="fd-product-card__rating">
                <?php
                echo wp_kses_post(
                    wc_get_rating_html(
                        $averageRating,
                        $ratingCount
                    )
                );
                ?>
            </div>
        <?php endif; ?>

        <div class="fd-product-card__price">
            <?php
            $price =
                $product->get_price_html();

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
            <?php
            echo esc_html__(
                'Ver apostila',
                'facil-digital'
            );
            ?>
        </a>
    </div>
</article>
