<?php

declare(strict_types=1);

namespace FacilDigital\Core\Core;

use WP_REST_Request;
use WP_REST_Response;

final class Plugin
{
    private bool $booted =
        false;

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted =
            true;

        if (
            !defined(
                'FACIL_DIGITAL_CORE_BOOTED'
            )
        ) {
            define(
                'FACIL_DIGITAL_CORE_BOOTED',
                true
            );
        }

        add_action(
            'admin_menu',
            [
                $this,
                'registerAdminMenu',
            ]
        );

        add_action(
            'rest_api_init',
            [
                $this,
                'registerRestRoutes',
            ]
        );
    }

    public function registerAdminMenu(): void
    {
        add_menu_page(
            __(
                'Facil Digital+',
                'facil-digital-core'
            ),
            __(
                'Facil Digital+',
                'facil-digital-core'
            ),
            'manage_options',
            'facil-digital',
            [
                $this,
                'renderAdminPage',
            ],
            'dashicons-welcome-learn-more',
            56
        );
    }

    public function registerRestRoutes(): void
    {
        register_rest_route(
            'facil-digital/v1',
            '/health',
            [
                'methods' =>
                    'GET',

                'callback' =>
                    [
                        $this,
                        'health',
                    ],

                'permission_callback' =>
                    '__return_true',
            ]
        );
    }

    public function health(
        WP_REST_Request $request
    ): WP_REST_Response {
        unset($request);

        return new WP_REST_Response(
            [
                'status' =>
                    'ok',

                'service' =>
                    'facil-digital-core',
            ],
            200
        );
    }

    public function renderAdminPage(): void
    {
        if (
            !current_user_can(
                'manage_options'
            )
        ) {
            wp_die(
                esc_html__(
                    'Acesso negado.',
                    'facil-digital-core'
                )
            );
        }

        $woocommerceVersion =
            defined(
                'WC_VERSION'
            )
                ? WC_VERSION
                : 'indisponivel';

        ?>
        <div class="wrap">
            <h1>
                <?php
                echo esc_html__(
                    'Facil Digital+',
                    'facil-digital-core'
                );
                ?>
            </h1>

            <p>
                <?php
                echo esc_html__(
                    'Fundacao do Facil Digital+ Core ativa.',
                    'facil-digital-core'
                );
                ?>
            </p>

            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th>Core</th>
                        <td>
                            <?php
                            echo esc_html(
                                FACIL_DIGITAL_CORE_VERSION
                            );
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th>WordPress</th>
                        <td>
                            <?php
                            echo esc_html(
                                get_bloginfo(
                                    'version'
                                )
                            );
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th>WooCommerce</th>
                        <td>
                            <?php
                            echo esc_html(
                                $woocommerceVersion
                            );
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th>PHP</th>
                        <td>
                            <?php
                            echo esc_html(
                                PHP_VERSION
                            );
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th>Ambiente</th>
                        <td>
                            <?php
                            echo esc_html(
                                wp_get_environment_type()
                            );
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
}
