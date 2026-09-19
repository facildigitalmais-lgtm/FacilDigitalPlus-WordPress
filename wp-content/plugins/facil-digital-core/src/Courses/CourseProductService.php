<?php

declare(strict_types=1);

namespace FacilDigital\Core\Courses;

use RuntimeException;
use WC_Product;
use WC_Product_Simple;

final class CourseProductService
{
    public function __construct(
        private readonly CourseRepository $courses =
            new CourseRepository()
    ) {
    }

    /**
     * @param array<string, mixed> $courseData
     * @param array<string, mixed> $productData
     * @return array{course_id:int, product_id:int}
     */
    public function createCourseProduct(
        string $title,
        string $slug,
        string|int|float $regularPrice,
        array $courseData = [],
        array $productData = []
    ): array {
        $title = sanitize_text_field($title);
        $slug = sanitize_title(
            $slug !== ''
                ? $slug
                : $title
        );

        if ($title === '' || $slug === '') {
            throw new RuntimeException(
                'course_product_identity_invalid'
            );
        }

        if (
            $this->courses->findBySlug(
                $slug
            ) !== null
        ) {
            throw new RuntimeException(
                'course_product_slug_duplicate'
            );
        }

        $product = new WC_Product_Simple();

        $this->configureNewProduct(
            $product,
            $title,
            $regularPrice,
            $productData
        );

        $productId = (int) $product->save();

        if ($productId <= 0) {
            throw new RuntimeException(
                'course_product_create_failed'
            );
        }

        try {
            $courseId = $this->courses->create(
                $productId,
                $title,
                $slug,
                $courseData
            );
        } catch (\Throwable $exception) {
            $product->delete(true);

            throw $exception;
        }

        return [
            'course_id' => $courseId,
            'product_id' => $productId,
        ];
    }

    /**
     * @param array<string, mixed> $courseData
     */
    public function linkExistingProduct(
        int $productId,
        string $title,
        string $slug,
        array $courseData = []
    ): int {
        $product = $this->requireSimpleProduct(
            $productId
        );

        $existing =
            $this->courses->findByProductId(
                $productId
            );

        if (is_array($existing)) {
            return (int) (
                $existing['id']
                ?? 0
            );
        }

        $title = sanitize_text_field($title);
        $slug = sanitize_title(
            $slug !== ''
                ? $slug
                : $title
        );

        if ($title === '' || $slug === '') {
            throw new RuntimeException(
                'course_product_identity_invalid'
            );
        }

        if (
            $this->courses->findBySlug(
                $slug
            ) !== null
        ) {
            throw new RuntimeException(
                'course_product_slug_duplicate'
            );
        }

        $courseId = $this->courses->create(
            $productId,
            $title,
            $slug,
            $courseData
        );

        try {
            $this->applyCourseProductFlags(
                $product
            );

            $savedId = (int) $product->save();

            if ($savedId <= 0) {
                throw new RuntimeException(
                    'course_product_update_failed'
                );
            }
        } catch (\Throwable $exception) {
            $this->courses->delete(
                $courseId
            );

            throw $exception;
        }

        return $courseId;
    }

    public function isCourseProduct(
        int $productId
    ): bool {
        if ($productId <= 0) {
            return false;
        }

        return $this->courses
            ->findByProductId(
                $productId
            ) !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function courseForProduct(
        int $productId
    ): ?array {
        if ($productId <= 0) {
            return null;
        }

        return $this->courses
            ->findByProductId(
                $productId
            );
    }

    public function productForCourse(
        int $courseId
    ): ?WC_Product {
        $course = $this->courses->findById(
            $courseId
        );

        if (!is_array($course)) {
            return null;
        }

        $product = wc_get_product(
            (int) (
                $course['product_id']
                ?? 0
            )
        );

        return $product instanceof WC_Product
            ? $product
            : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateProductForCourse(
        int $courseId,
        array $data
    ): void {
        $product = $this->productForCourse(
            $courseId
        );

        if (!$product instanceof WC_Product_Simple) {
            throw new RuntimeException(
                'course_product_missing'
            );
        }

        if (array_key_exists('name', $data)) {
            $name = sanitize_text_field(
                (string) $data['name']
            );

            if ($name === '') {
                throw new RuntimeException(
                    'course_product_name_invalid'
                );
            }

            $product->set_name($name);
        }

        if (
            array_key_exists(
                'regular_price',
                $data
            )
        ) {
            $price = $this->normalizePrice(
                $data['regular_price']
            );

            $product->set_regular_price(
                $price
            );

            $product->set_price(
                $price
            );
        }

        if (array_key_exists('status', $data)) {
            $product->set_status(
                $this->normalizeStatus(
                    (string) $data['status']
                )
            );
        }

        if (
            array_key_exists(
                'catalog_visibility',
                $data
            )
        ) {
            $product->set_catalog_visibility(
                $this->normalizeVisibility(
                    (string) $data[
                        'catalog_visibility'
                    ]
                )
            );
        }

        if (
            array_key_exists(
                'description',
                $data
            )
        ) {
            $product->set_description(
                wp_kses_post(
                    (string) $data[
                        'description'
                    ]
                )
            );
        }

        if (
            array_key_exists(
                'short_description',
                $data
            )
        ) {
            $product->set_short_description(
                wp_kses_post(
                    (string) $data[
                        'short_description'
                    ]
                )
            );
        }

        if (
            array_key_exists(
                'image_id',
                $data
            )
        ) {
            $product->set_image_id(
                max(
                    0,
                    (int) $data['image_id']
                )
            );
        }

        $this->applyCourseProductFlags(
            $product
        );

        $savedId = (int) $product->save();

        if ($savedId <= 0) {
            throw new RuntimeException(
                'course_product_update_failed'
            );
        }
    }

    private function requireSimpleProduct(
        int $productId
    ): WC_Product_Simple {
        if ($productId <= 0) {
            throw new RuntimeException(
                'course_product_invalid'
            );
        }

        $product = wc_get_product(
            $productId
        );

        if (!$product instanceof WC_Product) {
            throw new RuntimeException(
                'course_product_not_found'
            );
        }

        if (!$product instanceof WC_Product_Simple) {
            throw new RuntimeException(
                'course_product_type_unsupported'
            );
        }

        return $product;
    }

    /**
     * @param array<string, mixed> $productData
     */
    private function configureNewProduct(
        WC_Product_Simple $product,
        string $title,
        string|int|float $regularPrice,
        array $productData
    ): void {
        $price = $this->normalizePrice(
            $regularPrice
        );

        $product->set_name($title);
        $product->set_regular_price($price);
        $product->set_price($price);

        $product->set_status(
            $this->normalizeStatus(
                (string) (
                    $productData['status']
                    ?? 'draft'
                )
            )
        );

        $product->set_catalog_visibility(
            $this->normalizeVisibility(
                (string) (
                    $productData[
                        'catalog_visibility'
                    ]
                    ?? 'visible'
                )
            )
        );

        if (
            isset($productData['description'])
        ) {
            $product->set_description(
                wp_kses_post(
                    (string) $productData[
                        'description'
                    ]
                )
            );
        }

        if (
            isset(
                $productData[
                    'short_description'
                ]
            )
        ) {
            $product->set_short_description(
                wp_kses_post(
                    (string) $productData[
                        'short_description'
                    ]
                )
            );
        }

        if (
            isset($productData['image_id'])
        ) {
            $product->set_image_id(
                max(
                    0,
                    (int) $productData[
                        'image_id'
                    ]
                )
            );
        }

        $this->applyCourseProductFlags(
            $product
        );
    }

    private function applyCourseProductFlags(
        WC_Product_Simple $product
    ): void {
        $product->set_virtual(true);
        $product->set_downloadable(false);
        $product->set_sold_individually(true);
        $product->set_manage_stock(false);
        $product->set_stock_status(
            'instock'
        );
    }

    private function normalizePrice(
        mixed $value
    ): string {
        $price = wc_format_decimal(
            $value,
            wc_get_price_decimals()
        );

        if (
            $price === ''
            || !is_numeric($price)
            || (float) $price < 0
        ) {
            throw new RuntimeException(
                'course_product_price_invalid'
            );
        }

        return $price;
    }

    private function normalizeStatus(
        string $status
    ): string {
        $status = sanitize_key($status);

        if (
            !in_array(
                $status,
                [
                    'draft',
                    'publish',
                    'private',
                    'pending',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'course_product_status_invalid'
            );
        }

        return $status;
    }

    private function normalizeVisibility(
        string $visibility
    ): string {
        $visibility = sanitize_key(
            $visibility
        );

        if (
            !in_array(
                $visibility,
                [
                    'visible',
                    'catalog',
                    'search',
                    'hidden',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'course_product_visibility_invalid'
            );
        }

        return $visibility;
    }
}