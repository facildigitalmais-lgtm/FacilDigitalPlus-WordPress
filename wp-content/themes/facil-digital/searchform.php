<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$searchId =
    wp_unique_id(
        'fd-search-field-'
    );

$currentSearch = '';

if (
    function_exists(
        'fd_theme_catalog_current_search'
    )
) {
    $currentSearch =
        fd_theme_catalog_current_search();
} elseif (
    isset($_GET['busca'])
    && is_string($_GET['busca'])
) {
    $currentSearch =
        sanitize_text_field(
            wp_unslash(
                $_GET['busca']
            )
        );
}

?>

<form
    role="search"
    method="get"
    class="fd-search-form"
    action="<?php
        echo esc_url(
            fd_theme_get_shop_url()
        );
    ?>"
>
    <label
        class="fd-sr-only"
        for="<?php
            echo esc_attr(
                $searchId
            );
        ?>"
    >
        <?php
        echo esc_html__(
            'Buscar apostilas',
            'facil-digital'
        );
        ?>
    </label>

    <div class="fd-search-form__field">
        <?php
        echo fd_theme_icon(
            'search'
        );
        ?>

        <input
            id="<?php
                echo esc_attr(
                    $searchId
                );
            ?>"
            type="search"
            class="fd-search-form__input"
            name="busca"
            value="<?php
                echo esc_attr(
                    $currentSearch
                );
            ?>"
            placeholder="<?php
                echo esc_attr__(
                    'Busque por concurso, cargo, banca ou apostila...',
                    'facil-digital'
                );
            ?>"
            autocomplete="off"
        >

        <button
            type="submit"
            class="fd-button fd-button--primary fd-search-form__submit"
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
