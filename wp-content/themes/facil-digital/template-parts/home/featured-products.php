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
    $products =
        wc_get_products(
            [
                'status'  => 'publish',
                'limit'   => 6,
                'orderby' => 'date',
                'order'   => 'DESC',
            ]
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
                    'Escolha sua proxima apostila',

                'text' =>
                    'Materiais digitais organizados para tornar sua preparacao mais simples e objetiva.',
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
                        'Novas apostilas em preparacao',

                    'text' =>
                        'O catalogo esta sendo preparado. Em breve os materiais publicados aparecerao automaticamente aqui.',
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
                Explorar catalogo completo
            </a>
        </div>
    </div>
</section>