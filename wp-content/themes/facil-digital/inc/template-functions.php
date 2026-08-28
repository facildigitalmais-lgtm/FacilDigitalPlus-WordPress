<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function fd_theme_primary_menu_fallback(): void
{
    $items = [
        [
            'label' =>
                __(
                    'Inicio',
                    'facil-digital'
                ),

            'url' =>
                home_url('/'),
        ],

        [
            'label' =>
                __(
                    'Apostilas',
                    'facil-digital'
                ),

            'url' =>
                home_url(
                    '/apostilas/'
                ),
        ],

        [
            'label' =>
                __(
                    'Sobre',
                    'facil-digital'
                ),

            'url' =>
                home_url(
                    '/sobre/'
                ),
        ],

        [
            'label' =>
                __(
                    'FAQ',
                    'facil-digital'
                ),

            'url' =>
                home_url(
                    '/faq/'
                ),
        ],
    ];

    echo '<ul class="fd-primary-nav__list">';

    foreach ($items as $item) {
        printf(
            '<li class="fd-primary-nav__item"><a class="fd-primary-nav__link" href="%1$s">%2$s</a></li>',
            esc_url(
                $item['url']
            ),
            esc_html(
                $item['label']
            )
        );
    }

    echo '</ul>';
}

function fd_theme_get_account_url(): string
{
    if (
        function_exists(
            'wc_get_page_permalink'
        )
    ) {
        $url =
            wc_get_page_permalink(
                'myaccount'
            );

        if (
            is_string($url)
            && $url !== ''
        ) {
            return $url;
        }
    }

    return home_url(
        '/minha-conta/'
    );
}

function fd_theme_get_cart_url(): string
{
    if (
        function_exists(
            'wc_get_cart_url'
        )
    ) {
        return wc_get_cart_url();
    }

    return home_url(
        '/carrinho/'
    );
}

function fd_theme_get_shop_url(): string
{
    if (
        function_exists(
            'wc_get_page_permalink'
        )
    ) {
        $url =
            wc_get_page_permalink(
                'shop'
            );

        if (
            is_string($url)
            && $url !== ''
        ) {
            return $url;
        }
    }

    return home_url(
        '/apostilas/'
    );
}

function fd_theme_get_cart_count(): int
{
    if (
        !function_exists('WC')
    ) {
        return 0;
    }

    $woocommerce =
        WC();

    if (
        !$woocommerce
        || !$woocommerce->cart
    ) {
        return 0;
    }

    return (int)
        $woocommerce
            ->cart
            ->get_cart_contents_count();
}

function fd_theme_icon(
    string $name
): string {
    $icons = [
        'search' =>
            '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m21 21-4.35-4.35m2.35-5.15A7.5 7.5 0 1 1 4 11.5a7.5 7.5 0 0 1 15 0Z"/></svg>',

        'user' =>
            '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20 21a8 8 0 0 0-16 0m12-13a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/></svg>',

        'cart' =>
            '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 4h2l2.2 10.1a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.6L21 7H6m3 13a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm10 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z"/></svg>',
    ];

    if (
        !array_key_exists(
            $name,
            $icons
        )
    ) {
        return '';
    }

    return sprintf(
        '<span class="fd-icon fd-icon--%1$s" aria-hidden="true">%2$s</span>',
        esc_attr($name),
        $icons[$name]
    );
}

function fd_theme_footer_fallback_items(
    string $location
): array {
    $menus = [
        'footer-company' =>
            [
                [
                    'label' => 'Sobre',
                    'url'   => home_url('/sobre/'),
                ],

                [
                    'label' => 'Apostilas',
                    'url'   => home_url('/apostilas/'),
                ],

                [
                    'label' => 'FAQ',
                    'url'   => home_url('/faq/'),
                ],
            ],

        'footer-support' =>
            [
                [
                    'label' => 'Contato',
                    'url'   => home_url('/contato/'),
                ],

                [
                    'label' => 'Minha conta',
                    'url'   => fd_theme_get_account_url(),
                ],

                [
                    'label' => 'Carrinho',
                    'url'   => fd_theme_get_cart_url(),
                ],
            ],

        'footer-legal' =>
            [
                [
                    'label' =>
                        'Politica de Privacidade',

                    'url' =>
                        home_url(
                            '/privacidade/'
                        ),
                ],

                [
                    'label' =>
                        'Termos de Uso',

                    'url' =>
                        home_url(
                            '/termos/'
                        ),
                ],
            ],
    ];

    return $menus[$location] ?? [];
}

function fd_theme_render_footer_fallback(
    string $location
): void {
    $items =
        fd_theme_footer_fallback_items(
            $location
        );

    if ($items === []) {
        return;
    }

    echo '<ul class="fd-footer-menu">';

    foreach ($items as $item) {
        printf(
            '<li><a href="%1$s">%2$s</a></li>',
            esc_url(
                $item['url']
            ),
            esc_html(
                $item['label']
            )
        );
    }

    echo '</ul>';
}