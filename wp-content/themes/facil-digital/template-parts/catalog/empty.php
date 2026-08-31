<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$hasFilters =
    function_exists(
        'fd_theme_catalog_has_active_filters'
    )
        && fd_theme_catalog_has_active_filters();

$title =
    $hasFilters
        ? 'Nenhuma apostila corresponde à sua busca'
        : 'Nenhuma apostila disponível';

$text =
    $hasFilters
        ? 'Ajuste a busca ou os filtros para encontrar outros materiais.'
        : 'Ainda não há apostilas publicadas neste catálogo.';

$url =
    $hasFilters
        ? fd_theme_get_shop_url()
        : home_url('/');

$button =
    $hasFilters
        ? 'Limpar busca e filtros'
        : 'Voltar para o início';

?>

<div class="fd-catalog-empty">
    <span class="fd-catalog-empty__icon">
        <?php
        echo fd_theme_icon(
            'search'
        );
        ?>
    </span>

    <h2>
        <?php echo esc_html($title); ?>
    </h2>

    <p>
        <?php echo esc_html($text); ?>
    </p>

    <a
        class="fd-button fd-button--primary"
        href="<?php echo esc_url($url); ?>"
    >
        <?php echo esc_html($button); ?>
    </a>
</div>
