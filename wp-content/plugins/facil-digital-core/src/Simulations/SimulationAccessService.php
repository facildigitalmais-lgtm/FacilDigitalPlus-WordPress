<?php

declare(strict_types=1);

namespace FacilDigital\Core\Simulations;

use FacilDigital\Core\Contests\ContestModule;
use FacilDigital\Core\Core\Capabilities;
use FacilDigital\Core\Entitlements\EntitlementService;
use FacilDigital\Core\Products\ProductMetadata;

final class SimulationAccessService
{
    public function __construct(
        private readonly SimulationRepository $simulations = new SimulationRepository(),
        private readonly EntitlementService $entitlements = new EntitlementService()
    ) {
    }

    public function canAccess(int $userId, int $simulationId): bool
    {
        if ($userId <= 0 || $simulationId <= 0) {
            return false;
        }

        if (user_can($userId, Capabilities::MANAGE_SIMULATIONS)) {
            return true;
        }

        $simulation = $this->simulations->findById($simulationId);
        if (!is_array($simulation) || ($simulation['status'] ?? '') !== 'published') {
            return false;
        }

        $activeEntitlements = $this->entitlements->activeForUser($userId);

        $linkedProductIds = array_values(
            array_unique(
                array_filter(
                    array_map(
                        'intval',
                        (array) ($simulation['product_ids'] ?? [])
                    )
                )
            )
        );

        if ($linkedProductIds !== []) {
            foreach ($activeEntitlements as $entitlement) {
                $productId = (int) ($entitlement['product_id'] ?? 0);

                if (
                    $productId <= 0
                    || !in_array($productId, $linkedProductIds, true)
                    || !ProductMetadata::isApostila($productId)
                ) {
                    continue;
                }

                if (
                    ProductMetadata::get(
                        $productId,
                        ProductMetadata::HAS_SIMULATIONS,
                        'no'
                    ) !== 'yes'
                ) {
                    continue;
                }

                return true;
            }

            return false;
        }

        $contestTermId = (int) ($simulation['contest_term_id'] ?? 0);
        $position = $this->normalize((string) ($simulation['position_name'] ?? ''));

        foreach ($activeEntitlements as $entitlement) {
            $productId = (int) ($entitlement['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            if (ProductMetadata::get($productId, ProductMetadata::HAS_SIMULATIONS, 'no') !== 'yes') {
                continue;
            }
            if (
                $contestTermId > 0
                && !has_term($contestTermId, ContestModule::TAXONOMY, $productId)
            ) {
                continue;
            }
            $productPosition = $this->normalize(
                ProductMetadata::get($productId, ProductMetadata::POSITION_NAME)
            );
            if ($position !== '' && $productPosition !== '' && $position !== $productPosition) {
                continue;
            }
            return true;
        }

        return false;
    }

    private function normalize(string $value): string
    {
        $value = remove_accents(sanitize_text_field($value));
        return strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? $value));
    }
}
