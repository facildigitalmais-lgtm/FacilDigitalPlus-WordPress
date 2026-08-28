<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function fd_theme_page_url(
    string $slug
): string {
    $page =
        get_page_by_path(
            $slug,
            OBJECT,
            'page'
        );

    if ($page instanceof WP_Post) {
        $permalink =
            get_permalink(
                $page->ID
            );

        if (
            is_string($permalink)
            && $permalink !== ''
        ) {
            return $permalink;
        }
    }

    return home_url(
        '/' . trim(
            $slug,
            '/'
        ) . '/'
    );
}

function fd_theme_primary_menu_fallback(): void
{
    $items = [
        [
            'label' => __(
                'Inicio',
                'facil-digital'
            ),
            'url'   => home_url('/'),
        ],
        [
            'label' => __(
                'Apostilas',
                'facil-digital'
            ),
            'url'   => fd_theme_get_shop_url(),
        ],
        [
            'label' => __(
                'Sobre',
                'facil-digital'
            ),
            'url'   => fd_theme_page_url(
                'sobre'
            ),
        ],
        [
            'label' => __(
                'FAQ',
                'facil-digital'
            ),
            'url'   => fd_theme_page_url(
                'faq'
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

    return fd_theme_page_url(
        'minha-conta'
    );
}

function fd_theme_get_login_url(): string
{
    if (is_user_logged_in()) {
        return fd_theme_get_account_url();
    }

    return fd_theme_page_url(
        'entrar'
    );
}

function fd_theme_get_register_url(): string
{
    return fd_theme_page_url(
        'cadastro'
    );
}

function fd_theme_get_lost_password_url(): string
{
    return fd_theme_page_url(
        'recuperar-senha'
    );
}

function fd_theme_get_terms_url(): string
{
    $pageId =
        (int) get_option(
            'woocommerce_terms_page_id'
        );

    if ($pageId > 0) {
        $url =
            get_permalink(
                $pageId
            );

        if (is_string($url)) {
            return $url;
        }
    }

    return fd_theme_page_url(
        'termos'
    );
}

function fd_theme_get_privacy_url(): string
{
    $pageId =
        (int) get_option(
            'wp_page_for_privacy_policy'
        );

    if ($pageId > 0) {
        $url =
            get_permalink(
                $pageId
            );

        if (is_string($url)) {
            return $url;
        }
    }

    return fd_theme_page_url(
        'privacidade'
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

    return fd_theme_page_url(
        'carrinho'
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

    return fd_theme_page_url(
        'apostilas'
    );
}

function fd_theme_get_cart_count(): int
{
    if (!function_exists('WC')) {
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

        'check' =>
            '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m5 12 4 4L19 6"/></svg>',

        'lock' =>
            '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>',
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
        'footer-company' => [
            [
                'label' => 'Sobre',
                'url'   => fd_theme_page_url(
                    'sobre'
                ),
            ],
            [
                'label' => 'Apostilas',
                'url'   => fd_theme_get_shop_url(),
            ],
            [
                'label' => 'FAQ',
                'url'   => fd_theme_page_url(
                    'faq'
                ),
            ],
        ],

        'footer-support' => [
            [
                'label' => 'Contato',
                'url'   => fd_theme_page_url(
                    'contato'
                ),
            ],
            [
                'label' =>
                    is_user_logged_in()
                        ? 'Minha conta'
                        : 'Entrar',
                'url' =>
                    is_user_logged_in()
                        ? fd_theme_get_account_url()
                        : fd_theme_get_login_url(),
            ],
            [
                'label' => 'Carrinho',
                'url'   => fd_theme_get_cart_url(),
            ],
        ],

        'footer-legal' => [
            [
                'label' =>
                    'Politica de Privacidade',
                'url' =>
                    fd_theme_get_privacy_url(),
            ],
            [
                'label' =>
                    'Termos de Uso',
                'url' =>
                    fd_theme_get_terms_url(),
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