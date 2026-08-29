<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Identifica uma busca especificamente limitada
 * ao post type product.
 */
function fd_theme_is_product_search(): bool
{
    if (!is_search()) {
        return false;
    }

    $postType =
        get_query_var(
            'post_type'
        );

    if (is_array($postType)) {
        return in_array(
            'product',
            $postType,
            true
        );
    }

    return $postType === 'product';
}

/**
 * Prioriza search.php nas buscas de produtos
 * tratadas pelo template loader do WooCommerce.
 *
 * @param array<int, string> $templates
 * @return array<int, string>
 */
function fd_theme_product_search_template_files(
    array $templates,
    string $defaultFile
): array {
    if (
        !fd_theme_is_product_search()
        || $defaultFile !== 'archive-product.php'
    ) {
        return $templates;
    }

    array_unshift(
        $templates,
        'search.php'
    );

    return array_values(
        array_unique(
            $templates
        )
    );
}

add_filter(
    'woocommerce_template_loader_files',
    'fd_theme_product_search_template_files',
    10,
    2
);

function fd_theme_woocommerce_wrapper_start(): void
{
    echo '<section class="fd-storefront fd-section">';
    echo '<div class="fd-container">';
}

function fd_theme_woocommerce_wrapper_end(): void
{
    echo '</div>';
    echo '</section>';
}

function fd_theme_prepare_woocommerce_catalog(): void
{
    if (
        !function_exists(
            'is_woocommerce'
        )
        || !is_woocommerce()
    ) {
        return;
    }

    remove_action(
        'woocommerce_before_main_content',
        'woocommerce_output_content_wrapper',
        10
    );

    remove_action(
        'woocommerce_after_main_content',
        'woocommerce_output_content_wrapper_end',
        10
    );

    add_action(
        'woocommerce_before_main_content',
        'fd_theme_woocommerce_wrapper_start',
        10
    );

    add_action(
        'woocommerce_after_main_content',
        'fd_theme_woocommerce_wrapper_end',
        10
    );

    if (
        is_shop()
        || is_product_taxonomy()
    ) {
        remove_action(
            'woocommerce_before_shop_loop',
            'woocommerce_result_count',
            20
        );

        remove_action(
            'woocommerce_before_shop_loop',
            'woocommerce_catalog_ordering',
            30
        );
    }
}

add_action(
    'wp',
    'fd_theme_prepare_woocommerce_catalog',
    5
);

function fd_theme_catalog_products_per_page(
    int $count
): int {
    return 12;
}

add_filter(
    'loop_shop_per_page',
    'fd_theme_catalog_products_per_page',
    20
);

function fd_theme_catalog_columns(
    int $columns
): int {
    return 3;
}

add_filter(
    'loop_shop_columns',
    'fd_theme_catalog_columns',
    20
);

function fd_theme_catalog_orderby_options(): array
{
    return [
        'menu_order' =>
            __(
                'Ordenacao padrao',
                'facil-digital'
            ),

        'date' =>
            __(
                'Mais recentes',
                'facil-digital'
            ),

        'price' =>
            __(
                'Menor preco',
                'facil-digital'
            ),

        'price-desc' =>
            __(
                'Maior preco',
                'facil-digital'
            ),
    ];
}

function fd_theme_catalog_current_orderby(): string
{
    $orderby = '';

    if (
        isset($_GET['orderby'])
        && is_string(
            $_GET['orderby']
        )
    ) {
        $orderby =
            wc_clean(
                wp_unslash(
                    $_GET['orderby']
                )
            );
    }

    $options =
        fd_theme_catalog_orderby_options();

    if (
        !array_key_exists(
            $orderby,
            $options
        )
    ) {
        return 'menu_order';
    }

    return $orderby;
}

function fd_theme_catalog_title(): string
{
    if (
        function_exists(
            'woocommerce_page_title'
        )
    ) {
        $title =
            woocommerce_page_title(
                false
            );

        if (
            is_string($title)
            && $title !== ''
        ) {
            return $title;
        }
    }

    return __(
        'Apostilas',
        'facil-digital'
    );
}

function fd_theme_catalog_description(): string
{
    if (
        function_exists(
            'is_product_taxonomy'
        )
        && is_product_taxonomy()
    ) {
        $description =
            term_description();

        if (
            is_string($description)
            && $description !== ''
        ) {
            return wp_strip_all_tags(
                $description
            );
        }
    }

    return __(
        'Encontre materiais digitais para sua preparacao e escolha a apostila adequada ao seu objetivo.',
        'facil-digital'
    );
}
