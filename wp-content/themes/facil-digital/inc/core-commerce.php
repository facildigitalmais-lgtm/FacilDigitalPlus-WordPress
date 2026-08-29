<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function fd_theme_core_product_metadata_available(): bool
{
    return class_exists(
        \FacilDigital\Core\Products\ProductMetadata::class
    );
}

function fd_theme_product_meta(
    int $productId,
    string $key,
    string $default = ''
): string {
    if (!fd_theme_core_product_metadata_available()) {
        return $default;
    }

    return \FacilDigital\Core\Products\ProductMetadata::get(
        $productId,
        $key,
        $default
    );
}

/**
 * @return list<string>
 */
function fd_theme_product_contest_names(
    int $productId
): array {
    if (
        !class_exists(
            \FacilDigital\Core\Contests\ContestModule::class
        )
    ) {
        return [];
    }

    $terms = wp_get_post_terms(
        $productId,
        \FacilDigital\Core\Contests\ContestModule::TAXONOMY,
        [
            'fields' => 'names',
        ]
    );

    if (
        is_wp_error($terms)
        || !is_array($terms)
    ) {
        return [];
    }

    return array_values(
        array_map(
            'strval',
            $terms
        )
    );
}

/**
 * @return list<array{label:string,value:string}>
 */
function fd_theme_product_fact_rows(
    int $productId
): array {
    if (!fd_theme_core_product_metadata_available()) {
        return [];
    }

    $meta =
        \FacilDigital\Core\Products\ProductMetadata::class;

    $rows = [];

    $contests =
        fd_theme_product_contest_names(
            $productId
        );

    if ($contests !== []) {
        $rows[] = [
            'label' => __('Concurso', 'facil-digital'),
            'value' => implode(', ', $contests),
        ];
    }

    $definitions = [
        [
            'label' => __('Cargo', 'facil-digital'),
            'key' => $meta::POSITION_NAME,
        ],
        [
            'label' => __('Banca', 'facil-digital'),
            'key' => $meta::BOARD,
        ],
        [
            'label' => __('Ano', 'facil-digital'),
            'key' => $meta::EXAM_YEAR,
        ],
        [
            'label' => __('Páginas', 'facil-digital'),
            'key' => $meta::PAGE_COUNT,
        ],
        [
            'label' => __('Versão', 'facil-digital'),
            'key' => $meta::MATERIAL_VERSION,
        ],
    ];

    foreach ($definitions as $definition) {
        $value = fd_theme_product_meta(
            $productId,
            $definition['key']
        );

        if ($value === '') {
            continue;
        }

        $rows[] = [
            'label' => $definition['label'],
            'value' => $value,
        ];
    }

    return $rows;
}

function fd_theme_catalog_current_contest(): string
{
    if (
        is_tax('fd_concurso')
        && ($term = get_queried_object())
        instanceof WP_Term
    ) {
        return $term->slug;
    }

    if (
        !isset($_GET['concurso'])
        || !is_string($_GET['concurso'])
    ) {
        return '';
    }

    return sanitize_title(
        wp_unslash(
            $_GET['concurso']
        )
    );
}

/**
 * @return list<WP_Term>
 */
function fd_theme_catalog_contests(): array
{
    if (!taxonomy_exists('fd_concurso')) {
        return [];
    }

    $terms = get_terms([
        'taxonomy' => 'fd_concurso',
        'hide_empty' => true,
        'orderby' => 'name',
        'order' => 'ASC',
    ]);

    if (
        is_wp_error($terms)
        || !is_array($terms)
    ) {
        return [];
    }

    return array_values($terms);
}

function fd_theme_filter_catalog_by_contest(
    WP_Query $query
): void {
    $slug =
        fd_theme_catalog_current_contest();

    if (
        $slug === ''
        || is_tax('fd_concurso')
    ) {
        return;
    }

    $term = get_term_by(
        'slug',
        $slug,
        'fd_concurso'
    );

    if (!$term instanceof WP_Term) {
        return;
    }

    $taxQuery =
        $query->get('tax_query');

    if (!is_array($taxQuery)) {
        $taxQuery = [];
    }

    $taxQuery[] = [
        'taxonomy' => 'fd_concurso',
        'field' => 'slug',
        'terms' => [$slug],
    ];

    $query->set(
        'tax_query',
        $taxQuery
    );
}

add_action(
    'woocommerce_product_query',
    'fd_theme_filter_catalog_by_contest',
    20
);

function fd_theme_catalog_product_context(): void
{
    global $product;

    if (
        !$product instanceof WC_Product
        || !fd_theme_core_product_metadata_available()
    ) {
        return;
    }

    $productId =
        (int) $product->get_id();

    if (
        !\FacilDigital\Core\Products\ProductMetadata::isApostila(
            $productId
        )
    ) {
        return;
    }

    $parts =
        fd_theme_product_contest_names(
            $productId
        );

    $position =
        fd_theme_product_meta(
            $productId,
            \FacilDigital\Core\Products\ProductMetadata::POSITION_NAME
        );

    if ($position !== '') {
        $parts[] = $position;
    }

    if ($parts === []) {
        return;
    }

    echo '<p class="fd-catalog-card__meta">';
    echo esc_html(
        implode(' • ', $parts)
    );
    echo '</p>';
}

add_action(
    'woocommerce_after_shop_loop_item_title',
    'fd_theme_catalog_product_context',
    8
);

function fd_theme_enqueue_commerce_assets(): void
{
    if (
        !function_exists('is_cart')
        || (
            !is_cart()
            && !is_checkout()
            && !is_account_page()
        )
    ) {
        return;
    }

    fd_theme_enqueue_style_file(
        'fd-commerce',
        '/assets/css/commerce.css',
        [
            'fd-components',
        ]
    );
}

add_action(
    'wp_enqueue_scripts',
    'fd_theme_enqueue_commerce_assets',
    25
);
