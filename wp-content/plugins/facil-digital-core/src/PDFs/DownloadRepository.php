<?php

declare(strict_types=1);

namespace FacilDigital\Core\PDFs;

use FacilDigital\Core\Core\Database;
use RuntimeException;

final class DownloadRepository
{
    public function countForEntitlement(
        int $entitlementId
    ): int {
        global $wpdb;

        $table = Database::table('downloads');

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE entitlement_id = %d",
                $entitlementId
            )
        );
    }

    public function countForUser(int $userId): int
    {
        global $wpdb;

        $table = Database::table('downloads');

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
                $userId
            )
        );
    }

    public function record(
        int $userId,
        int $entitlementId,
        int $pdfFileId,
        int $productId,
        int $orderId,
        ?string $ipHash,
        ?string $userAgentHash
    ): int {
        global $wpdb;

        $table = Database::table('downloads');
        $inserted = $wpdb->insert(
            $table,
            [
                'user_id' => $userId,
                'entitlement_id' => $entitlementId,
                'pdf_file_id' => $pdfFileId,
                'product_id' => $productId,
                'order_id' => $orderId,
                'ip_hash' => $ipHash,
                'user_agent_hash' => $userAgentHash,
                'downloaded_at' => current_time('mysql', true),
            ]
        );

        if ($inserted === false) {
            throw new RuntimeException('download_record_failed');
        }

        return (int) $wpdb->insert_id;
    }
}
