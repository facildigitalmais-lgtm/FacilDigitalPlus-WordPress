<?php

declare(strict_types=1);

use FacilDigital\Core\Contests\ContestModule;
use FacilDigital\Core\Products\ProductMetadata;

if (!defined('ABSPATH')) {
    exit;
}

if (wp_get_environment_type() !== 'development') {
    throw new RuntimeException(
        'M1 seed permitido somente em development.'
    );
}

if (!class_exists(WC_Product_Simple::class)) {
    throw new RuntimeException(
        'WooCommerce indisponível.'
    );
}

if (!taxonomy_exists(ContestModule::TAXONOMY)) {
    throw new RuntimeException(
        'Taxonomia de concursos não registrada.'
    );
}

$contestDefinitions = [
    [
        'name' => 'Transpetro 2026',
        'slug' => 'fd-m1-transpetro-2026',
        'description' => 'Concurso temporário M1 para testes.',
    ],
    [
        'name' => 'Correios 2026',
        'slug' => 'fd-m1-correios-2026',
        'description' => 'Concurso temporário M1 para testes.',
    ],
];

$contestIds = [];

foreach ($contestDefinitions as $definition) {
    $existing = get_term_by(
        'slug',
        $definition['slug'],
        ContestModule::TAXONOMY
    );

    if ($existing instanceof WP_Term) {
        $contestIds[$definition['slug']] =
            (int) $existing->term_id;
        continue;
    }

    $created = wp_insert_term(
        $definition['name'],
        ContestModule::TAXONOMY,
        [
            'slug' => $definition['slug'],
            'description' => $definition['description'],
        ]
    );

    if (is_wp_error($created)) {
        throw new RuntimeException(
            $created->get_error_message()
        );
    }

    $contestIds[$definition['slug']] =
        (int) $created['term_id'];
}

$products = [
    [
        'key' => 'transpetro-taifeiro',
        'name' => 'M1 Apostila Transpetro - Taifeiro',
        'slug' => 'fd-m1-transpetro-taifeiro',
        'sku' => 'FD-M1-TAI',
        'price' => '14.50',
        'contest' => 'fd-m1-transpetro-2026',
        'position' => 'Taifeiro',
        'board' => 'Cesgranrio',
        'year' => '2026',
        'pages' => '186',
        'version' => '1.0',
        'simulations' => 'yes',
    ],
    [
        'key' => 'transpetro-dutos',
        'name' => 'M1 Apostila Transpetro - Dutos e Terminais',
        'slug' => 'fd-m1-transpetro-dutos',
        'sku' => 'FD-M1-DUT',
        'price' => '14.50',
        'contest' => 'fd-m1-transpetro-2026',
        'position' => 'Dutos e Terminais',
        'board' => 'Cesgranrio',
        'year' => '2026',
        'pages' => '220',
        'version' => '1.0',
        'simulations' => 'yes',
    ],
    [
        'key' => 'correios-atendente',
        'name' => 'M1 Apostila Correios - Atendente',
        'slug' => 'fd-m1-correios-atendente',
        'sku' => 'FD-M1-COR',
        'price' => '19.90',
        'contest' => 'fd-m1-correios-2026',
        'position' => 'Atendente',
        'board' => 'A definir',
        'year' => '2026',
        'pages' => '160',
        'version' => '1.0',
        'simulations' => 'no',
    ],
];

$result = [];

foreach ($products as $definition) {
    $ids = get_posts([
        'post_type' => 'product',
        'post_status' => [
            'publish',
            'draft',
            'private',
        ],
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_key' => '_fd_m1_seed_key',
        'meta_value' => $definition['key'],
        'no_found_rows' => true,
    ]);

    $product = null;

    if ($ids !== []) {
        $product = wc_get_product(
            (int) $ids[0]
        );
    }

    if (!$product instanceof WC_Product_Simple) {
        $product = new WC_Product_Simple();
    }

    $product->set_name($definition['name']);
    $product->set_slug($definition['slug']);
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->set_sku($definition['sku']);
    $product->set_regular_price($definition['price']);
    $product->set_price($definition['price']);
    $product->set_virtual(true);
    $product->set_downloadable(false);
    $product->set_manage_stock(false);
    $product->set_stock_status('instock');
    $product->set_tax_status('none');
    $product->set_short_description(
        'Produto temporário M1 para validar concursos, checkout e entitlement.'
    );
    $product->set_description(
        '<p>Produto temporário do macrolote M1.</p>'
    );

    $productId = $product->save();

    update_post_meta($productId, '_fd_m1_seed', '1');
    update_post_meta(
        $productId,
        '_fd_m1_seed_key',
        $definition['key']
    );

    $metadata = [
        ProductMetadata::IS_APOSTILA => 'yes',
        ProductMetadata::POSITION_NAME => $definition['position'],
        ProductMetadata::BOARD => $definition['board'],
        ProductMetadata::EXAM_YEAR => $definition['year'],
        ProductMetadata::PAGE_COUNT => $definition['pages'],
        ProductMetadata::MATERIAL_VERSION => $definition['version'],
        ProductMetadata::HAS_SIMULATIONS => $definition['simulations'],
        ProductMetadata::DOWNLOAD_LIMIT => '5',
        ProductMetadata::GENERATE_PERSONALIZED_PDF => 'yes',
        ProductMetadata::WATERMARK_ENABLED => 'yes',
        ProductMetadata::PDF_PASSWORD_ENABLED => 'yes',
    ];

    foreach ($metadata as $key => $value) {
        update_post_meta(
            $productId,
            $key,
            $value
        );
    }

    $assigned = wp_set_object_terms(
        $productId,
        [
            $contestIds[$definition['contest']],
        ],
        ContestModule::TAXONOMY,
        false
    );

    if (is_wp_error($assigned)) {
        throw new RuntimeException(
            $assigned->get_error_message()
        );
    }

    $result[] = [
        'id' => $productId,
        'name' => $definition['name'],
        'contest' => $definition['contest'],
        'url' => get_permalink($productId),
    ];
}

echo wp_json_encode(
    [
        'status' => 'seeded',
        'products' => $result,
        'contests' => $contestIds,
    ],
    JSON_PRETTY_PRINT
    | JSON_UNESCAPED_UNICODE
);

echo PHP_EOL;
