<?php

declare(strict_types=1);

namespace FacilDigital\Core\PDFs;

use FacilDigital\Core\Entitlements\EntitlementRepository;
use FacilDigital\Core\Products\ProductMetadata;
use FacilDigital\Core\Security\Cpf;
use FacilDigital\Core\WooCommerce\CheckoutModule;
use setasign\Fpdi\Tcpdf\Fpdi;
use WC_Order;
use WP_User;

final class PdfGenerationService
{
    public function __construct(
        private readonly EntitlementRepository $entitlements = new EntitlementRepository(),
        private readonly PdfFileRepository $files = new PdfFileRepository(),
        private readonly PrivateStorage $storage = new PrivateStorage(),
        private readonly MasterPdfService $masters = new MasterPdfService()
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function generateForEntitlement(
        int $entitlementId
    ): array {
        $entitlement = $this->entitlements->findById(
            $entitlementId
        );

        if (!is_array($entitlement)) {
            throw new PdfGenerationException('entitlement_missing');
        }

        if (($entitlement['status'] ?? '') !== 'active') {
            throw new PdfGenerationException('entitlement_inactive');
        }

        $userId = (int) ($entitlement['user_id'] ?? 0);
        $productId = (int) ($entitlement['product_id'] ?? 0);
        $orderId = (int) ($entitlement['order_id'] ?? 0);

        if (
            $userId <= 0
            || $productId <= 0
            || $orderId <= 0
            || !ProductMetadata::isApostila($productId)
        ) {
            throw new PdfGenerationException('entitlement_invalid');
        }

        $version = ProductMetadata::get(
            $productId,
            ProductMetadata::MATERIAL_VERSION,
            '1'
        );

        $watermarkEnabled = ProductMetadata::get(
            $productId,
            ProductMetadata::WATERMARK_ENABLED,
            'yes'
        ) === 'yes';

        $passwordEnabled = ProductMetadata::get(
            $productId,
            ProductMetadata::PDF_PASSWORD_ENABLED,
            'yes'
        ) === 'yes';

        $existing = $this->files->findForEntitlementVersion(
            $entitlementId,
            $version
        );

        if (
            is_array($existing)
            && ($existing['status'] ?? '') === 'ready'
        ) {
            $existingPath = $this->storage->path(
                (string) ($existing['storage_key'] ?? '')
            );

            if (is_file($existingPath)) {
                return $existing;
            }
        }

        $storageKey = is_array($existing)
            ? (string) ($existing['storage_key'] ?? '')
            : $this->storage->generatedKey(
                $userId,
                $orderId,
                $productId,
                $version
            );

        $trackingCode = is_array($existing)
            ? (string) ($existing['tracking_code'] ?? '')
            : $this->trackingCode(
                $orderId,
                $productId
            );

        if ($storageKey === '' || $trackingCode === '') {
            throw new PdfGenerationException('pdf_identity_invalid');
        }

        $record = $this->files->ensurePending(
            $entitlement,
            $version,
            $storageKey,
            $trackingCode,
            $watermarkEnabled,
            $passwordEnabled
        );

        $pdfId = (int) ($record['id'] ?? 0);
        $this->files->markGenerating($pdfId);

        try {
            $masterPath = $this->masters->masterPath($productId);
            $order = wc_get_order($orderId);
            $user = get_userdata($userId);

            if (!$order instanceof WC_Order) {
                throw new PdfGenerationException('order_missing');
            }

            if (!$user instanceof WP_User) {
                throw new PdfGenerationException('user_missing');
            }

            $cpf = CheckoutModule::getOrderCpf($order);

            if ($passwordEnabled && !Cpf::isValid($cpf)) {
                throw new PdfGenerationException('cpf_missing_or_invalid');
            }

            $destination = $this->storage->path($storageKey);
            $tempKey = $this->storage->tempKey();
            $tempPath = $this->storage->path($tempKey);

            $this->render(
                $masterPath,
                $tempPath,
                $user,
                $order,
                $trackingCode,
                $cpf,
                $watermarkEnabled,
                $passwordEnabled
            );

            $this->storage->assertPdf($tempPath);

            if (!@rename($tempPath, $destination)) {
                if (!copy($tempPath, $destination)) {
                    throw new PdfGenerationException('pdf_move_failed');
                }
                @unlink($tempPath);
            }

            @chmod($destination, 0640);
            $this->storage->assertPdf($destination);

            $size = filesize($destination);
            $sha256 = hash_file('sha256', $destination);

            if ($size === false || !is_string($sha256)) {
                throw new PdfGenerationException('pdf_integrity_failed');
            }

            $this->files->markReady(
                $pdfId,
                (int) $size,
                $sha256
            );
        } catch (PdfGenerationException $exception) {
            $this->files->markFailed(
                $pdfId,
                $exception->errorCode()
            );
            throw $exception;
        } catch (\Throwable $exception) {
            unset($exception);
            $this->files->markFailed(
                $pdfId,
                'generation_failed'
            );
            throw new PdfGenerationException('generation_failed');
        }

        $ready = $this->files->findById($pdfId);

        if (!is_array($ready)) {
            throw new PdfGenerationException('pdf_record_missing');
        }

        return $ready;
    }

    /**
     * @return array{name:string,cpf_masked:string,order:string,tracking:string,diagonal:string}
     */
    public function watermarkData(
        WP_User $user,
        WC_Order $order,
        string $trackingCode,
        string $cpf
    ): array {
        $name = trim((string) $user->display_name);
        if ($name === '') {
            $name = 'Aluno Fácil Digital+';
        }

        $cpfMasked = Cpf::mask($cpf);
        $orderLabel = '#' . $order->get_id();

        return [
            'name' => $name,
            'cpf_masked' => $cpfMasked,
            'order' => $orderLabel,
            'tracking' => $trackingCode,
            'diagonal' => sprintf(
                '%s • PEDIDO %d • FÁCIL DIGITAL+',
                $name,
                $order->get_id()
            ),
        ];
    }

    private function render(
        string $masterPath,
        string $outputPath,
        WP_User $user,
        WC_Order $order,
        string $trackingCode,
        string $cpf,
        bool $watermarkEnabled,
        bool $passwordEnabled
    ): void {
        if (!class_exists(Fpdi::class)) {
            throw new PdfGenerationException('fpdi_unavailable');
        }

        $pdf = new Fpdi(
            'P',
            'mm',
            'A4',
            true,
            'UTF-8',
            false,
            false
        );

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetCreator('Fácil Digital+');
        $pdf->SetAuthor('Fácil Digital+');
        $pdf->SetTitle('Apostila personalizada Fácil Digital+');

        if ($passwordEnabled) {
            $ownerPassword = wp_generate_password(40, true, true);
            $pdf->SetProtection(
                ['print'],
                Cpf::normalize($cpf),
                $ownerPassword,
                2
            );
        }

        $pageCount = $pdf->setSourceFile($masterPath);

        if ($pageCount <= 0) {
            throw new PdfGenerationException('master_page_count_invalid');
        }

        $watermark = $this->watermarkData(
            $user,
            $order,
            $trackingCode,
            $cpf
        );

        for ($page = 1; $page <= $pageCount; $page++) {
            $templateId = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($templateId);

            $width = (float) ($size['width'] ?? 0);
            $height = (float) ($size['height'] ?? 0);

            if ($width <= 0 || $height <= 0) {
                throw new PdfGenerationException('master_page_size_invalid');
            }

            $orientation = $width > $height ? 'L' : 'P';
            $pdf->AddPage(
                $orientation,
                [$width, $height]
            );
            $pdf->useTemplate(
                $templateId,
                0,
                0,
                $width,
                $height
            );

            if ($watermarkEnabled) {
                $this->drawWatermark(
                    $pdf,
                    $width,
                    $height,
                    $watermark
                );
            }
        }

        $pdf->Output($outputPath, 'F');
    }

    /**
     * @param array{name:string,cpf_masked:string,order:string,tracking:string,diagonal:string} $data
     */
    private function drawWatermark(
        Fpdi $pdf,
        float $width,
        float $height,
        array $data
    ): void {
        $pdf->SetTextColor(90, 90, 90);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetXY(8, 5);
        $pdf->Cell(
            max(10, $width - 16),
            5,
            sprintf(
                'Material licenciado para: %s | CPF: %s | Pedido: %s',
                $data['name'],
                $data['cpf_masked'],
                $data['order']
            ),
            0,
            0,
            'C'
        );

        $pdf->SetTextColor(210, 210, 210);
        $pdf->SetFont('helvetica', 'B', 22);
        $pdf->StartTransform();
        $pdf->Rotate(
            35,
            $width / 2,
            $height / 2
        );
        $pdf->SetXY(12, ($height / 2) - 5);
        $pdf->Cell(
            max(10, $width - 24),
            10,
            $data['diagonal'],
            0,
            0,
            'C'
        );
        $pdf->StopTransform();

        $pdf->SetTextColor(95, 95, 95);
        $pdf->SetFont('helvetica', '', 6.5);
        $pdf->SetXY(8, max(0, $height - 10));
        $pdf->Cell(
            max(10, $width - 16),
            4,
            sprintf(
                'USO PESSOAL • DISTRIBUIÇÃO NÃO AUTORIZADA • %s',
                $data['tracking']
            ),
            0,
            0,
            'C'
        );
    }

    private function trackingCode(
        int $orderId,
        int $productId
    ): string {
        return sprintf(
            'FD-%d-%d-%s',
            $orderId,
            $productId,
            strtoupper(bin2hex(random_bytes(4)))
        );
    }
}
