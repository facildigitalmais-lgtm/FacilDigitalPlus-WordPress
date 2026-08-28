<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>

<section class="fd-home-hero">
    <div class="fd-container fd-home-hero__grid">
        <div class="fd-home-hero__content">
            <span class="fd-eyebrow">
                Sua preparacao com mais foco
            </span>

            <h1>
                Apostilas e simulados
                para transformar estudo
                em resultado.
            </h1>

            <p class="fd-home-hero__lead">
                Encontre materiais digitais
                organizados para concursos
                publicos e estude onde estiver.
            </p>

            <form
                class="fd-home-search"
                role="search"
                method="get"
                action="<?php
                    echo esc_url(
                        home_url('/')
                    );
                ?>"
            >
                <label
                    class="fd-sr-only"
                    for="fd-home-search-input"
                >
                    Buscar apostila
                </label>

                <div class="fd-home-search__field">
                    <?php
                    echo fd_theme_icon(
                        'search'
                    );
                    ?>

                    <input
                        id="fd-home-search-input"
                        type="search"
                        name="s"
                        placeholder="Qual concurso ou cargo voce procura?"
                    >

                    <input
                        type="hidden"
                        name="post_type"
                        value="product"
                    >

                    <button
                        type="submit"
                        class="fd-button fd-button--primary"
                    >
                        Buscar
                    </button>
                </div>
            </form>

            <div class="fd-home-hero__actions">
                <a
                    class="fd-button fd-button--primary fd-button--large"
                    href="<?php
                        echo esc_url(
                            fd_theme_get_shop_url()
                        );
                    ?>"
                >
                    Ver todas as apostilas
                </a>

                <a
                    class="fd-button fd-button--secondary fd-button--large"
                    href="<?php
                        echo esc_url(
                            fd_theme_get_login_url()
                        );
                    ?>"
                >
                    Area do aluno
                </a>
            </div>

            <ul class="fd-home-hero__trust">
                <li>
                    <?php
                    echo fd_theme_icon(
                        'check'
                    );
                    ?>
                    Acesso digital
                </li>

                <li>
                    <?php
                    echo fd_theme_icon(
                        'check'
                    );
                    ?>
                    Compra segura
                </li>

                <li>
                    <?php
                    echo fd_theme_icon(
                        'check'
                    );
                    ?>
                    Simulados online
                </li>
            </ul>
        </div>

        <div
            class="fd-home-hero__visual"
            aria-hidden="true"
        >
            <div class="fd-home-book">
                <span class="fd-home-book__brand">
                    Facil Digital+
                </span>

                <div class="fd-home-book__middle">
                    <span>
                        Preparacao para
                        concursos publicos
                    </span>

                    <strong>
                        Apostilas
                        + Simulados
                    </strong>
                </div>

                <span class="fd-home-book__footer">
                    Estude. Pratique. Evolua.
                </span>
            </div>
        </div>
    </div>
</section>