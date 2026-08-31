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
                Sua preparação começa aqui
            </span>

            <h1>
                Apostilas e simulados
                para estudar com foco
                no seu concurso.
            </h1>

            <p class="fd-home-hero__lead">
                Encontre apostilas digitais para
                seu concurso, pratique com simulados
                e acompanhe sua preparação em um só lugar.
            </p>

            <form
                class="fd-home-search"
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
                        name="busca"
                        placeholder="Qual concurso ou cargo você procura?"
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
                    Área do aluno
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
                        Preparação para
                        concursos públicos
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
