<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$isProductSearch =
    isset($_GET['post_type'])
    && is_string(
        $_GET['post_type']
    )
    && sanitize_key(
        wp_unslash(
            $_GET['post_type']
        )
    ) === 'product';

?>

<section class="fd-search-page fd-section">
    <div class="fd-container">
        <?php
        get_template_part(
            'template-parts/search/header',
            null,
            [
                'product_search' =>
                    $isProductSearch,
            ]
        );
        ?>

        <?php if (have_posts()) : ?>
            <div class="fd-search-results">
                <?php
                while (have_posts()) :
                    the_post();

                    if (
                        get_post_type()
                        === 'product'
                        && function_exists(
                            'wc_get_product'
                        )
                    ) {
                        $product =
                            wc_get_product(
                                get_the_ID()
                            );

                        if ($product) {
                            get_template_part(
                                'template-parts/components/product-card',
                                null,
                                [
                                    'product' =>
                                        $product,
                                ]
                            );
                        }

                        continue;
                    }

                    get_template_part(
                        'template-parts/search/result-card'
                    );

                endwhile;
                ?>
            </div>

            <nav
                class="fd-search-pagination"
                aria-label="<?php
                    echo esc_attr__(
                        'Paginação dos resultados',
                        'facil-digital'
                    );
                ?>"
            >
                <?php
                the_posts_pagination(
                    [
                        'mid_size'  => 1,
                        'prev_text' =>
                            __(
                                'Anterior',
                                'facil-digital'
                            ),
                        'next_text' =>
                            __(
                                'Próxima',
                                'facil-digital'
                            ),
                    ]
                );
                ?>
            </nav>
        <?php else : ?>
            <?php
            get_template_part(
                'template-parts/components/empty-state',
                null,
                [
                    'title' =>
                        'Nenhum resultado encontrado',

                    'text' =>
                        'Tente uma busca diferente ou explore o catálogo completo de apostilas.',
                ]
            );
            ?>

            <div class="fd-search-empty__action">
                <a
                    class="fd-button fd-button--primary"
                    href="<?php
                        echo esc_url(
                            fd_theme_get_shop_url()
                        );
                    ?>"
                >
                    <?php
                    echo esc_html__(
                        'Ver apostilas',
                        'facil-digital'
                    );
                    ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php

get_footer();
