<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function fd_theme_setup(): void
{
    load_theme_textdomain(
        'facil-digital',
        FD_THEME_DIR . '/languages'
    );

    add_theme_support(
        'title-tag'
    );

    add_theme_support(
        'post-thumbnails'
    );

    add_theme_support(
        'responsive-embeds'
    );

    add_theme_support(
        'align-wide'
    );

    add_theme_support(
        'woocommerce'
    );

    add_theme_support(
        'wc-product-gallery-zoom'
    );

    add_theme_support(
        'wc-product-gallery-lightbox'
    );

    add_theme_support(
        'wc-product-gallery-slider'
    );

    add_theme_support(
        'custom-logo',
        [
            'height'      => 72,
            'width'       => 260,
            'flex-height' => true,
            'flex-width'  => true,
        ]
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

            'footer-company' =>
                __(
                    'Rodape - Institucional',
                    'facil-digital'
                ),

            'footer-support' =>
                __(
                    'Rodape - Atendimento',
                    'facil-digital'
                ),

            'footer-legal' =>
                __(
                    'Rodape - Legal',
                    'facil-digital'
                ),
        ]
    );

    $GLOBALS['content_width'] =
        1180;
}

add_action(
    'after_setup_theme',
    'fd_theme_setup'
);