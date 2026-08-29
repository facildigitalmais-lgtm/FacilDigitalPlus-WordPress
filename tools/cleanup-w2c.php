<?php

if (!defined('ABSPATH')) {
    exit;
}

if (
    wp_get_environment_type()
    !== 'development'
) {
    throw new RuntimeException(
        'W2C cleanup permitido somente em development.'
    );
}

$ids =
    get_posts(
        [
            'post_type' =>
                'product',

            'post_status' =>
                'any',

            'posts_per_page' =>
                -1,

            'fields' =>
                'ids',

            'meta_key' =>
                '_fd_w2c_seed',

            'meta_value' =>
                '1',

            'no_found_rows' =>
                true,
        ]
    );

$removed = [];

foreach ($ids as $id) {
    $id =
        (int) $id;

    $title =
        get_the_title(
            $id
        );

    $deleted =
        wp_delete_post(
            $id,
            true
        );

    if (!$deleted) {
        continue;
    }

    $removed[] = [
        'id' =>
            $id,

        'title' =>
            $title,
    ];
}

echo wp_json_encode(
    [
        'status' =>
            'cleaned',

        'removed_count' =>
            count(
                $removed
            ),

        'removed' =>
            $removed,
    ],
    JSON_PRETTY_PRINT
);

echo PHP_EOL;
