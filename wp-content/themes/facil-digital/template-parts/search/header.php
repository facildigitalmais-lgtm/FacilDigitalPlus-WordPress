<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

global $wp_query;

$count =
    $wp_query instanceof WP_Query
        ? (int) $wp_query
            ->found_posts
        : 0;

$term =
    get_search_query();

$isProductSearch =
    !empty(
        $args[
            'product_search'
        ]
    );

?>

<header class="fd-search-header">
    <span class="fd-eyebrow">
        <?php
        echo esc_html(
            $isProductSearch
                ? __(
                    'Busca de apostilas',
                    'facil-digital'
                )
                : __(
                    'Busca',
                    'facil-digital'
                )
        );
        ?>
    </span>

    <h1>
        <?php
        printf(
            esc_html__(
                'Resultados para "%s"',
                'facil-digital'
            ),
            esc_html(
                $term
            )
        );
        ?>
    </h1>

    <p>
        <?php
        printf(
            esc_html(
                _n(
                    '%d resultado encontrado.',
                    '%d resultados encontrados.',
                    $count,
                    'facil-digital'
                )
            ),
            $count
        );
        ?>
    </p>

    <?php
    get_search_form();
    ?>
</header>
