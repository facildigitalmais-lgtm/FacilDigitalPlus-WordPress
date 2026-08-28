<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$searchId =
    wp_unique_id(
        'fd-search-field-'
    );

?>

<form
    role="search"
    method="get"
    class="fd-search-form"
    action="<?php
        echo esc_url(
            home_url('/')
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
            'Buscar no site',
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
            name="s"
            value="<?php
                echo esc_attr(
                    get_search_query()
                );
            ?>"
            placeholder="<?php
                echo esc_attr__(
                    'Busque por concurso, cargo ou apostila...',
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