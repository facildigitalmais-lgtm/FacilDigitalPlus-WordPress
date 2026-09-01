<?php

declare(strict_types=1);

namespace FacilDigital\Core\PDFs;

use FacilDigital\Core\Entitlements\EntitlementRepository;
use FacilDigital\Core\Products\ProductMetadata;
use RuntimeException;

final class DownloadService
{
    public function __construct(
        private readonly PdfFileRepository $files = new PdfFileRepository(),
        private readonly EntitlementRepository $entitlements = new EntitlementRepository(),
        private readonly DownloadRepository $downloads = new DownloadRepository(),
        private readonly PrivateStorage $storage = new PrivateStorage()
    ) {
    }

    /**
     * @return array{pdf:array<string,mixed>,entitlement:array<string,mixed>,path:string,limit:int,used:int,remaining:int}
     */
    public function authorize(
        int $userId,
        int $pdfFileId
    ): array {
        if ($userId <= 0 || $pdfFileId <= 0) {
            throw new RuntimeException('download_unauthorized');
        }

        $pdf = $this->files->findById($pdfFileId);
        if (!is_array($pdf)) {
            throw new RuntimeException('download_pdf_missing');
        }

        if (
            (int) ($pdf['user_id'] ?? 0) !== $userId
            || ($pdf['status'] ?? '') !== 'ready'
        ) {
            throw new RuntimeException('download_unauthorized');
        }

        $entitlement = $this->entitlements->findById(
            (int) ($pdf['entitlement_id'] ?? 0)
        );

        if (
            !is_array($entitlement)
            || (int) ($entitlement['user_id'] ?? 0) !== $userId
            || (int) ($entitlement['product_id'] ?? 0) !== (int) ($pdf['product_id'] ?? 0)
            || ($entitlement['status'] ?? '') !== 'active'
        ) {
            throw new RuntimeException('download_entitlement_inactive');
        }

        $expiresAt = $entitlement['expires_at'] ?? null;
        if (
            is_string($expiresAt)
            && $expiresAt !== ''
            && strtotime($expiresAt) <= time()
        ) {
            throw new RuntimeException('download_entitlement_expired');
        }

        $productId = (int) ($pdf['product_id'] ?? 0);
        $limit = max(
            1,
            (int) ProductMetadata::get(
                $productId,
                ProductMetadata::DOWNLOAD_LIMIT,
                '5'
            )
        );

        $used = $this->downloads->countForEntitlement(
            (int) ($entitlement['id'] ?? 0)
        );

        if ($used >= $limit) {
            throw new RuntimeException('download_limit_reached');
        }

        $path = $this->storage->path(
            (string) ($pdf['storage_key'] ?? '')
        );
        $this->storage->assertPdf($path);

        return [
            'pdf' => $pdf,
            'entitlement' => $entitlement,
            'path' => $path,
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
        ];
    }

    /**
     * @param array{pdf:array<string,mixed>,entitlement:array<string,mixed>,path:string,limit:int,used:int,remaining:int} $authorization
     */
    public function record(array $authorization): int
    {
        $pdf = $authorization['pdf'];
        $entitlement = $authorization['entitlement'];

        return $this->downloads->record(
            (int) ($pdf['user_id'] ?? 0),
            (int) ($entitlement['id'] ?? 0),
            (int) ($pdf['id'] ?? 0),
            (int) ($pdf['product_id'] ?? 0),
            (int) ($pdf['order_id'] ?? 0),
            $this->requestHash(
                isset($_SERVER['REMOTE_ADDR'])
                    && is_string($_SERVER['REMOTE_ADDR'])
                        ? $_SERVER['REMOTE_ADDR']
                        : ''
            ),
            $this->requestHash(
                isset($_SERVER['HTTP_USER_AGENT'])
                    && is_string($_SERVER['HTTP_USER_AGENT'])
                        ? $_SERVER['HTTP_USER_AGENT']
                        : ''
            )
        );
    }

    /**
     * @param array{pdf:array<string,mixed>,entitlement:array<string,mixed>,path:string,limit:int,used:int,remaining:int} $authorization
     */
    public function stream(array $authorization): never
    {
        $this->record($authorization);

        $pdf = $authorization['pdf'];
        $path = $authorization['path'];
        $product = wc_get_product(
            (int) ($pdf['product_id'] ?? 0)
        );

        $baseName = $product
            ? sanitize_file_name($product->get_slug())
            : 'apostila';

        if ($baseName === '') {
            $baseName = 'apostila';
        }

        $filename = $baseName . '-personalizada.pdf';
        $size = filesize($path);

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        nocache_headers();
        header('Content-Type: application/pdf');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        if ($size !== false) {
            header('Content-Length: ' . (string) $size);
        }

        readfile($path);
        exit;
    }

    private function requestHash(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return hash_hmac(
            'sha256',
            $value,
            wp_salt('auth')
        );
    }
}
