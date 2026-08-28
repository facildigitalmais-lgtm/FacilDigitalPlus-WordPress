<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

get_header();

?>

<section class="fd-hero fd-section">
    <div class="fd-container fd-hero__layout">
        <div class="fd-hero__content">
            <span class="fd-eyebrow">
                Preparacao para concursos publicos
            </span>

            <h1>
                Sua preparacao
                <span>mais facil</span>
                com a Facil Digital+
            </h1>

            <p class="fd-hero__lead">
                Apostilas digitais, materiais
                organizados e simulados para voce
                estudar com foco no que realmente
                importa.
            </p>

            <div class="fd-hero__actions">
                <a
                    class="fd-button fd-button--primary fd-button--large"
                    href="<?php
                        echo esc_url(
                            fd_theme_get_shop_url()
                        );
                    ?>"
                >
                    Ver apostilas
                </a>

                <a
                    class="fd-button fd-button--secondary fd-button--large"
                    href="<?php
                        echo esc_url(
                            fd_theme_get_account_url()
                        );
                    ?>"
                >
                    Area do aluno
                </a>
            </div>
        </div>

        <div
            class="fd-hero__visual"
            aria-hidden="true"
        >
            <div class="fd-hero-card">
                <span class="fd-hero-card__label">
                    Facil Digital+
                </span>

                <strong>
                    Estude.
                    Pratique.
                    Evolua.
                </strong>

                <span>
                    Apostilas + Simulados
                </span>
            </div>
        </div>
    </div>
</section>

<section class="fd-section fd-section--soft">
    <div class="fd-container">
        <div class="fd-section-heading fd-section-heading--center">
            <span class="fd-eyebrow">
                Uma plataforma completa
            </span>

            <h2>
                Tudo para organizar
                sua preparacao
            </h2>

            <p>
                Materiais digitais pensados para
                facilitar sua rotina de estudos.
            </p>
        </div>

        <div class="fd-feature-grid">
            <article class="fd-feature-card">
                <span class="fd-feature-card__number">
                    01
                </span>

                <h3>
                    Apostilas digitais
                </h3>

                <p>
                    Conteudo organizado por concurso,
                    cargo e disciplina.
                </p>
            </article>

            <article class="fd-feature-card">
                <span class="fd-feature-card__number">
                    02
                </span>

                <h3>
                    Simulados
                </h3>

                <p>
                    Pratique seus conhecimentos
                    e acompanhe seus resultados.
                </p>
            </article>

            <article class="fd-feature-card">
                <span class="fd-feature-card__number">
                    03
                </span>

                <h3>
                    Acesso digital
                </h3>

                <p>
                    Seus materiais e resultados
                    reunidos em uma unica conta.
                </p>
            </article>
        </div>
    </div>
</section>

<?php

get_footer();