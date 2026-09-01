<?php

declare(strict_types=1);

namespace FacilDigital\Core\API;

use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Core\Capabilities;
use FacilDigital\Core\Support\Diagnostics;
use WP_REST_Request;
use WP_REST_Response;

final class StatusController implements ModuleInterface
{
    private const NAMESPACE = 'facil-digital/v1';

    private const ROUTE = '/status';

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
            self::NAMESPACE,
            self::ROUTE,
            [
                'methods' => 'GET',
                'callback' => [$this, 'show'],
                'permission_callback' => [$this, 'permissions'],
            ]
        );
    }

    public function permissions(): bool
    {
        return current_user_can(
            Capabilities::ACCESS_ADMIN
        );
    }

    public function show(
        WP_REST_Request $request
    ): WP_REST_Response {
        unset($request);

        return new WP_REST_Response(
            (new Diagnostics())->snapshot(),
            200
        );
    }
}
