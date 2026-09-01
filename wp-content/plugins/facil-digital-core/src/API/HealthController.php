<?php

declare(strict_types=1);

namespace FacilDigital\Core\API;

use FacilDigital\Core\Contracts\ModuleInterface;
use WP_REST_Request;
use WP_REST_Response;

final class HealthController implements ModuleInterface
{
    public function register(): void
    {
        add_action(
            'rest_api_init',
            [$this, 'registerRoutes']
        );
    }

    public function registerRoutes(): void
    {
        register_rest_route(
            'facil-digital/v1',
            '/health',
            [
                'methods' => 'GET',
                'callback' => [$this, 'health'],
                'permission_callback' => '__return_true',
            ]
        );
    }

    public function health(
        WP_REST_Request $request
    ): WP_REST_Response {
        unset($request);

        return new WP_REST_Response(
            [
                'status' => 'ok',
                'service' => 'facil-digital-core',
            ],
            200
        );
    }
}
