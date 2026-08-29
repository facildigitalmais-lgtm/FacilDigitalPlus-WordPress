<?php

declare(strict_types=1);

namespace FacilDigital\Core\Admin;

use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Core\Capabilities;
use FacilDigital\Core\Core\Database;

final class Menu implements ModuleInterface
{
    public const PARENT_SLUG = 'facil-digital';
    private const SETTINGS_SLUG = 'facil-digital-settings';

    public function register(): void
    {
        add_action(
            'admin_menu',
            [$this, 'registerMenu']
        );
    }

    public function registerMenu(): void
    {
        add_menu_page(
            __('Fácil Digital+', 'facil-digital-core'),
            __('Fácil Digital+', 'facil-digital-core'),
            Capabilities::ACCESS_ADMIN,
            self::PARENT_SLUG,
            [$this, 'renderDashboard'],
            'dashicons-welcome-learn-more',
            56
        );

        add_submenu_page(
            self::PARENT_SLUG,
            __('Dashboard', 'facil-digital-core'),
            __('Dashboard', 'facil-digital-core'),
            Capabilities::ACCESS_ADMIN,
            self::PARENT_SLUG,
            [$this, 'renderDashboard']
        );

        add_submenu_page(
            self::PARENT_SLUG,
            __('Configurações', 'facil-digital-core'),
            __('Configurações', 'facil-digital-core'),
            Capabilities::MANAGE_SETTINGS,
            self::SETTINGS_SLUG,
            [$this, 'renderSettings']
        );
    }

    public function renderDashboard(): void
    {
        $this->guard(Capabilities::ACCESS_ADMIN);

        $woocommerceVersion = defined('WC_VERSION')
            ? WC_VERSION
            : 'indisponível';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Fácil Digital+', 'facil-digital-core'); ?></h1>
            <p>
                <?php
                echo esc_html__(
                    'Fundação do Fácil Digital+ Core ativa.',
                    'facil-digital-core'
                );
                ?>
            </p>

            <table class="widefat striped" style="max-width: 760px">
                <tbody>
                    <tr>
                        <th scope="row">Core</th>
                        <td><?php echo esc_html(FACIL_DIGITAL_CORE_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Schema</th>
                        <td><?php echo esc_html(Database::installedVersion()); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Permissões</th>
                        <td><?php echo esc_html(Capabilities::installedVersion()); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">WordPress</th>
                        <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">WooCommerce</th>
                        <td><?php echo esc_html($woocommerceVersion); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">PHP</th>
                        <td><?php echo esc_html(PHP_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Ambiente</th>
                        <td><?php echo esc_html(wp_get_environment_type()); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function renderSettings(): void
    {
        $this->guard(Capabilities::MANAGE_SETTINGS);
        ?>
        <div class="wrap">
            <h1>
                <?php
                echo esc_html__(
                    'Configurações — Fácil Digital+',
                    'facil-digital-core'
                );
                ?>
            </h1>
            <p>
                <?php
                echo esc_html__(
                    'A estrutura de permissões está pronta. As configurações funcionais serão adicionadas nas próximas fases.',
                    'facil-digital-core'
                );
                ?>
            </p>
        </div>
        <?php
    }

    private function guard(string $capability): void
    {
        if (current_user_can($capability)) {
            return;
        }

        wp_die(
            esc_html__('Acesso negado.', 'facil-digital-core')
        );
    }
}
