<?php

declare(strict_types=1);

namespace FacilDigital\Core\PDFs;

use FacilDigital\Core\Core\Database;
use RuntimeException;

final class PdfFileRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        global $wpdb;

        $table = Database::table('pdf_files');
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForEntitlementVersion(
        int $entitlementId,
        string $version
    ): ?array {
        global $wpdb;

        $table = Database::table('pdf_files');
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE entitlement_id = %d
                   AND product_version = %s
                 LIMIT 1",
                $entitlementId,
                $version
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $entitlement
     * @return array<string, mixed>
     */
    public function ensurePending(
        array $entitlement,
        string $version,
        string $storageKey,
        string $trackingCode,
        bool $watermarkEnabled,
        bool $passwordEnabled
    ): array {
        global $wpdb;

        $entitlementId = (int) ($entitlement['id'] ?? 0);
        $existing = $this->findForEntitlementVersion(
            $entitlementId,
            $version
        );

        if (is_array($existing)) {
            return $existing;
        }

        $table = Database::table('pdf_files');
        $now = current_time('mysql', true);

        $inserted = $wpdb->insert(
            $table,
            [
                'entitlement_id' => $entitlementId,
                'user_id' => (int) ($entitlement['user_id'] ?? 0),
                'product_id' => (int) ($entitlement['product_id'] ?? 0),
                'order_id' => (int) ($entitlement['order_id'] ?? 0),
                'product_version' => $version,
                'storage_key' => $storageKey,
                'file_size' => null,
                'sha256' => null,
                'tracking_code' => $trackingCode,
                'status' => 'pending',
                'watermark_enabled' => $watermarkEnabled ? 1 : 0,
                'password_enabled' => $passwordEnabled ? 1 : 0,
                'generation_attempts' => 0,
                'generated_at' => null,
                'error_code' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        if ($inserted === false) {
            $concurrent = $this->findForEntitlementVersion(
                $entitlementId,
                $version
            );

            if (is_array($concurrent)) {
                return $concurrent;
            }

            throw new RuntimeException('pdf_record_create_failed');
        }

        $row = $this->findById((int) $wpdb->insert_id);

        if (!is_array($row)) {
            throw new RuntimeException('pdf_record_missing');
        }

        return $row;
    }

    public function markGenerating(int $id): void
    {
        global $wpdb;

        $table = Database::table('pdf_files');
        $now = current_time('mysql', true);
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                 SET status = 'generating',
                     generation_attempts = generation_attempts + 1,
                     error_code = NULL,
                     updated_at = %s
                 WHERE id = %d",
                $now,
                $id
            )
        );

        if ($result === false) {
            throw new RuntimeException('pdf_record_update_failed');
        }
    }

    public function markReady(
        int $id,
        int $fileSize,
        string $sha256
    ): void {
        global $wpdb;

        $table = Database::table('pdf_files');
        $now = current_time('mysql', true);
        $result = $wpdb->update(
            $table,
            [
                'status' => 'ready',
                'file_size' => $fileSize,
                'sha256' => $sha256,
                'generated_at' => $now,
                'error_code' => null,
                'updated_at' => $now,
            ],
            ['id' => $id]
        );

        if ($result === false) {
            throw new RuntimeException('pdf_record_ready_failed');
        }
    }

    public function markFailed(
        int $id,
        string $errorCode
    ): void {
        global $wpdb;

        $table = Database::table('pdf_files');
        $wpdb->update(
            $table,
            [
                'status' => 'failed',
                'error_code' => sanitize_key($errorCode),
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $id]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function readyForUser(int $userId): array
    {
        global $wpdb;

        $pdf = Database::table('pdf_files');
        $entitlements = Database::table('entitlements');
        $now = current_time('mysql', true);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.*
                 FROM {$pdf} p
                 INNER JOIN {$entitlements} e
                    ON e.id = p.entitlement_id
                 WHERE p.user_id = %d
                   AND p.status = 'ready'
                   AND e.status = 'active'
                   AND (e.expires_at IS NULL OR e.expires_at > %s)
                 ORDER BY p.generated_at DESC, p.id DESC",
                $userId,
                $now
            ),
            ARRAY_A
        );

        return is_array($rows) ? array_values($rows) : [];
    }
}
