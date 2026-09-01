<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$isLoggedIn =
    is_user_logged_in();

$studentAreaUrl =
    $isLoggedIn
        ? fd_theme_get_account_url()
        : fd_theme_get_login_url();

$studentAreaLabel =
    $isLoggedIn
        ? 'Minha conta'
        : 'Área do aluno';

?>

<section class="fd-home-hero fd-home-hero--uxa">
    <div class="fd-container fd-home-hero__grid">
        <div class="fd-home-hero__content">
            <span class="fd-eyebrow">
                Sua preparação começa aqui
            </span>

            <h1>
                Estude com direção.
                <span>
                    Conquiste a sua vaga.
                </span>
            </h1>

            <p class="fd-home-hero__lead">
                Apostilas digitais e simulados para
                organizar seus estudos, praticar questões
                e acompanhar sua preparação em um só lugar.
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
                        autocomplete="off"
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
                    Explorar apostilas
                </a>

                <a
                    class="fd-button fd-button--secondary fd-button--large"
                    href="<?php
                        echo esc_url(
                            $studentAreaUrl
                        );
                    ?>"
                >
                    <?php
                    echo esc_html(
                        $studentAreaLabel
                    );
                    ?>
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
                        'lock'
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
            <span class="fd-uxa-orbit fd-uxa-orbit--one"></span>
            <span class="fd-uxa-orbit fd-uxa-orbit--two"></span>

            <div class="fd-study-dashboard">
                <div class="fd-study-dashboard__top">
                    <div>
                        <span class="fd-study-dashboard__eyebrow">
                            Fácil Digital+
                        </span>

                        <strong>
                            Minha preparação
                        </strong>
                    </div>

                    <span class="fd-study-dashboard__status">
                        Em andamento
                    </span>
                </div>

                <div class="fd-study-dashboard__progress">
                    <div>
                        <span>
                            Plano de estudos
                        </span>

                        <strong>
                            Exemplo visual
                        </strong>
                    </div>

                    <div class="fd-study-dashboard__track">
                        <span></span>
                    </div>
                </div>

                <div class="fd-study-dashboard__cards">
                    <div class="fd-study-mini-card fd-study-mini-card--blue">
                        <span>
                            Apostilas
                        </span>

                        <strong>
                            Organize
                        </strong>
                    </div>

                    <div class="fd-study-mini-card fd-study-mini-card--mint">
                        <span>
                            Simulados
                        </span>

                        <strong>
                            Pratique
                        </strong>
                    </div>

                    <div class="fd-study-mini-card fd-study-mini-card--violet">
                        <span>
                            Resultados
                        </span>

                        <strong>
                            Evolua
                        </strong>
                    </div>
                </div>

                <div class="fd-study-dashboard__activity">
                    <div class="fd-study-dashboard__activity-icon">
                        <?php
                        echo fd_theme_icon(
                            'check'
                        );
                        ?>
                    </div>

                    <div>
                        <span>
                            Tudo em um só lugar
                        </span>

                        <strong>
                            Materiais, prática e desempenho
                        </strong>
                    </div>
                </div>
            </div>

            <div class="fd-uxa-floating-card fd-uxa-floating-card--pdf">
                <?php
                echo fd_theme_icon(
                    'lock'
                );
                ?>

                <div>
                    <span>
                        Conteúdo digital
                    </span>

                    <strong>
                        Acesso protegido
                    </strong>
                </div>
            </div>

            <div class="fd-uxa-floating-card fd-uxa-floating-card--quiz">
                <span class="fd-uxa-floating-card__dot"></span>

                <div>
                    <span>
                        Simulados
                    </span>

                    <strong>
                        Prática online
                    </strong>
                </div>
            </div>
        </div>
    </div>
</section>
