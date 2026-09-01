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

$related =
    fd_theme_get_related_product_objects(
        $product,
        3
    );

if ($related === []) {
    return;
}

?>

<section
    class="fd-product-section fd-product-related"
    id="relacionadas"
>
    <div class="fd-product-section__heading">
        <span class="fd-eyebrow">
            <?php
            echo esc_html__(
                'Continue explorando',
                'facil-digital'
            );
            ?>
        </span>

        <h2>
            <?php
            echo esc_html__(
                'Outras apostilas',
                'facil-digital'
            );
            ?>
        </h2>
    </div>

    <div class="fd-product-grid">
        <?php
        foreach ($related as $item) {
            get_template_part(
                'template-parts/components/product-card',
                null,
                [
                    'product' =>
                        $item,
                ]
            );
        }
        ?>
    </div>
</section>
