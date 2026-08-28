<?php
/*
Template Name: Facil Digital - Termos
*/

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

get_header();

?>

<section class="fd-page-hero">
    <div class="fd-container fd-page-hero__inner">
        <span class="fd-eyebrow">
            Legal
        </span>

        <h1>
            Termos de Uso
        </h1>

        <p>
            Condicoes gerais de utilizacao
            da plataforma Facil Digital+.
        </p>
    </div>
</section>

<section class="fd-section">
    <div class="fd-container fd-legal-content">
        <p class="fd-legal-content__updated">
            Ultima atualizacao:
            <?php
            echo esc_html(
                wp_date(
                    'd/m/Y'
                )
            );
            ?>
        </p>

        <h2>
            1. Plataforma
        </h2>

        <p>
            A Facil Digital+ disponibiliza
            produtos digitais e recursos
            relacionados a preparacao para
            concursos publicos.
        </p>

        <h2>
            2. Conta do usuario
        </h2>

        <p>
            O usuario e responsavel por
            manter seus dados cadastrais
            corretos e por preservar a
            confidencialidade de sua senha.
        </p>

        <h2>
            3. Produtos digitais
        </h2>

        <p>
            As condicoes especificas,
            caracteristicas e conteudos
            de cada produto sao apresentados
            em sua respectiva pagina.
        </p>

        <h2>
            4. Uso individual
        </h2>

        <p>
            O acesso concedido ao comprador
            destina-se ao uso pessoal
            conforme as condicoes apresentadas
            no momento da compra.
        </p>

        <h2>
            5. Disponibilidade
        </h2>

        <p>
            Podem ocorrer manutencoes,
            atualizacoes ou indisponibilidades
            temporarias necessarias a
            operacao e seguranca do servico.
        </p>

        <h2>
            6. Alteracoes
        </h2>

        <p>
            Estes termos podem ser
            atualizados quando necessario.
            A versao disponibilizada nesta
            pagina representa os termos
            vigentes publicados no site.
        </p>
    </div>
</section>

<?php

get_footer();