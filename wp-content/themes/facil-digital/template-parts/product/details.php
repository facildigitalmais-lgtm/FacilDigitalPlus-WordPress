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
    $product
        ->get_description();

$sku =
    $product
        ->get_sku();

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
                    'As informacoes detalhadas deste material serao publicadas no cadastro do produto.',
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
                'Informacoes',
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

            <div>
                <dt>
                    <?php
                    echo esc_html__(
                        'Tipo',
                        'facil-digital'
                    );
                    ?>
                </dt>

                <dd>
                    <?php
                    echo esc_html(
                        $product->is_virtual()
                            ? __(
                                'Produto virtual',
                                'facil-digital'
                            )
                            : __(
                                'Produto WooCommerce',
                                'facil-digital'
                            )
                    );
                    ?>
                </dd>
            </div>

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
                    <dt>
                        SKU
                    </dt>

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
