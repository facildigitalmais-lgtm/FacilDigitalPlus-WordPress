<?php
/*
Template Name: Facil Digital - Sobre
*/

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

get_header();

?>

<section class="fd-page-hero fd-page-about">
    <div class="fd-container fd-page-hero__inner">
        <span class="fd-eyebrow">
            Sobre a Facil Digital+
        </span>

        <h1>
            Educacao digital
            com foco em preparacao.
        </h1>

        <p>
            A Facil Digital+ nasceu para tornar
            materiais de estudo e ferramentas
            de pratica mais acessiveis,
            organizados e simples de utilizar.
        </p>
    </div>
</section>

<section class="fd-section">
    <div class="fd-container fd-content-grid">
        <article class="fd-content-card">
            <h2>
                Nossa proposta
            </h2>

            <p>
                Reunir apostilas digitais,
                simulados e recursos para
                concursos publicos em uma
                plataforma centralizada.
            </p>
        </article>

        <article class="fd-content-card">
            <h2>
                Clareza
            </h2>

            <p>
                O aluno deve encontrar
                rapidamente o material
                correspondente ao concurso
                e cargo que procura.
            </p>
        </article>

        <article class="fd-content-card">
            <h2>
                Evolucao
            </h2>

            <p>
                O projeto combina conteudo
                de estudo com ferramentas
                de pratica e acompanhamento.
            </p>
        </article>
    </div>
</section>

<section class="fd-section fd-section--soft">
    <div class="fd-container fd-prose">
        <h2>
            Uma plataforma em constante evolucao
        </h2>

        <p>
            A estrutura da Facil Digital+
            foi planejada para integrar
            catalogo, compras, materiais,
            simulados e resultados em uma
            experiencia unica para o aluno.
        </p>

        <a
            class="fd-button fd-button--primary"
            href="<?php
                echo esc_url(
                    fd_theme_get_shop_url()
                );
            ?>"
        >
            Conhecer as apostilas
        </a>
    </div>
</section>

<?php

get_footer();