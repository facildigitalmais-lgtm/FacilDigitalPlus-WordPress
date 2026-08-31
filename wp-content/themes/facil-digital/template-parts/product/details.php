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

$description =
    $product->get_description();

$sku =
    $product->get_sku();

$factRows =
    function_exists(
        'fd_theme_product_fact_rows'
    )
        ? fd_theme_product_fact_rows(
            (int) $product->get_id()
        )
        : [];
?>
<section
    class="fd-product-section fd-product-details"
    id="descricao"
>
    <div class="fd-product-details__content">
        <span class="fd-eyebrow">
            <?php
            echo esc_html__(
                'Detalhes',
                'facil-digital'
            );
            ?>
        </span>

        <h2>
            <?php
            echo esc_html__(
                'Sobre esta apostila',
                'facil-digital'
            );
            ?>
        </h2>

        <?php if ($description !== '') : ?>
            <div class="fd-product-description">
                <?php
                echo wp_kses_post(
                    apply_filters(
                        'the_content',
                        $description
                    )
                );
                ?>
            </div>
        <?php else : ?>
            <p class="fd-product-description">
                <?php
                echo esc_html__(
                    'Esta apostila ainda não possui uma descrição detalhada. Consulte as informações técnicas ao lado.',
                    'facil-digital'
                );
                ?>
            </p>
        <?php endif; ?>
    </div>

    <aside class="fd-product-facts">
        <h3>
            <?php
            echo esc_html__(
                'Informações',
                'facil-digital'
            );
            ?>
        </h3>

        <dl>
            <div>
                <dt>
                    <?php
                    echo esc_html__(
                        'Formato',
                        'facil-digital'
                    );
                    ?>
                </dt>
                <dd>
                    <?php
                    echo esc_html__(
                        'Digital',
                        'facil-digital'
                    );
                    ?>
                </dd>
            </div>

            <?php foreach ($factRows as $row) : ?>
                <div>
                    <dt>
                        <?php
                        echo esc_html(
                            $row['label']
                        );
                        ?>
                    </dt>
                    <dd>
                        <?php
                        echo esc_html(
                            $row['value']
                        );
                        ?>
                    </dd>
                </div>
            <?php endforeach; ?>

            <div>
                <dt>
                    <?php
                    echo esc_html__(
                        'Disponibilidade',
                        'facil-digital'
                    );
                    ?>
                </dt>
                <dd>
                    <?php
                    echo esc_html(
                        fd_theme_product_stock_label(
                            $product
                        )
                    );
                    ?>
                </dd>
            </div>

            <?php if ($sku !== '') : ?>
                <div>
                    <dt>SKU</dt>
                    <dd>
                        <?php
                        echo esc_html(
                            $sku
                        );
                        ?>
                    </dd>
                </div>
            <?php endif; ?>
        </dl>
    </aside>
</section>
