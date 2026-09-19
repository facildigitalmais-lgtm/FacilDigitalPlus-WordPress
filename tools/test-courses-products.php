<?php

declare(strict_types=1);

use FacilDigital\Core\Core\Database;
use FacilDigital\Core\Courses\CourseProductService;
use FacilDigital\Core\Courses\CourseRepository;

if (wp_get_environment_type() !== 'development') {
    throw new RuntimeException(
        'Este teste so pode rodar em development.'
    );
}

global $wpdb;

$productIds = [];
$courseIds = [];
$failure = null;

$assert = static function (
    bool $condition,
    string $label
): void {
    if (!$condition) {
        throw new RuntimeException(
            'FAIL: ' . $label
        );
    }

    echo 'PASS: ' . $label . PHP_EOL;
};

$expectRuntimeException =
    static function (
        callable $callback,
        string $message,
        string $label
    ) use ($assert): void {
        $matched = false;

        try {
            $callback();
        } catch (RuntimeException $exception) {
            $matched =
                $exception->getMessage()
                === $message;
        }

        $assert(
            $matched,
            $label
        );
    };

$courses = new CourseRepository();
$service = new CourseProductService(
    $courses
);

try {
    $suffix = strtolower(
        wp_generate_password(
            8,
            false,
            false
        )
    );

    $normalProduct =
        new WC_Product_Simple();

    $normalProduct->set_name(
        'FD NORMAL PRODUCT ' . $suffix
    );

    $normalProduct->set_regular_price(
        '25.00'
    );

    $normalProduct->set_price(
        '25.00'
    );

    $normalProduct->set_status(
        'draft'
    );

    $normalProduct->set_virtual(
        false
    );

    $normalProductId =
        (int) $normalProduct->save();

    $productIds[] =
        $normalProductId;

    $assert(
        $normalProductId > 0,
        'produto comum criado'
    );

    $assert(
        !$service->isCourseProduct(
            $normalProductId
        ),
        'produto comum nao e identificado como curso'
    );

    $created =
        $service->createCourseProduct(
            'FD Curso Woo ' . $suffix,
            'fd-curso-woo-' . $suffix,
            '149.90',
            [
                'short_description' =>
                    'Curso integrado ao WooCommerce',
                'workload_minutes' =>
                    600,
                'completion_threshold' =>
                    95,
                'navigation_mode' =>
                    'free',
                'certificate_enabled' =>
                    true,
                'status' =>
                    'draft',
            ],
            [
                'status' =>
                    'draft',
                'catalog_visibility' =>
                    'visible',
                'description' =>
                    '<p>Descricao comercial.</p>',
                'short_description' =>
                    '<p>Resumo comercial.</p>',
            ]
        );

    $courseId =
        (int) $created['course_id'];

    $courseProductId =
        (int) $created['product_id'];

    $courseIds[] = $courseId;
    $productIds[] = $courseProductId;

    $assert(
        $courseId > 0
        && $courseProductId > 0,
        'curso e produto WooCommerce criados juntos'
    );

    $course = $courses->findById(
        $courseId
    );

    $assert(
        is_array($course)
        && (int) (
            $course['product_id']
            ?? 0
        ) === $courseProductId,
        'curso referencia produto correto'
    );

    $assert(
        $service->isCourseProduct(
            $courseProductId
        ),
        'produto criado e identificado como curso'
    );

    $courseByProduct =
        $service->courseForProduct(
            $courseProductId
        );

    $assert(
        is_array($courseByProduct)
        && (int) (
            $courseByProduct['id']
            ?? 0
        ) === $courseId,
        'curso recuperado pelo produto'
    );

    $courseProduct =
        $service->productForCourse(
            $courseId
        );

    $assert(
        $courseProduct
            instanceof WC_Product_Simple
        && $courseProduct->get_id()
            === $courseProductId,
        'produto recuperado pelo curso'
    );

    $assert(
        $courseProduct->is_virtual()
        && !$courseProduct->is_downloadable()
        && $courseProduct
            ->is_sold_individually(),
        'produto curso usa flags comerciais corretas'
    );

    $assert(
        !$courseProduct->get_manage_stock()
        && $courseProduct->is_in_stock(),
        'produto curso dispensa estoque fisico'
    );

    $assert(
        (float) $courseProduct
            ->get_regular_price()
        === 149.9,
        'preco inicial do curso persistido'
    );

    $assert(
        $courseProduct->get_status()
        === 'draft',
        'produto curso inicia como draft'
    );

    $service->updateProductForCourse(
        $courseId,
        [
            'name' =>
                'FD Curso Woo Atualizado '
                . $suffix,
            'regular_price' =>
                '179.90',
            'status' =>
                'publish',
            'catalog_visibility' =>
                'catalog',
            'description' =>
                '<p>Descricao atualizada.</p>',
            'short_description' =>
                '<p>Resumo atualizado.</p>',
        ]
    );

    $courseProduct = wc_get_product(
        $courseProductId
    );

    $assert(
        $courseProduct
            instanceof WC_Product_Simple
        && $courseProduct->get_name()
            === 'FD Curso Woo Atualizado '
                . $suffix
        && (float) $courseProduct
            ->get_regular_price()
            === 179.9
        && $courseProduct->get_status()
            === 'publish'
        && $courseProduct
            ->get_catalog_visibility()
            === 'catalog',
        'produto curso atualizado por CRUD WooCommerce'
    );

    $assert(
        $courseProduct->is_virtual()
        && !$courseProduct->is_downloadable()
        && $courseProduct
            ->is_sold_individually(),
        'atualizacao preserva flags de curso'
    );

    $expectRuntimeException(
        static function () use (
            $service,
            $courseId
        ): void {
            $service->updateProductForCourse(
                $courseId,
                [
                    'regular_price' =>
                        '-1',
                ]
            );
        },
        'course_product_price_invalid',
        'preco negativo de curso rejeitado'
    );

    $expectRuntimeException(
        static function () use (
            $service,
            $courseId
        ): void {
            $service->updateProductForCourse(
                $courseId,
                [
                    'catalog_visibility' =>
                        'invalid',
                ]
            );
        },
        'course_product_visibility_invalid',
        'visibilidade invalida rejeitada'
    );

    $linkedProduct =
        new WC_Product_Simple();

    $linkedProduct->set_name(
        'FD Existing Product '
        . $suffix
    );

    $linkedProduct->set_regular_price(
        '79.90'
    );

    $linkedProduct->set_price(
        '79.90'
    );

    $linkedProduct->set_status(
        'draft'
    );

    $linkedProduct->set_virtual(
        false
    );

    $linkedProduct->set_downloadable(
        true
    );

    $linkedProductId =
        (int) $linkedProduct->save();

    $productIds[] =
        $linkedProductId;

    $linkedCourseId =
        $service->linkExistingProduct(
            $linkedProductId,
            'FD Curso Vinculado '
                . $suffix,
            'fd-curso-vinculado-'
                . $suffix,
            [
                'workload_minutes' =>
                    300,
            ]
        );

    $courseIds[] =
        $linkedCourseId;

    $assert(
        $linkedCourseId > 0,
        'produto existente vinculado a curso'
    );

    $assert(
        $service->isCourseProduct(
            $linkedProductId
        ),
        'produto existente passa a ser curso'
    );

    $linkedProductReloaded =
        wc_get_product(
            $linkedProductId
        );

    $assert(
        $linkedProductReloaded
            instanceof WC_Product_Simple
        && $linkedProductReloaded
            ->is_virtual()
        && !$linkedProductReloaded
            ->is_downloadable()
        && $linkedProductReloaded
            ->is_sold_individually(),
        'vinculo normaliza flags do produto existente'
    );

    $sameLinkedCourseId =
        $service->linkExistingProduct(
            $linkedProductId,
            'Nome Ignorado no Retry',
            'slug-ignorado-no-retry'
        );

    $assert(
        $sameLinkedCourseId
        === $linkedCourseId,
        'vinculo de produto existente e idempotente'
    );

    $normalReloaded =
        wc_get_product(
            $normalProductId
        );

    $assert(
        $normalReloaded
            instanceof WC_Product_Simple
        && !$normalReloaded
            ->is_virtual()
        && !$service->isCourseProduct(
            $normalProductId
        ),
        'produto comum permanece inalterado'
    );

    $entitlementsTable =
        Database::table(
            'entitlements'
        );

    $courseEntitlements =
        (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$entitlementsTable}
                 WHERE product_id IN (%d, %d)",
                $courseProductId,
                $linkedProductId
            )
        );

    $assert(
        $courseEntitlements === 0,
        'produto curso nao cria entitlement de apostila'
    );

    $variableProduct =
        new WC_Product_Variable();

    $variableProduct->set_name(
        'FD Variable Product '
        . $suffix
    );

    $variableProduct->set_status(
        'draft'
    );

    $variableProductId =
        (int) $variableProduct->save();

    $productIds[] =
        $variableProductId;

    $expectRuntimeException(
        static function () use (
            $service,
            $variableProductId,
            $suffix
        ): void {
            $service->linkExistingProduct(
                $variableProductId,
                'Curso Variavel',
                'curso-variavel-' . $suffix
            );
        },
        'course_product_type_unsupported',
        'produto variavel nao pode ser vinculado nesta versao'
    );

    $expectRuntimeException(
        static function () use (
            $service,
            $courseId
        ): void {
            $service->updateProductForCourse(
                $courseId,
                [
                    'status' =>
                        'trash',
                ]
            );
        },
        'course_product_status_invalid',
        'status comercial invalido rejeitado'
    );

    echo 'COURSES_PRODUCT_LINKAGE=PASS'
        . PHP_EOL;

    echo 'COURSES_PRODUCT_COEXISTENCE=PASS'
        . PHP_EOL;

    echo 'COURSES_WOOCOMMERCE_INTEGRATION=PASS'
        . PHP_EOL;
} catch (Throwable $exception) {
    $failure = $exception;

    fwrite(
        STDERR,
        $exception->getMessage()
        . PHP_EOL
    );
} finally {
    foreach (
        array_reverse($courseIds)
        as $courseId
    ) {
        $wpdb->delete(
            Database::table(
                'courses'
            ),
            ['id' => $courseId],
            ['%d']
        );
    }

    foreach (
        array_reverse($productIds)
        as $productId
    ) {
        $product = wc_get_product(
            $productId
        );

        if ($product instanceof WC_Product) {
            $product->delete(true);
        }
    }

    echo 'FIXTURES_CLEANUP=OK'
        . PHP_EOL;
}

if ($failure instanceof Throwable) {
    exit(1);
}