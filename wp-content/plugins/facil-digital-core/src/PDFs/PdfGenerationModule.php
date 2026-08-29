<?php

declare(strict_types=1);

namespace FacilDigital\Core\PDFs;

use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Entitlements\EntitlementRepository;
use FacilDigital\Core\Products\ProductMetadata;

final class PdfGenerationModule implements ModuleInterface
{
    public const ACTION = 'facil_digital_generate_pdf';
    public const GROUP = 'facil-digital-pdf';

    public function __construct(
        private readonly PdfGenerationService $service = new PdfGenerationService(),
        private readonly EntitlementRepository $entitlements = new EntitlementRepository()
    ) {
    }

    public function register(): void
    {
        add_action(
            'facil_digital_entitlement_granted',
            [$this, 'enqueue'],
            10,
            1
        );

        add_action(
            'facil_digital_master_pdf_updated',
            [$this, 'enqueueForProduct'],
            10,
            1
        );

        add_action(
            self::ACTION,
            [$this, 'run'],
            10,
            1
        );
    }

    public function enqueue(int $entitlementId): int
    {
        if ($entitlementId <= 0) {
            return 0;
        }

        $entitlement = $this->entitlements->findById($entitlementId);
        if (!is_array($entitlement)) {
            return 0;
        }

        $productId = (int) ($entitlement['product_id'] ?? 0);
        if (
            ProductMetadata::get(
                $productId,
                ProductMetadata::MASTER_PDF_KEY
            ) === ''
        ) {
            return 0;
        }

        $args = [$entitlementId];

        if (
            function_exists('as_has_scheduled_action')
            && function_exists('as_enqueue_async_action')
        ) {
            if (
                as_has_scheduled_action(
                    self::ACTION,
                    $args,
                    self::GROUP
                )
            ) {
                return 1;
            }

            return (int) as_enqueue_async_action(
                self::ACTION,
                $args,
                self::GROUP,
                true,
                10
            );
        }

        if (!wp_next_scheduled(self::ACTION, $args)) {
            wp_schedule_single_event(
                time() + 5,
                self::ACTION,
                $args
            );
        }

        return 1;
    }

    public function enqueueForProduct(int $productId): void
    {
        foreach (
            $this->entitlements->activeForProduct($productId)
            as $entitlement
        ) {
            $this->enqueue(
                (int) ($entitlement['id'] ?? 0)
            );
        }
    }

    public function run(int $entitlementId): void
    {
        try {
            $this->service->generateForEntitlement(
                $entitlementId
            );
        } catch (PdfGenerationException $exception) {
            error_log(
                sprintf(
                    'FD_PDF_GENERATION_FAILED entitlement_id=%d error_code=%s',
                    $entitlementId,
                    sanitize_key($exception->errorCode())
                )
            );
        } catch (\Throwable) {
            error_log(
                sprintf(
                    'FD_PDF_GENERATION_FAILED entitlement_id=%d error_code=unknown',
                    $entitlementId
                )
            );
        }
    }
}
