<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define(
    'FD_THEME_VERSION',
    '0.1.0'
);

function fd_theme_setup(): void
{
    add_theme_support(
        'title-tag'
    );

    add_theme_support(
        'post-thumbnails'
    );

    add_theme_support(
        'woocommerce'
    );

    add_theme_support(
        'html5',
        [
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
            'style',
            'script',
        ]
    );

    register_nav_menus(
        [
            'primary' =>
                __(
                    'Menu principal',
                    'facil-digital'
                ),
        ]
    );
}

add_action(
    'after_setup_theme',
    'fd_theme_setup'
);

function fd_theme_enqueue_assets(): void
{
    wp_enqueue_style(
        'fd-theme-main',
        get_template_directory_uri()
            . '/assets/css/main.css',
        [],
        FD_THEME_VERSION
    );
}

add_action(
    'wp_enqueue_scripts',
    'fd_theme_enqueue_assets'
);
