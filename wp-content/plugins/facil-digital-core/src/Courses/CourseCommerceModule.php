<?php

declare(strict_types=1);

namespace FacilDigital\Core\Courses;

use FacilDigital\Core\Contracts\ModuleInterface;
use WC_Order;
use WC_Order_Item_Product;

final class CourseCommerceModule implements ModuleInterface
{
    public function __construct(
        private readonly CourseRepository $courses =
            new CourseRepository(),
        private readonly EnrollmentRepository $enrollments =
            new EnrollmentRepository()
    ) {
    }

    public function register(): void
    {
        add_action(
            'woocommerce_order_status_processing',
            [$this, 'grantFromOrder'],
            20,
            1
        );

        add_action(
            'woocommerce_order_status_completed',
            [$this, 'grantFromOrder'],
            20,
            1
        );

        add_action(
            'woocommerce_order_status_cancelled',
            [$this, 'revokeFromOrder'],
            20,
            1
        );

        add_action(
            'woocommerce_order_status_failed',
            [$this, 'revokeFromOrder'],
            20,
            1
        );

        add_action(
            'woocommerce_order_status_refunded',
            [$this, 'revokeFromOrder'],
            20,
            1
        );
    }

    public function grantFromOrder(int $orderId): void
    {
        $order = wc_get_order(
            $orderId
        );

        if (
            !$order instanceof WC_Order
            || !$order->has_status(
                [
                    'processing',
                    'completed',
                ]
            )
        ) {
            return;
        }

        $userId =
            (int) $order->get_user_id();

        if ($userId <= 0) {
            return;
        }

        foreach (
            $order->get_items(
                'line_item'
            )
            as $item
        ) {
            if (
                !$item
                instanceof WC_Order_Item_Product
            ) {
                continue;
            }

            $productId =
                (int) $item->get_product_id();

            if ($productId <= 0) {
                continue;
            }

            $course =
                $this->courses
                    ->findByProductId(
                        $productId
                    );

            if (!is_array($course)) {
                continue;
            }

            $enrollmentId =
                $this->enrollments->grant(
                    $userId,
                    (int) (
                        $course['id']
                        ?? 0
                    ),
                    $productId,
                    $orderId
                );

            $enrollment =
                $this->enrollments
                    ->findById(
                        $enrollmentId
                    );

            if (
                is_array($enrollment)
                && (string) (
                    $enrollment['status']
                    ?? ''
                ) === 'revoked'
            ) {
                $this->enrollments
                    ->reactivate(
                        $enrollmentId
                    );
            }
        }
    }

    public function revokeFromOrder(
        int $orderId
    ): void {
        $order = wc_get_order(
            $orderId
        );

        if (!$order instanceof WC_Order) {
            return;
        }

        $userId =
            (int) $order->get_user_id();

        if ($userId <= 0) {
            return;
        }

        $reason =
            'order_'
            . sanitize_key(
                (string) $order->get_status()
            );

        foreach (
            $this->enrollments->forUser(
                $userId
            )
            as $enrollment
        ) {
            if (
                (int) (
                    $enrollment['order_id']
                    ?? 0
                ) !== $orderId
                || (string) (
                    $enrollment['status']
                    ?? ''
                ) === 'revoked'
            ) {
                continue;
            }

            $this->enrollments->revoke(
                (int) (
                    $enrollment['id']
                    ?? 0
                ),
                $reason
            );
        }
    }
}