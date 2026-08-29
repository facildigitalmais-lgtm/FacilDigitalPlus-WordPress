<?php

declare(strict_types=1);

namespace FacilDigital\Core\API;

use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\PDFs\DownloadModule;
use FacilDigital\Core\PDFs\DownloadRepository;
use FacilDigital\Core\PDFs\PdfFileRepository;
use FacilDigital\Core\Products\ProductMetadata;
use WP_REST_Request;
use WP_REST_Response;

final class PdfController implements ModuleInterface
{
    private const NAMESPACE = 'facil-digital/v1';
    private const ROUTE = '/me/pdfs';

    public function __construct(
        private readonly PdfFileRepository $files = new PdfFileRepository(),
        private readonly DownloadRepository $downloads = new DownloadRepository()
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

        $items = [];

        foreach (
            $this->files->readyForUser(
                get_current_user_id()
            )
            as $row
        ) {
            $productId = (int) ($row['product_id'] ?? 0);
            $entitlementId = (int) ($row['entitlement_id'] ?? 0);
            $product = wc_get_product($productId);
            $limit = max(
                1,
                (int) ProductMetadata::get(
                    $productId,
                    ProductMetadata::DOWNLOAD_LIMIT,
                    '5'
                )
            );
            $used = $this->downloads->countForEntitlement(
                $entitlementId
            );

            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'product_id' => $productId,
                'product_name' => $product ? $product->get_name() : '',
                'order_id' => (int) ($row['order_id'] ?? 0),
                'product_version' => (string) ($row['product_version'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'tracking_code' => (string) ($row['tracking_code'] ?? ''),
                'generated_at' => $row['generated_at'] ?? null,
                'download_limit' => $limit,
                'downloads_used' => $used,
                'downloads_remaining' => max(0, $limit - $used),
                'download_url' => $used < $limit
                    ? DownloadModule::url((int) ($row['id'] ?? 0))
                    : null,
            ];
        }

        return new WP_REST_Response(
            ['items' => $items],
            200
        );
    }
}
