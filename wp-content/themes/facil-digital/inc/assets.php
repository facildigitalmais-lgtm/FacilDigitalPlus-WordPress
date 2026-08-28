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

    if (is_front_page()) {
        fd_theme_enqueue_style_file(
            'fd-home',
            '/assets/css/home.css',
            [
                'fd-components',
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

    fd_theme_enqueue_style_file(
        'fd-responsive',
        '/assets/css/responsive.css',
        [
            'fd-header',
            'fd-footer',
        ]
    );

    fd_theme_enqueue_script_file(
        'fd-navigation',
        '/assets/js/navigation.js'
    );
}

add_action(
    'wp_enqueue_scripts',
    'fd_theme_enqueue_assets'
);