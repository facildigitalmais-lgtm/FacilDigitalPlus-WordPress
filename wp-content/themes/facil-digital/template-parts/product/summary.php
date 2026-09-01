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
    || !$product instanceof WC_Product
) {
    return;
}

$GLOBALS['product'] =
    $product;

$productId =
    (int) $product->get_id();

$price =
    $product->get_price_html();

$shortDescription =
    $product->get_short_description();

$contests =
    function_exists(
        'fd_theme_product_contest_names'
    )
        ? fd_theme_product_contest_names(
            $productId
        )
        : [];

$position = '';
$board = '';

if (
    function_exists(
        'fd_theme_core_product_metadata_available'
    )
    && fd_theme_core_product_metadata_available()
) {
    $position =
        fd_theme_product_meta(
            $productId,
            \FacilDigital\Core\Products\ProductMetadata::POSITION_NAME
        );

    $board =
        fd_theme_product_meta(
            $productId,
            \FacilDigital\Core\Products\ProductMetadata::BOARD
        );
}

$summaryFacts = [];

if ($contests !== []) {
    $summaryFacts[] = [
        'label' =>
            'Concurso',

        'value' =>
            implode(
                ', ',
                $contests
            ),
    ];
}

if ($position !== '') {
    $summaryFacts[] = [
        'label' =>
            'Cargo',

        'value' =>
            $position,
    ];
}

if ($board !== '') {
    $summaryFacts[] = [
        'label' =>
            'Banca',

        'value' =>
            $board,
    ];
}

$coverContext =
    implode(
        ' • ',
        array_column(
            $summaryFacts,
            'value'
        )
    );

$hasProductImage =
    (int) $product->get_image_id() > 0
    || $product->get_gallery_image_ids() !== [];

$hasSimulations = false;

if (
    function_exists(
        'fd_theme_core_product_metadata_available'
    )
    && fd_theme_core_product_metadata_available()
) {
    $hasSimulations =
        \FacilDigital\Core\Products\ProductMetadata::get(
            $productId,
            \FacilDigital\Core\Products\ProductMetadata::HAS_SIMULATIONS,
            'no'
        ) === 'yes';
}

?>

<section class="fd-product-primary">
    <div class="fd-product-primary__media">
        <?php
        if ($product->is_on_sale()) {
            woocommerce_show_product_sale_flash();
        }

        if ($hasProductImage) {
            woocommerce_show_product_images();
        } else {
            ?>
            <div
                class="fd-product-cover-fallback"
                aria-hidden="true"
            >
                <span class="fd-product-cover-fallback__brand">
                    Fácil Digital+
                </span>

                <div class="fd-product-cover-fallback__content">
                    <span>
                        Apostila digital
                    </span>

                    <strong>
                        <?php
                        echo esc_html(
                            $product->get_name()
                        );
                        ?>
                    </strong>

                    <?php if ($coverContext !== '') : ?>
                        <small>
                            <?php
                            echo esc_html(
                                $coverContext
                            );
                            ?>
                        </small>
                    <?php endif; ?>
                </div>

                <span class="fd-product-cover-fallback__footer">
                    Estude. Pratique. Evolua.
                </span>
            </div>
            <?php
        }
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

        <?php if ($summaryFacts !== []) : ?>
            <dl
                class="fd-product-context"
                aria-label="<?php
                    echo esc_attr__(
                        'Informações principais da apostila',
                        'facil-digital'
                    );
                ?>"
            >
                <?php foreach ($summaryFacts as $fact) : ?>
                    <div>
                        <dt>
                            <?php
                            echo esc_html(
                                $fact['label']
                            );
                            ?>
                        </dt>

                        <dd>
                            <?php
                            echo esc_html(
                                $fact['value']
                            );
                            ?>
                        </dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>

        <nav
            class="fd-product-jump-links"
            aria-label="<?php
                echo esc_attr__(
                    'Navegação desta apostila',
                    'facil-digital'
                );
            ?>"
        >
            <a href="#descricao">
                Sobre a apostila
            </a>

            <?php if ($hasSimulations) : ?>
                <a href="#simulados">
                    Simulados
                </a>
            <?php endif; ?>

            <a href="#duvidas">
                Dúvidas
            </a>
        </nav>

        <span class="fd-product-price-label">
            <?php
            echo esc_html__(
                'Valor da apostila',
                'facil-digital'
            );
            ?>
        </span>

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

        <?php if ($shortDescription !== '') : ?>
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
                    echo esc_attr(
                        $product->is_in_stock()
                            ? 'fd-product-stock__dot fd-product-stock__dot--available'
                            : 'fd-product-stock__dot fd-product-stock__dot--unavailable'
                    );
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
                        'Material digital vinculado à sua conta',
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
                        'Acesso pela área do aluno após a liberação',
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
                        'Finalização pelo checkout da loja',
                        'facil-digital'
                    );
                    ?>
                </span>
            </li>
        </ul>
    </div>
</section>
