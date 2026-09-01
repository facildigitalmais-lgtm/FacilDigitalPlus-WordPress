<?php

declare(strict_types=1);

namespace FacilDigital\Core\WooCommerce;

use FacilDigital\Core\Entitlements\EntitlementService;
use Throwable;
use WC_Order;

final class OrderEntitlementHandler
{
    public function __construct(
        private readonly EntitlementService $service = new EntitlementService()
    ) {
    }

    public function handlePaidOrder(int $orderId): void
    {
        $order = wc_get_order($orderId);

        if (!$order instanceof WC_Order) {
            return;
        }

        try {
            $this->service->grantForPaidOrder($order);
        } catch (Throwable $exception) {
            $this->logFailure(
                'grant',
                $orderId,
                $exception
            );
        }
    }

    public function handleRefundedOrder(int $orderId): void
    {
        $this->revoke(
            $orderId,
            'woocommerce_refunded'
        );
    }

    public function handleCancelledOrder(int $orderId): void
    {
        $this->revoke(
            $orderId,
            'woocommerce_cancelled'
        );
    }

    public function handleFailedOrder(int $orderId): void
    {
        $this->revoke(
            $orderId,
            'woocommerce_failed'
        );
    }

    private function revoke(
        int $orderId,
        string $reason
    ): void {
        $order = wc_get_order($orderId);

        if (!$order instanceof WC_Order) {
            return;
        }

        try {
            $this->service->revokeForOrder(
                $order,
                $reason
            );
        } catch (Throwable $exception) {
            $this->logFailure(
                'revoke',
                $orderId,
                $exception
            );
        }
    }

    private function logFailure(
        string $operation,
        int $orderId,
        Throwable $exception
    ): void {
        if (!function_exists('wc_get_logger')) {
            return;
        }

        wc_get_logger()->error(
            'Falha de entitlement.',
            [
                'source' => 'facil-digital-core',
                'operation' => $operation,
                'order_id' => $orderId,
                'exception' => get_class($exception),
            ]
        );
    }
}
