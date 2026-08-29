<?php

declare(strict_types=1);

namespace FacilDigital\Core\Products;

use FacilDigital\Core\Contests\ContestModule;
use WC_Product;

final class ProductRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public function find(int $productId): ?array
    {
        $product = wc_get_product($productId);

        if (!$product instanceof WC_Product) {
            return null;
        }

        $terms = wp_get_post_terms(
            $productId,
            ContestModule::TAXONOMY,
            [
                'fields' => 'all',
            ]
        );

        if (is_wp_error($terms)) {
            $terms = [];
        }

        return [
            'id' => $productId,
            'name' => $product->get_name(),
            'slug' => $product->get_slug(),
            'price' => $product->get_price(),
            'is_apostila' => ProductMetadata::isApostila($productId),
            'position_name' => ProductMetadata::get(
                $productId,
                ProductMetadata::POSITION_NAME
            ),
            'board' => ProductMetadata::get(
                $productId,
                ProductMetadata::BOARD
            ),
            'exam_year' => ProductMetadata::get(
                $productId,
                ProductMetadata::EXAM_YEAR
            ),
            'page_count' => ProductMetadata::get(
                $productId,
                ProductMetadata::PAGE_COUNT
            ),
            'material_version' => ProductMetadata::get(
                $productId,
                ProductMetadata::MATERIAL_VERSION,
                '1'
            ),
            'has_simulations' => ProductMetadata::get(
                $productId,
                ProductMetadata::HAS_SIMULATIONS,
                'no'
            ) === 'yes',
            'download_limit' => (int) ProductMetadata::get(
                $productId,
                ProductMetadata::DOWNLOAD_LIMIT,
                '5'
            ),
            'generate_personalized_pdf' => ProductMetadata::get(
                $productId,
                ProductMetadata::GENERATE_PERSONALIZED_PDF,
                'yes'
            ) === 'yes',
            'watermark_enabled' => ProductMetadata::get(
                $productId,
                ProductMetadata::WATERMARK_ENABLED,
                'yes'
            ) === 'yes',
            'pdf_password_enabled' => ProductMetadata::get(
                $productId,
                ProductMetadata::PDF_PASSWORD_ENABLED,
                'yes'
            ) === 'yes',
            'contests' => array_map(
                static fn (\WP_Term $term): array => [
                    'id' => (int) $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                ],
                $terms
            ),
        ];
    }
}
