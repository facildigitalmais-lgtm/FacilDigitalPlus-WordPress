<?php

declare(strict_types=1);

namespace FacilDigital\Core\API;

use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Entitlements\EntitlementService;
use WP_REST_Request;
use WP_REST_Response;

final class EntitlementController implements ModuleInterface
{
    private const NAMESPACE = 'facil-digital/v1';
    private const ROUTE = '/me/entitlements';

    public function __construct(
        private readonly EntitlementService $service = new EntitlementService()
    ) {
    }

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
                'callback' => [$this, 'index'],
                'permission_callback' => [$this, 'permissions'],
            ]
        );
    }

    public function permissions(): bool
    {
        return is_user_logged_in();
    }

    public function index(
        WP_REST_Request $request
    ): WP_REST_Response {
        unset($request);

        $rows = $this->service->activeForUser(
            get_current_user_id()
        );

        $items = [];

        foreach ($rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $product = wc_get_product($productId);

            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'product_id' => $productId,
                'product_name' => $product
                    ? $product->get_name()
                    : '',
                'order_id' => (int) ($row['order_id'] ?? 0),
                'status' => (string) ($row['status'] ?? ''),
                'granted_at' => (string) ($row['granted_at'] ?? ''),
                'expires_at' => $row['expires_at'] ?? null,
            ];
        }

        return new WP_REST_Response(
            [
                'items' => $items,
            ],
            200
        );
    }
}
