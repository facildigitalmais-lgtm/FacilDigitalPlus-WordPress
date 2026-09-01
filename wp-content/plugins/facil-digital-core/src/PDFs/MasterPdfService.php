<?php

declare(strict_types=1);

namespace FacilDigital\Core\PDFs;

use FacilDigital\Core\Products\ProductMetadata;
use RuntimeException;

final class MasterPdfService
{
    public function __construct(
        private readonly PrivateStorage $storage = new PrivateStorage()
    ) {
    }

    /**
     * @param array<string, mixed> $file
     */
    public function importUploaded(
        int $productId,
        array $file
    ): string {
        $error = isset($file['error'])
            ? (int) $file['error']
            : UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('master_upload_missing');
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('master_upload_failed');
        }

        $tmpName = isset($file['tmp_name'])
            && is_string($file['tmp_name'])
                ? $file['tmp_name']
                : '';

        $originalName = isset($file['name'])
            && is_string($file['name'])
                ? $file['name']
                : '';

        $size = isset($file['size'])
            ? (int) $file['size']
            : 0;

        if (
            $tmpName === ''
            || $size <= 0
            || strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'pdf'
        ) {
            throw new RuntimeException('master_upload_invalid');
        }

        $maxBytes = (int) apply_filters(
            'facil_digital_max_master_pdf_bytes',
            PrivateStorage::DEFAULT_MAX_MASTER_BYTES,
            $productId
        );

        if ($maxBytes > 0 && $size > $maxBytes) {
            throw new RuntimeException('master_upload_too_large');
        }

        return $this->importFromPath(
            $productId,
            $tmpName
        );
    }

    public function importFromPath(
        int $productId,
        string $sourcePath
    ): string {
        if (!ProductMetadata::isApostila($productId)) {
            throw new RuntimeException('master_product_not_apostila');
        }

        $this->storage->assertPdf($sourcePath);

        $oldKey = ProductMetadata::get(
            $productId,
            ProductMetadata::MASTER_PDF_KEY
        );

        $newKey = $this->storage->storeMaster(
            $productId,
            $sourcePath
        );

        update_post_meta(
            $productId,
            ProductMetadata::MASTER_PDF_KEY,
            $newKey
        );

        if ($oldKey !== '' && $oldKey !== $newKey) {
            $this->storage->delete($oldKey);
        }

        do_action(
            'facil_digital_master_pdf_updated',
            $productId
        );

        return $newKey;
    }

    public function masterPath(int $productId): string
    {
        $key = ProductMetadata::get(
            $productId,
            ProductMetadata::MASTER_PDF_KEY
        );

        if ($key === '') {
            throw new RuntimeException('master_pdf_missing');
        }

        $path = $this->storage->path($key);
        $this->storage->assertPdf($path);

        return $path;
    }

    public function hasMaster(int $productId): bool
    {
        try {
            $this->masterPath($productId);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }
}
