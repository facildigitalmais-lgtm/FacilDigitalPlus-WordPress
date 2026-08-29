<?php

declare(strict_types=1);

namespace FacilDigital\Core\Entitlements;

use FacilDigital\Core\Core\Database;
use RuntimeException;

final class EntitlementRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public function findExact(
        int $userId,
        int $productId,
        int $orderId
    ): ?array {
        global $wpdb;

        $table = Database::table('entitlements');

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE user_id = %d
                   AND product_id = %d
                   AND order_id = %d
                 LIMIT 1",
                $userId,
                $productId,
                $orderId
            ),
            ARRAY_A
        );

        return is_array($row)
            ? $row
            : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActive(
        int $userId,
        int $productId
    ): ?array {
        global $wpdb;

        $table = Database::table('entitlements');
        $now = current_time('mysql', true);

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE user_id = %d
                   AND product_id = %d
                   AND status = 'active'
                   AND (
                       expires_at IS NULL
                       OR expires_at > %s
                   )
                 ORDER BY granted_at DESC, id DESC
                 LIMIT 1",
                $userId,
                $productId,
                $now
            ),
            ARRAY_A
        );

        return is_array($row)
            ? $row
            : null;
    }

    public function grant(
        int $userId,
        int $productId,
        int $orderId,
        ?int $orderItemId,
        string $source = 'woocommerce'
    ): int {
        global $wpdb;

        $existing = $this->findExact(
            $userId,
            $productId,
            $orderId
        );

        if (
            is_array($existing)
            && ($existing['status'] ?? '') === 'active'
        ) {
            return (int) $existing['id'];
        }

        $table = Database::table('entitlements');
        $now = current_time('mysql', true);

        if (is_array($existing)) {
            $updated = $wpdb->update(
                $table,
                [
                    'order_item_id' => $orderItemId,
                    'status' => 'active',
                    'source' => sanitize_key($source),
                    'granted_at' => $now,
                    'revoked_at' => null,
                    'revocation_reason' => null,
                    'updated_at' => $now,
                ],
                [
                    'id' => (int) $existing['id'],
                ],
                [
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    null,
                    null,
                    '%s',
                ],
                [
                    '%d',
                ]
            );

            if ($updated === false) {
                throw new RuntimeException(
                    'Falha ao reativar entitlement.'
                );
            }

            return (int) $existing['id'];
        }

        $inserted = $wpdb->insert(
            $table,
            [
                'user_id' => $userId,
                'product_id' => $productId,
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
                'status' => 'active',
                'source' => sanitize_key($source),
                'granted_at' => $now,
                'revoked_at' => null,
                'expires_at' => null,
                'revocation_reason' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                '%d',
                '%d',
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                null,
                null,
                null,
                '%s',
                '%s',
            ]
        );

        if ($inserted !== false) {
            return (int) $wpdb->insert_id;
        }

        /*
         * Uma segunda execução concorrente pode ter criado
         * a linha entre o SELECT e o INSERT. A unique key
         * user_product_order é a última barreira.
         */
        $concurrent = $this->findExact(
            $userId,
            $productId,
            $orderId
        );

        if (is_array($concurrent)) {
            return (int) $concurrent['id'];
        }

        throw new RuntimeException(
            'Falha ao criar entitlement.'
        );
    }

    public function revokeByOrder(
        int $orderId,
        string $reason
    ): int {
        global $wpdb;

        $table = Database::table('entitlements');
        $now = current_time('mysql', true);
        $reason = sanitize_key($reason);

        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                 SET status = 'revoked',
                     revoked_at = %s,
                     revocation_reason = %s,
                     updated_at = %s
                 WHERE order_id = %d
                   AND status = 'active'",
                $now,
                $reason,
                $now,
                $orderId
            )
        );

        if ($result === false) {
            throw new RuntimeException(
                'Falha ao revogar entitlements do pedido.'
            );
        }

        return (int) $result;
    }

    public function countForOrder(int $orderId): int
    {
        global $wpdb;

        $table = Database::table('entitlements');

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$table}
                 WHERE order_id = %d",
                $orderId
            )
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeForUser(int $userId): array
    {
        global $wpdb;

        $table = Database::table('entitlements');
        $now = current_time('mysql', true);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM {$table}
                 WHERE user_id = %d
                   AND status = 'active'
                   AND (
                       expires_at IS NULL
                       OR expires_at > %s
                   )
                 ORDER BY granted_at DESC, id DESC",
                $userId,
                $now
            ),
            ARRAY_A
        );

        return is_array($rows)
            ? array_values($rows)
            : [];
    }
}
