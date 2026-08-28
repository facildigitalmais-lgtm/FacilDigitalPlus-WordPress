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

function fd_theme_enqueue_assets(): void
{
    $styles = [
        [
            'handle' =>
                'fd-variables',

            'file' =>
                '/assets/css/variables.css',

            'dependencies' =>
                [],
        ],

        [
            'handle' =>
                'fd-reset',

            'file' =>
                '/assets/css/reset.css',

            'dependencies' =>
                [
                    'fd-variables',
                ],
        ],

        [
            'handle' =>
                'fd-typography',

            'file' =>
                '/assets/css/typography.css',

            'dependencies' =>
                [
                    'fd-reset',
                ],
        ],

        [
            'handle' =>
                'fd-layout',

            'file' =>
                '/assets/css/layout.css',

            'dependencies' =>
                [
                    'fd-typography',
                ],
        ],

        [
            'handle' =>
                'fd-components',

            'file' =>
                '/assets/css/components.css',

            'dependencies' =>
                [
                    'fd-layout',
                ],
        ],

        [
            'handle' =>
                'fd-header',

            'file' =>
                '/assets/css/header.css',

            'dependencies' =>
                [
                    'fd-components',
                ],
        ],

        [
            'handle' =>
                'fd-footer',

            'file' =>
                '/assets/css/footer.css',

            'dependencies' =>
                [
                    'fd-components',
                ],
        ],

        [
            'handle' =>
                'fd-responsive',

            'file' =>
                '/assets/css/responsive.css',

            'dependencies' =>
                [
                    'fd-header',
                    'fd-footer',
                ],
        ],
    ];

    foreach ($styles as $style) {
        wp_enqueue_style(
            $style['handle'],
            FD_THEME_URI
                . $style['file'],
            $style['dependencies'],
            fd_theme_asset_version(
                $style['file']
            )
        );
    }

    wp_enqueue_script(
        'fd-navigation',
        FD_THEME_URI
            . '/assets/js/navigation.js',
        [],
        fd_theme_asset_version(
            '/assets/js/navigation.js'
        ),
        [
            'in_footer' =>
                true,

            'strategy' =>
                'defer',
        ]
    );
}

add_action(
    'wp_enqueue_scripts',
    'fd_theme_enqueue_assets'
);