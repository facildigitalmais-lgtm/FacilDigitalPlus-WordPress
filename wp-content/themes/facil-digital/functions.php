<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define(
    'FD_THEME_VERSION',
    '0.3.0'
);

define(
    'FD_THEME_DIR',
    get_template_directory()
);

define(
    'FD_THEME_URI',
    get_template_directory_uri()
);

$fdThemeFiles = [
    '/inc/setup.php',
    '/inc/assets.php',
    '/inc/template-functions.php',
    '/inc/woocommerce.php',
    '/inc/authentication.php',
    '/inc/catalog.php',
    '/inc/product.php',
];

foreach ($fdThemeFiles as $fdThemeFile) {
    $fdThemePath =
        FD_THEME_DIR
        . $fdThemeFile;

    if (!is_readable($fdThemePath)) {
        continue;
    }

    require_once $fdThemePath;
}
