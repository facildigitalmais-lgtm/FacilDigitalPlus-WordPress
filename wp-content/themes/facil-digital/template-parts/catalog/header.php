<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$title =
    fd_theme_catalog_title();

$description =
    fd_theme_catalog_description();

?>

<header class="fd-catalog-header">
    <span class="fd-eyebrow">
        <?php
        echo esc_html__(
            'Catálogo Fácil Digital+',
            'facil-digital'
        );
        ?>
    </span>

    <h1>
        <?php
        echo esc_html(
            $title
        );
        ?>
    </h1>

    <p>
        <?php
        echo esc_html(
            $description
        );
        ?>
    </p>

    <form
        class="fd-catalog-search"
        role="search"
        method="get"
        action="<?php
            echo esc_url(
                fd_theme_get_shop_url()
            );
        ?>"
    >
        <label
            class="fd-sr-only"
            for="fd-catalog-search"
        >
            <?php
            echo esc_html__(
                'Buscar apostilas',
                'facil-digital'
            );
            ?>
        </label>

        <div class="fd-catalog-search__field">
            <?php
            echo fd_theme_icon(
                'search'
            );
            ?>

            <input
                id="fd-catalog-search"
                type="search"
                name="busca"
                value="<?php
                    echo esc_attr(
                        fd_theme_catalog_current_search()
                    );
                ?>"
                placeholder="<?php
                    echo esc_attr__(
                        'Busque por concurso, banca ou cargo...',
                        'facil-digital'
                    );
                ?>"
            >


            <button
                class="fd-button fd-button--primary"
                type="submit"
            >
                <?php
                echo esc_html__(
                    'Buscar',
                    'facil-digital'
                );
                ?>
            </button>
        </div>
    </form>
</header>
