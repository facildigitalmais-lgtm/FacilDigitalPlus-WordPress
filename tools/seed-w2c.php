<?php

if (!defined('ABSPATH')) {
    exit;
}

if (
    wp_get_environment_type()
    !== 'development'
) {
    throw new RuntimeException(
        'W2C seed permitido somente em development.'
    );
}

if (
    !class_exists(
        'WC_Product_Simple'
    )
) {
    throw new RuntimeException(
        'WooCommerce nao esta disponivel.'
    );
}

$products = [
    [
        'key' =>
            'transpetro-seguranca',

        'name' =>
            'Apostila Transpetro - Seguranca',

        'slug' =>
            'fd-w2c-transpetro-seguranca',

        'sku' =>
            'FD-W2C-SEG',

        'price' =>
            '14.50',

        'short' =>
            'Material digital demonstrativo para preparacao ao cargo de Seguranca da Transpetro.',

        'description' =>
            '<p>Produto temporario da fase W2C criado exclusivamente para validar catalogo, busca, pagina individual e carrinho no ambiente de desenvolvimento.</p><p>O conteudo comercial definitivo sera administrado posteriormente pelo WooCommerce e pelos metadados da plataforma Facil Digital+.</p>',
    ],

    [
        'key' =>
            'transpetro-dutos',

        'name' =>
            'Apostila Transpetro - Dutos e Terminais',

        'slug' =>
            'fd-w2c-transpetro-dutos-terminais',

        'sku' =>
            'FD-W2C-DUT',

        'price' =>
            '14.50',

        'short' =>
            'Material digital demonstrativo para Transpetro Junior Nivel Medio Dutos e Terminais.',

        'description' =>
            '<p>Produto temporario da fase W2C para validacao da experiencia comercial da Facil Digital+.</p><p>Nao representa ainda o fluxo definitivo de entitlement ou entrega de PDF protegido.</p>',
    ],

    [
        'key' =>
            'transpetro-contabilidade',

        'name' =>
            'Apostila Transpetro - Contabilidade',

        'slug' =>
            'fd-w2c-transpetro-contabilidade',

        'sku' =>
            'FD-W2C-CON',

        'price' =>
            '14.50',

        'short' =>
            'Material digital demonstrativo para Transpetro Junior Nivel Medio Contabilidade.',

        'description' =>
            '<p>Produto temporario utilizado para testar a vitrine WooCommerce da Facil Digital+.</p><p>Os dados especificos de concurso, cargo, banca e simulados serao integrados nas fases posteriores do Core.</p>',
    ],
];

$result = [];

foreach ($products as $definition) {
    $existingIds =
        get_posts(
            [
                'post_type' =>
                    'product',

                'post_status' =>
                    [
                        'publish',
                        'draft',
                        'private',
                    ],

                'posts_per_page' =>
                    1,

                'fields' =>
                    'ids',

                'meta_key' =>
                    '_fd_w2c_seed_key',

                'meta_value' =>
                    $definition['key'],

                'no_found_rows' =>
                    true,
            ]
        );

    $product = null;

    if ($existingIds !== []) {
        $product =
            wc_get_product(
                (int) $existingIds[0]
            );
    }

    if (
        !$product
        || !is_a(
            $product,
            WC_Product_Simple::class
        )
    ) {
        $product =
            new WC_Product_Simple();
    }

    $product->set_name(
        $definition['name']
    );

    $product->set_slug(
        $definition['slug']
    );

    $product->set_status(
        'publish'
    );

    $product->set_catalog_visibility(
        'visible'
    );

    $product->set_sku(
        $definition['sku']
    );

    $product->set_regular_price(
        $definition['price']
    );

    $product->set_price(
        $definition['price']
    );

    $product->set_virtual(
        true
    );

    $product->set_downloadable(
        false
    );

    $product->set_manage_stock(
        false
    );

    $product->set_stock_status(
        'instock'
    );

    $product->set_tax_status(
        'none'
    );

    $product->set_short_description(
        $definition['short']
    );

    $product->set_description(
        $definition['description']
    );

    $productId =
        $product->save();

    update_post_meta(
        $productId,
        '_fd_w2c_seed',
        '1'
    );

    update_post_meta(
        $productId,
        '_fd_w2c_seed_key',
        $definition['key']
    );

    $result[] = [
        'id' =>
            $productId,

        'key' =>
            $definition['key'],

        'name' =>
            $definition['name'],

        'price' =>
            $definition['price'],

        'url' =>
            get_permalink(
                $productId
            ),
    ];
}

echo wp_json_encode(
    [
        'status' =>
            'seeded',

        'environment' =>
            wp_get_environment_type(),

        'products' =>
            $result,
    ],
    JSON_PRETTY_PRINT
);

echo PHP_EOL;

/*
 * P2 COMPATIBILITY: W2C PRODUCTS ARE APOSTILAS
 *
 * O catálogo atual exibe somente produtos
 * reconhecidos pelo Fácil Digital+ Core
 * como apostilas.
 *
 * Estes são fixtures temporários de catálogo,
 * portanto também devem respeitar o contrato
 * comercial atual.
 */
$fdW2cApostilaMetaKey =
    class_exists(
        '\FacilDigital\Core\Products\ProductMetadata'
    )
        ? \FacilDigital\Core\Products\ProductMetadata::IS_APOSTILA
        : '_fd_is_apostila';

$fdW2cSeedProductIds =
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

            'meta_key' =>
                '_fd_w2c_seed',

            'meta_value' =>
                '1',
        ]
    );

foreach (
    $fdW2cSeedProductIds
    as $fdW2cSeedProductId
) {
    update_post_meta(
        (int) $fdW2cSeedProductId,
        $fdW2cApostilaMetaKey,
        'yes'
    );
}

unset(
    $fdW2cApostilaMetaKey,
    $fdW2cSeedProductIds,
    $fdW2cSeedProductId
);
