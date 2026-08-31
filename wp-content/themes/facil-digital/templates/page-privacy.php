<?php
/*
Template Name: Fácil Digital - Privacidade
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
            Política de Privacidade
        </h1>

        <p>
            Informações gerais sobre
            tratamento de dados na
            plataforma Fácil Digital+.
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
            autenticação, compras,
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
            também as políticas e medidas
            de seguranca desses provedores.
            A Fácil Digital+ não deve
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
            de informações.
        </p>

        <h2>
            5. Direitos do titular
        </h2>

        <p>
            O titular pode utilizar os
            canais oficiais de atendimento
            para solicitar informações
            relacionadas aos seus dados,
            observadas as obrigações legais
            e contratuais aplicaveis.
        </p>

        <h2>
            6. Contato
        </h2>

        <p>
            Para solicitações relacionadas
            a privacidade, utilize a página
            oficial de contato da plataforma.
        </p>
    </div>
</section>

<?php

get_footer();
