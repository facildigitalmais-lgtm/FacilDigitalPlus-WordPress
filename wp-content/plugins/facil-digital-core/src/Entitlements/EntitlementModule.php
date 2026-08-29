<?php

declare(strict_types=1);

namespace FacilDigital\Core\Entitlements;

use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\WooCommerce\OrderEntitlementHandler;

final class EntitlementModule implements ModuleInterface
{
    public function __construct(
        private readonly OrderEntitlementHandler $handler = new OrderEntitlementHandler()
    ) {
    }

    public function register(): void
    {
        add_action(
            'woocommerce_payment_complete',
            [$this->handler, 'handlePaidOrder'],
            20
        );

        add_action(
            'woocommerce_order_status_processing',
            [$this->handler, 'handlePaidOrder'],
            20
        );

        add_action(
            'woocommerce_order_status_completed',
            [$this->handler, 'handlePaidOrder'],
            20
        );

        add_action(
            'woocommerce_order_status_refunded',
            [$this->handler, 'handleRefundedOrder'],
            20
        );

        add_action(
            'woocommerce_order_status_cancelled',
            [$this->handler, 'handleCancelledOrder'],
            20
        );

        add_action(
            'woocommerce_order_status_failed',
            [$this->handler, 'handleFailedOrder'],
            20
        );
    }
}
