<?php
/**
 * Plugin Name: Facil Digital+ Core
 * Plugin URI: https://facildigitalmais.com
 * Description: Regras de negocio da plataforma Facil Digital+.
 * Version: 0.2.0
 * Author: Facil Digital+
 * Author URI: https://facildigitalmais.com
 * Requires at least: 7.0
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * Text Domain: facil-digital-core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('FACIL_DIGITAL_CORE_VERSION', '0.2.0');
define('FACIL_DIGITAL_CORE_FILE', __FILE__);
define('FACIL_DIGITAL_CORE_DIR', plugin_dir_path(__FILE__));

$autoload = FACIL_DIGITAL_CORE_DIR . 'vendor/autoload.php';

if (!is_readable($autoload)) {
    add_action(
        'admin_notices',
        static function (): void {
            if (!current_user_can('manage_options')) {
                return;
            }

            echo '<div class="notice notice-error"><p>';
            echo esc_html__(
                'Facil Digital+ Core: execute o Composer antes de ativar o plugin.',
                'facil-digital-core'
            );
            echo '</p></div>';
        }
    );

    return;
}

require_once $autoload;

register_activation_hook(
    FACIL_DIGITAL_CORE_FILE,
    [\FacilDigital\Core\Core\Activator::class, 'activate']
);

add_action(
    'before_woocommerce_init',
    static function (): void {
        if (!class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            return;
        }

        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            FACIL_DIGITAL_CORE_FILE,
            true
        );
    }
);

add_action(
    'plugins_loaded',
    static function (): void {
        $requirements = new \FacilDigital\Core\Support\Requirements();
        $errors = $requirements->validate();

        if ($errors !== []) {
            add_action(
                'admin_notices',
                static function () use ($errors): void {
                    if (!current_user_can('manage_options')) {
                        return;
                    }

                    foreach ($errors as $error) {
                        printf(
                            '<div class="notice notice-error"><p>%s</p></div>',
                            esc_html($error)
                        );
                    }
                }
            );

            return;
        }

        try {
            \FacilDigital\Core\Core\Migrations::maybeRun();
        } catch (\Throwable $exception) {
            unset($exception);

            add_action(
                'admin_notices',
                static function (): void {
                    if (!current_user_can('manage_options')) {
                        return;
                    }

                    echo '<div class="notice notice-error"><p>';
                    echo esc_html__(
                        'Facil Digital+ Core: falha ao preparar as tabelas W3. Execute ./tools/validate-w3a.sh.',
                        'facil-digital-core'
                    );
                    echo '</p></div>';
                }
            );

            return;
        }

        (new \FacilDigital\Core\Core\Plugin())->boot();
    },
    20
);
