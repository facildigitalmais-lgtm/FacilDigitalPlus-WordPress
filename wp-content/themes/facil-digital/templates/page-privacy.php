<?php
/*
Template Name: Facil Digital - Privacidade
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
            Politica de Privacidade
        </h1>

        <p>
            Informacoes gerais sobre
            tratamento de dados na
            plataforma Facil Digital+.
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
            1. Dados utilizados
        </h2>

        <p>
            A plataforma pode tratar dados
            necessarios para cadastro,
            autenticacao, compras,
            atendimento, seguranca e
            disponibilizacao de produtos
            digitais.
        </p>

        <h2>
            2. Finalidade
        </h2>

        <p>
            Os dados sao utilizados para
            operar a conta do cliente,
            processar pedidos, prestar
            atendimento, proteger a
            plataforma e disponibilizar
            recursos adquiridos.
        </p>

        <h2>
            3. Pagamentos
        </h2>

        <p>
            Dados de pagamento processados
            por provedores externos seguem
            tambem as politicas e medidas
            de seguranca desses provedores.
            A Facil Digital+ nao deve
            armazenar dados completos de
            cartao em sua aplicacao.
        </p>

        <h2>
            4. Seguranca
        </h2>

        <p>
            Sao adotadas medidas tecnicas
            e organizacionais compativeis
            com a natureza da plataforma
            para reduzir riscos de acesso
            indevido, perda ou alteracao
            de informacoes.
        </p>

        <h2>
            5. Direitos do titular
        </h2>

        <p>
            O titular pode utilizar os
            canais oficiais de atendimento
            para solicitar informacoes
            relacionadas aos seus dados,
            observadas as obrigacoes legais
            e contratuais aplicaveis.
        </p>

        <h2>
            6. Contato
        </h2>

        <p>
            Para solicitacoes relacionadas
            a privacidade, utilize a pagina
            oficial de contato da plataforma.
        </p>
    </div>
</section>

<?php

get_footer();