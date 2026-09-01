<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$product =
    $args['product']
    ?? null;

if (
    !$product instanceof WC_Product
    || !fd_theme_core_product_metadata_available()
) {
    return;
}

$productId =
    (int) $product->get_id();

$hasSimulations =
    \FacilDigital\Core\Products\ProductMetadata::get(
        $productId,
        \FacilDigital\Core\Products\ProductMetadata::HAS_SIMULATIONS,
        'no'
    ) === 'yes';

if (!$hasSimulations) {
    return;
}

?>

<section
    class="fd-product-section fd-product-simulations"
    id="simulados"
>
    <div>
        <span class="fd-eyebrow">
            <?php
            echo esc_html__(
                'Simulados',
                'facil-digital'
            );
            ?>
        </span>

        <h2>
            <?php
            echo esc_html__(
                'Prática integrada à plataforma',
                'facil-digital'
            );
            ?>
        </h2>

        <p>
            <?php
            echo esc_html__(
                'Os simulados vinculados a esta apostila ficam disponíveis na área do aluno conforme o acesso ativo. Tentativas, resultados, histórico e ranking permanecem vinculados à sua conta.',
                'facil-digital'
            );
            ?>
        </p>
    </div>

    <div
        class="fd-product-simulations__preview"
        aria-hidden="true"
    >
        <span>
            01
        </span>

        <strong>
            Simulados online
        </strong>

        <small>
            Acesso pela área do aluno
        </small>
    </div>
</section>
