<?php
/*
Template Name: Facil Digital - FAQ
*/

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$faqs = [
    [
        'question' =>
            'O que e a Facil Digital+?',
        'answer' =>
            'E uma plataforma de materiais digitais e ferramentas de preparacao para concursos publicos.',
    ],
    [
        'question' =>
            'Como encontro uma apostila?',
        'answer' =>
            'Use a busca do site ou acesse o catalogo de apostilas para localizar o concurso e cargo desejados.',
    ],
    [
        'question' =>
            'Preciso ter uma conta?',
        'answer' =>
            'Sim. A conta identifica o comprador e permite concentrar pedidos, downloads e recursos do aluno.',
    ],
    [
        'question' =>
            'Como acesso minhas compras?',
        'answer' =>
            'Acesse Minha Conta utilizando o mesmo e-mail cadastrado na plataforma.',
    ],
    [
        'question' =>
            'Esqueci minha senha. O que fazer?',
        'answer' =>
            'Utilize a pagina Recuperar Senha. Se existir uma conta associada aos dados informados, o WordPress enviara as instrucoes de redefinicao.',
    ],
    [
        'question' =>
            'Posso estudar pelo celular?',
        'answer' =>
            'Sim. O site e a area do aluno estao sendo desenvolvidos com layout responsivo.',
    ],
    [
        'question' =>
            'Os simulados serao online?',
        'answer' =>
            'Sim. A plataforma foi projetada para disponibilizar simulados e resultados diretamente na conta do aluno.',
    ],
    [
        'question' =>
            'Como entro em contato?',
        'answer' =>
            'Utilize a pagina Contato para consultar o canal oficial de atendimento.',
    ],
];

get_header();

?>

<section class="fd-page-hero fd-page-faq">
    <div class="fd-container fd-page-hero__inner">
        <span class="fd-eyebrow">
            Central de ajuda
        </span>

        <h1>
            Perguntas frequentes
        </h1>

        <p>
            Encontre respostas para
            as principais duvidas sobre
            a Facil Digital+.
        </p>
    </div>
</section>

<section class="fd-section">
    <div class="fd-container fd-faq-page">
        <div class="fd-faq-list">
            <?php
            foreach ($faqs as $faq) :
                ?>
                <details class="fd-faq-item">
                    <summary>
                        <?php
                        echo esc_html(
                            $faq[
                                'question'
                            ]
                        );
                        ?>
                    </summary>

                    <div class="fd-faq-item__content">
                        <p>
                            <?php
                            echo esc_html(
                                $faq[
                                    'answer'
                                ]
                            );
                            ?>
                        </p>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>

        <div class="fd-faq-support">
            <h2>
                Ainda precisa de ajuda?
            </h2>

            <p>
                Entre em contato com
                nosso atendimento.
            </p>

            <a
                class="fd-button fd-button--primary"
                href="<?php
                    echo esc_url(
                        fd_theme_page_url(
                            'contato'
                        )
                    );
                ?>"
            >
                Falar com atendimento
            </a>
        </div>
    </div>
</section>

<?php

get_footer();