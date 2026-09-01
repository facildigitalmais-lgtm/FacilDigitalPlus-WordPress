<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function fd_theme_asset_version(
    string $relativePath
): string {
    $relativePath =
        '/' . ltrim(
            $relativePath,
            '/'
        );

    $absolutePath =
        FD_THEME_DIR
        . $relativePath;

    if (
        is_file($absolutePath)
        && is_readable($absolutePath)
    ) {
        $modified =
            filemtime(
                $absolutePath
            );

        if ($modified !== false) {
            return (string) $modified;
        }
    }

    return FD_THEME_VERSION;
}

function fd_theme_enqueue_style_file(
    string $handle,
    string $file,
    array $dependencies = []
): void {
    wp_enqueue_style(
        $handle,
        FD_THEME_URI . $file,
        $dependencies,
        fd_theme_asset_version(
            $file
        )
    );
}

function fd_theme_enqueue_script_file(
    string $handle,
    string $file,
    array $dependencies = []
): void {
    wp_enqueue_script(
        $handle,
        FD_THEME_URI . $file,
        $dependencies,
        fd_theme_asset_version(
            $file
        ),
        [
            'in_footer' => true,
            'strategy'  => 'defer',
        ]
    );
}

function fd_theme_is_storefront_context(): bool
{
    if (
        function_exists(
            'is_woocommerce'
        )
        && is_woocommerce()
    ) {
        return true;
    }

    return is_search();
}

function fd_theme_enqueue_assets(): void
{
    fd_theme_enqueue_style_file(
        'fd-variables',
        '/assets/css/variables.css'
    );

    fd_theme_enqueue_style_file(
        'fd-reset',
        '/assets/css/reset.css',
        [
            'fd-variables',
        ]
    );

    fd_theme_enqueue_style_file(
        'fd-typography',
        '/assets/css/typography.css',
        [
            'fd-reset',
        ]
    );

    fd_theme_enqueue_style_file(
        'fd-layout',
        '/assets/css/layout.css',
        [
            'fd-typography',
        ]
    );

    fd_theme_enqueue_style_file(
        'fd-components',
        '/assets/css/components.css',
        [
            'fd-layout',
        ]
    );

    fd_theme_enqueue_style_file(
        'fd-header',
        '/assets/css/header.css',
        [
            'fd-components',
        ]
    );

    fd_theme_enqueue_style_file(
        'fd-footer',
        '/assets/css/footer.css',
        [
            'fd-components',
        ]
    );

    if (
        is_front_page()
        || fd_theme_is_storefront_context()
    ) {
        fd_theme_enqueue_style_file(
            'fd-product-card',
            '/assets/css/product-card.css',
            [
                'fd-components',
            ]
        );
    }

    if (is_front_page()) {
        fd_theme_enqueue_style_file(
            'fd-home',
            '/assets/css/home.css',
            [
                'fd-product-card',
            ]
        );
    }

    if (
        is_page()
        && !is_front_page()
    ) {
        fd_theme_enqueue_style_file(
            'fd-pages',
            '/assets/css/pages.css',
            [
                'fd-components',
            ]
        );
    }

    if (
        is_page_template(
            'templates/page-login.php'
        )
        || is_page_template(
            'templates/page-register.php'
        )
        || is_page_template(
            'templates/page-lost-password.php'
        )
    ) {
        fd_theme_enqueue_style_file(
            'fd-auth',
            '/assets/css/auth.css',
            [
                'fd-pages',
            ]
        );

        fd_theme_enqueue_script_file(
            'fd-auth',
            '/assets/js/auth.js'
        );
    }

    if (
        fd_theme_is_storefront_context()
    ) {
        fd_theme_enqueue_style_file(
            'fd-storefront',
            '/assets/css/storefront.css',
            [
                'fd-product-card',
            ]
        );

        fd_theme_enqueue_script_file(
            'fd-storefront',
            '/assets/js/storefront.js'
        );
    }

    if (
        function_exists('is_product')
        && is_product()
    ) {
        fd_theme_enqueue_style_file(
            'fd-product',
            '/assets/css/product.css',
            [
                'fd-storefront',
            ]
        );
    }

    if (
        function_exists('is_account_page')
        && is_account_page()
    ) {
        fd_theme_enqueue_style_file(
            'fd-student',
            '/assets/css/student.css',
            [
                'fd-components',
            ]
        );
    }

    if (
        (string) get_query_var('fd_simulation') !== ''
    ) {
        fd_theme_enqueue_style_file(
            'fd-simulation',
            '/assets/css/simulation.css',
            [
                'fd-components',
            ]
        );

        fd_theme_enqueue_script_file(
            'fd-simulation',
            '/assets/js/simulation.js'
        );
    }

    
    /*
     * UX-C: acabamento visual da Area do Aluno e dos simulados.
     * O Core/WooCommerce continuam responsaveis por dados e regras.
     */
    if (
        (
            function_exists('is_account_page')
            && is_account_page()
        )
        || (string) get_query_var('fd_simulation') !== ''
    ) {
        $fdUxCDependencies = [
            'fd-components',
        ];

        if (
            function_exists('is_account_page')
            && is_account_page()
        ) {
            $fdUxCDependencies[] =
                'fd-student';
        }

        if (
            (string) get_query_var('fd_simulation') !== ''
        ) {
            $fdUxCDependencies[] =
                'fd-simulation';
        }

        fd_theme_enqueue_style_file(
            'fd-ux-c',
            '/assets/css/ux-c.css',
            array_values(
                array_unique(
                    $fdUxCDependencies
                )
            )
        );
    }if (
        is_search()
        || is_404()
    ) {
        fd_theme_enqueue_style_file(
            'fd-search',
            '/assets/css/search.css',
            [
                'fd-components',
            ]
        );
    }

    fd_theme_enqueue_style_file(
        'fd-responsive',
        '/assets/css/responsive.css',
        [
            'fd-header',
            'fd-footer',
        ]
    );

    /*
     * UX-A: acabamento visual global.
     *
     * Carregado por ultimo para refinar header, footer e Home sem
     * substituir a logica do WooCommerce ou do Facil Digital Core.
     */
    fd_theme_enqueue_style_file(
        'fd-ux-a',
        '/assets/css/ux-a.css',
        [
            'fd-responsive',
        ]
    );

    /*
     * UX-B: acabamento visual do catalogo e da pagina de produto.
     * Carregado depois do UX-A sem substituir regras do WooCommerce/Core.
     */
    if (fd_theme_is_storefront_context()) {
        fd_theme_enqueue_style_file(
            'fd-ux-b',
            '/assets/css/ux-b.css',
            [
                'fd-ux-a',
            ]
        );
    }
    fd_theme_enqueue_script_file(
        'fd-navigation',
        '/assets/js/navigation.js'
    );
}

add_action(
    'wp_enqueue_scripts',
    'fd_theme_enqueue_assets'
);
