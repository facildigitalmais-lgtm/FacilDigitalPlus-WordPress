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

/**
 * @return list<WP_Term>
 */
function fd_theme_home_featured_contests(
    int $limit = 6
): array {
    if (
        !class_exists(
            \FacilDigital\Core\Contests\ContestModule::class
        )
    ) {
        return [];
    }

    $contestModule =
        \FacilDigital\Core\Contests\ContestModule::class;

    if (
        !taxonomy_exists(
            $contestModule::TAXONOMY
        )
    ) {
        return [];
    }

    $terms =
        get_terms(
            [
                'taxonomy' =>
                    $contestModule::TAXONOMY,

                'hide_empty' =>
                    true,

                'number' =>
                    max(1, $limit),

                'orderby' =>
                    'name',

                'order' =>
                    'ASC',

                'meta_query' => [
                    [
                        'key' =>
                            $contestModule::HOME_FEATURED_META,

                        'value' =>
                            'yes',

                        'compare' =>
                            '=',
                    ],
                ],
            ]
        );

    if (
        is_wp_error($terms)
        || !is_array($terms)
    ) {
        return [];
    }

    return array_values(
        array_filter(
            $terms,
            static fn ($term): bool =>
                $term instanceof WP_Term
        )
    );
}

/**
 * Indica se a query atual pertence ao catalogo
 * comercial de produtos/apostilas.
 */
function fd_theme_catalog_filter_context(): bool
{
    if (is_admin()) {
        return false;
    }

    if (
        function_exists('is_shop')
        && is_shop()
    ) {
        return true;
    }

    if (
        function_exists('is_product_taxonomy')
        && is_product_taxonomy()
    ) {
        return true;
    }

    return
        function_exists(
            'fd_theme_is_product_search'
        )
        && fd_theme_is_product_search();
}

function fd_theme_catalog_current_text_filter(
    string $parameter
): string {
    if (
        !isset($_GET[$parameter])
        || !is_string($_GET[$parameter])
    ) {
        return '';
    }

    return sanitize_text_field(
        wp_unslash(
            $_GET[$parameter]
        )
    );
}

function fd_theme_catalog_current_search(): string
{
    return fd_theme_catalog_current_text_filter(
        'busca'
    );
}

function fd_theme_catalog_has_active_filters(): bool
{
    return
        fd_theme_catalog_current_search() !== ''
        || fd_theme_catalog_current_contest() !== ''
        || fd_theme_catalog_current_board() !== ''
        || fd_theme_catalog_current_position() !== ''
        || fd_theme_catalog_current_price(
            'min_price'
        ) !== ''
        || fd_theme_catalog_current_price(
            'max_price'
        ) !== '';
}

function fd_theme_catalog_current_board(): string
{
    return fd_theme_catalog_current_text_filter(
        'banca'
    );
}

function fd_theme_catalog_current_position(): string
{
    return fd_theme_catalog_current_text_filter(
        'cargo'
    );
}

function fd_theme_catalog_current_price(
    string $parameter
): string {
    if (
        !isset($_GET[$parameter])
        || !is_string($_GET[$parameter])
    ) {
        return '';
    }

    $raw =
        wc_clean(
            wp_unslash(
                $_GET[$parameter]
            )
        );

    if ($raw === '') {
        return '';
    }

    $value =
        wc_format_decimal(
            $raw,
            2
        );

    if (
        $value === ''
        || (float) $value < 0
    ) {
        return '';
    }

    return $value;
}

/**
 * @return list<string>
 */
function fd_theme_catalog_meta_options(
    string $metaKey
): array {
    static $cache = [];

    if (isset($cache[$metaKey])) {
        return $cache[$metaKey];
    }

    if (
        !fd_theme_core_product_metadata_available()
    ) {
        $cache[$metaKey] = [];

        return [];
    }

    $productIds =
        get_posts(
            [
                'post_type' =>
                    'product',

                'post_status' =>
                    'publish',

                'posts_per_page' =>
                    -1,

                'fields' =>
                    'ids',

                'no_found_rows' =>
                    true,

                'meta_query' => [
                    [
                        'key' =>
                            \FacilDigital\Core\Products\ProductMetadata::IS_APOSTILA,

                        'value' =>
                            'yes',

                        'compare' =>
                            '=',
                    ],
                ],
            ]
        );

    $values = [];

    foreach ($productIds as $productId) {
        $value =
            get_post_meta(
                (int) $productId,
                $metaKey,
                true
            );

        if (!is_scalar($value)) {
            continue;
        }

        $value =
            trim(
                (string) $value
            );

        if ($value === '') {
            continue;
        }

        $values[$value] =
            $value;
    }

    natcasesort($values);

    $cache[$metaKey] =
        array_values($values);

    return $cache[$metaKey];
}

/**
 * @return list<string>
 */
function fd_theme_catalog_boards(): array
{
    if (
        !fd_theme_core_product_metadata_available()
    ) {
        return [];
    }

    return fd_theme_catalog_meta_options(
        \FacilDigital\Core\Products\ProductMetadata::BOARD
    );
}

/**
 * @return list<string>
 */
function fd_theme_catalog_positions(): array
{
    if (
        !fd_theme_core_product_metadata_available()
    ) {
        return [];
    }

    return fd_theme_catalog_meta_options(
        \FacilDigital\Core\Products\ProductMetadata::POSITION_NAME
    );
}

/**
 * @param array<string, mixed> $clause
 */
function fd_theme_catalog_add_meta_clause(
    WP_Query $query,
    array $clause
): void {
    $metaQuery =
        $query->get(
            'meta_query'
        );

    if (!is_array($metaQuery)) {
        $metaQuery = [];
    }

    $metaQuery[] =
        $clause;

    $query->set(
        'meta_query',
        $metaQuery
    );
}

/**
 * Mantem o catalogo comercial restrito
 * aos produtos reconhecidos pelo Core
 * como apostilas.
 */
function fd_theme_filter_catalog_to_apostilas(
    WP_Query $query
): void {
    if (
        !fd_theme_catalog_filter_context()
        || !fd_theme_core_product_metadata_available()
    ) {
        return;
    }

    fd_theme_catalog_add_meta_clause(
        $query,
        [
            'key' =>
                \FacilDigital\Core\Products\ProductMetadata::IS_APOSTILA,

            'value' =>
                'yes',

            'compare' =>
                '=',
        ]
    );
}

add_action(
    'woocommerce_product_query',
    'fd_theme_filter_catalog_to_apostilas',
    10
);

/**
 * Aplica filtros comerciais baseados
 * nos metadados oficiais das apostilas.
 */
/**
 * Retorna apostilas localizadas por:
 *
 * - titulo/conteudo;
 * - banca;
 * - cargo;
 * - concurso.
 *
 * @return list<int>
 */
function fd_theme_catalog_search_product_ids(
    string $search
): array {
    $search =
        trim($search);

    if (
        $search === ''
        || !fd_theme_core_product_metadata_available()
    ) {
        return [];
    }

    $metadata =
        \FacilDigital\Core\Products\ProductMetadata::class;

    $contestModule =
        \FacilDigital\Core\Contests\ContestModule::class;

    $apostilaClause = [
        'key' =>
            $metadata::IS_APOSTILA,

        'value' =>
            'yes',

        'compare' =>
            '=',
    ];

    $ids = [];

    /*
     * 1. Busca textual nativa:
     * titulo, resumo e conteudo.
     */
    $textIds =
        get_posts(
            [
                'post_type' =>
                    'product',

                'post_status' =>
                    'publish',

                'posts_per_page' =>
                    -1,

                'fields' =>
                    'ids',

                'no_found_rows' =>
                    true,

                's' =>
                    $search,

                'meta_query' => [
                    $apostilaClause,
                ],
            ]
        );

    foreach ($textIds as $productId) {
        $ids[] =
            (int) $productId;
    }

    /*
     * 2. Busca nos metadados comerciais:
     * banca e cargo.
     */
    $metaIds =
        get_posts(
            [
                'post_type' =>
                    'product',

                'post_status' =>
                    'publish',

                'posts_per_page' =>
                    -1,

                'fields' =>
                    'ids',

                'no_found_rows' =>
                    true,

                'meta_query' => [
                    'relation' =>
                        'AND',

                    $apostilaClause,

                    [
                        'relation' =>
                            'OR',

                        [
                            'key' =>
                                $metadata::BOARD,

                            'value' =>
                                $search,

                            'compare' =>
                                'LIKE',
                        ],

                        [
                            'key' =>
                                $metadata::POSITION_NAME,

                            'value' =>
                                $search,

                            'compare' =>
                                'LIKE',
                        ],
                    ],
                ],
            ]
        );

    foreach ($metaIds as $productId) {
        $ids[] =
            (int) $productId;
    }

    /*
     * 3. Busca pelo nome dos concursos.
     */
    if (
        class_exists($contestModule)
        && taxonomy_exists(
            $contestModule::TAXONOMY
        )
    ) {
        $contestTerms =
            get_terms(
                [
                    'taxonomy' =>
                        $contestModule::TAXONOMY,

                    'hide_empty' =>
                        false,

                    'search' =>
                        $search,

                    'fields' =>
                        'ids',
                ]
            );

        if (
            !is_wp_error($contestTerms)
            && is_array($contestTerms)
            && $contestTerms !== []
        ) {
            $contestProductIds =
                get_objects_in_term(
                    array_map(
                        'intval',
                        $contestTerms
                    ),
                    $contestModule::TAXONOMY
                );

            if (
                !is_wp_error(
                    $contestProductIds
                )
            ) {
                foreach (
                    $contestProductIds
                    as $productId
                ) {
                    $ids[] =
                        (int) $productId;
                }
            }
        }
    }

    $ids =
        array_values(
            array_unique(
                array_filter(
                    array_map(
                        'absint',
                        $ids
                    )
                )
            )
        );

    return $ids;
}

/**
 * Mantem a pesquisa dentro do arquivo
 * comercial /apostilas/.
 */
function fd_theme_filter_catalog_by_search(
    WP_Query $query
): void {
    if (
        !fd_theme_catalog_filter_context()
    ) {
        return;
    }

    $search =
        fd_theme_catalog_current_search();

    if ($search === '') {
        return;
    }

    $ids =
        fd_theme_catalog_search_product_ids(
            $search
        );

    /*
     * post__in vazio nao significa "nenhum"
     * para o WP_Query. Portanto usamos [0].
     */
    if ($ids === []) {
        $query->set(
            'post__in',
            [0]
        );

        return;
    }

    $existing =
        $query->get(
            'post__in'
        );

    if (
        is_array($existing)
        && $existing !== []
    ) {
        $ids =
            array_values(
                array_intersect(
                    array_map(
                        'absint',
                        $existing
                    ),
                    $ids
                )
            );

        if ($ids === []) {
            $ids = [0];
        }
    }

    $query->set(
        'post__in',
        $ids
    );
}

add_action(
    'woocommerce_product_query',
    'fd_theme_filter_catalog_by_search',
    25
);

function fd_theme_filter_catalog_by_metadata(
    WP_Query $query
): void {
    if (
        !fd_theme_catalog_filter_context()
        || !fd_theme_core_product_metadata_available()
    ) {
        return;
    }

    $board =
        fd_theme_catalog_current_board();

    if ($board !== '') {
        fd_theme_catalog_add_meta_clause(
            $query,
            [
                'key' =>
                    \FacilDigital\Core\Products\ProductMetadata::BOARD,

                'value' =>
                    $board,

                'compare' =>
                    '=',
            ]
        );
    }

    $position =
        fd_theme_catalog_current_position();

    if ($position !== '') {
        fd_theme_catalog_add_meta_clause(
            $query,
            [
                'key' =>
                    \FacilDigital\Core\Products\ProductMetadata::POSITION_NAME,

                'value' =>
                    $position,

                'compare' =>
                    '=',
            ]
        );
    }

    $minimum =
        fd_theme_catalog_current_price(
            'min_price'
        );

    $maximum =
        fd_theme_catalog_current_price(
            'max_price'
        );

    if (
        $minimum !== ''
        && $maximum !== ''
        && (float) $minimum > (float) $maximum
    ) {
        [
            $minimum,
            $maximum,
        ] = [
            $maximum,
            $minimum,
        ];
    }

    if (
        $minimum !== ''
        && $maximum !== ''
    ) {
        fd_theme_catalog_add_meta_clause(
            $query,
            [
                'key' =>
                    '_price',

                'value' => [
                    $minimum,
                    $maximum,
                ],

                'compare' =>
                    'BETWEEN',

                'type' =>
                    'NUMERIC',
            ]
        );

        return;
    }

    if ($minimum !== '') {
        fd_theme_catalog_add_meta_clause(
            $query,
            [
                'key' =>
                    '_price',

                'value' =>
                    $minimum,

                'compare' =>
                    '>=',

                'type' =>
                    'NUMERIC',
            ]
        );
    }

    if ($maximum !== '') {
        fd_theme_catalog_add_meta_clause(
            $query,
            [
                'key' =>
                    '_price',

                'value' =>
                    $maximum,

                'compare' =>
                    '<=',

                'type' =>
                    'NUMERIC',
            ]
        );
    }
}

add_action(
    'woocommerce_product_query',
    'fd_theme_filter_catalog_by_metadata',
    30
);

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
