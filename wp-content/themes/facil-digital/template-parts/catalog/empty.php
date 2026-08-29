<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

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
        <?php
        echo esc_html__(
            'Nenhuma apostila encontrada',
            'facil-digital'
        );
        ?>
    </h2>

    <p>
        <?php
        echo esc_html__(
            'Tente outro termo de busca ou volte ao catalogo completo.',
            'facil-digital'
        );
        ?>
    </p>

    <a
        class="fd-button fd-button--primary"
        href="<?php
            echo esc_url(
                fd_theme_get_shop_url()
            );
        ?>"
    >
        <?php
        echo esc_html__(
            'Ver todas as apostilas',
            'facil-digital'
        );
        ?>
    </a>
</div>
