<?php

declare(strict_types=1);

namespace FacilDigital\Core\Entitlements;

use FacilDigital\Core\Products\ProductMetadata;
use WC_Order;
use WC_Order_Item_Product;

final class EntitlementService
{
    public function __construct(
        private readonly EntitlementRepository $repository = new EntitlementRepository()
    ) {
    }

    public function grantForPaidOrder(WC_Order $order): int
    {
        if (!$this->isAuthoritativePaidOrder($order)) {
            return 0;
        }

        $userId = (int) $order->get_user_id();

        if ($userId <= 0) {
            return 0;
        }

        $granted = 0;

        foreach ($order->get_items('line_item') as $itemId => $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            $productId = (int) $item->get_product_id();

            if (
                $productId <= 0
                || !ProductMetadata::isApostila($productId)
            ) {
                continue;
            }

            $this->repository->grant(
                $userId,
                $productId,
                (int) $order->get_id(),
                (int) $itemId,
                'woocommerce'
            );

            $granted++;
        }

        return $granted;
    }

    public function revokeForOrder(
        WC_Order $order,
        string $reason
    ): int {
        return $this->repository->revokeByOrder(
            (int) $order->get_id(),
            $reason
        );
    }

    public function userCanAccessProduct(
        int $userId,
        int $productId
    ): bool {
        if (
            $userId <= 0
            || $productId <= 0
        ) {
            return false;
        }

        return $this->repository->findActive(
            $userId,
            $productId
        ) !== null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return $this->repository->activeForUser($userId);
    }

    private function isAuthoritativePaidOrder(WC_Order $order): bool
    {
        return $order->is_paid()
            && $order->get_date_paid() !== null;
    }
}
