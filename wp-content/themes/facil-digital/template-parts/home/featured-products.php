<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$products = [];

if (
    function_exists(
        'wc_get_products'
    )
) {
    $isApostila =
        static function ($product): bool {
            if (
                !$product
                || !is_a(
                    $product,
                    WC_Product::class
                )
            ) {
                return false;
            }

            if (
                fd_theme_core_product_metadata_available()
            ) {
                return
                    \FacilDigital\Core\Products\ProductMetadata::isApostila(
                        (int) $product->get_id()
                    );
            }

            return true;
        };

    /*
     * WooCommerce e a fonte oficial para definir
     * produtos em destaque. A Home apenas aplica
     * a regra adicional de exibir apostilas.
     */
    $featuredProducts =
        wc_get_products(
            [
                'status' =>
                    'publish',

                'featured' =>
                    true,

                'limit' =>
                    12,

                'orderby' =>
                    'date',

                'order' =>
                    'DESC',
            ]
        );

    $featuredProducts =
        array_values(
            array_filter(
                $featuredProducts,
                $isApostila
            )
        );

    /*
     * Completa a vitrine com materiais recentes
     * quando houver menos de seis destaques.
     */
    $recentProducts =
        wc_get_products(
            [
                'status' =>
                    'publish',

                'limit' =>
                    24,

                'orderby' =>
                    'date',

                'order' =>
                    'DESC',
            ]
        );

    $recentProducts =
        array_values(
            array_filter(
                $recentProducts,
                $isApostila
            )
        );

    $indexedProducts = [];

    foreach (
        array_merge(
            $featuredProducts,
            $recentProducts
        )
        as $product
    ) {
        if (
            !$product
            instanceof WC_Product
        ) {
            continue;
        }

        $indexedProducts[
            (int) $product->get_id()
        ] = $product;
    }

    $products =
        array_slice(
            array_values(
                $indexedProducts
            ),
            0,
            6
        );
}

?>

<section
    class="fd-section fd-home-featured"
    id="apostilas"
>
    <div class="fd-container">
        <?php
        get_template_part(
            'template-parts/components/section-heading',
            null,
            [
                'eyebrow' =>
                    'Materiais em destaque',

                'title' =>
                    'Escolha sua próxima apostila',

                'text' =>
                    'Materiais digitais organizados para tornar sua preparação mais simples e objetiva.',
            ]
        );
        ?>

        <?php if ($products !== []) : ?>
            <div class="fd-product-grid">
                <?php
                foreach ($products as $product) {
                    get_template_part(
                        'template-parts/components/product-card',
                        null,
                        [
                            'product' =>
                                $product,
                        ]
                    );
                }
                ?>
            </div>
        <?php else : ?>
            <?php
            get_template_part(
                'template-parts/components/empty-state',
                null,
                [
                    'title' =>
                        'Novas apostilas em preparação',

                    'text' =>
                        'O catálogo esta sendo preparado. Em breve os materiais publicados aparecerao automaticamente aqui.',
                ]
            );
            ?>
        <?php endif; ?>

        <div class="fd-home-featured__footer">
            <a
                class="fd-button fd-button--secondary"
                href="<?php
                    echo esc_url(
                        fd_theme_get_shop_url()
                    );
                ?>"
            >
                Explorar catálogo completo
            </a>
        </div>
    </div>
</section>
