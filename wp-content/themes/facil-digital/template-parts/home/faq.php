<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$items = [
    [
        'question' =>
            'Como recebo minha apostila?',
        'answer' =>
            'Os materiais digitais adquiridos ficam vinculados a sua conta de cliente conforme a liberacao do pedido.',
    ],
    [
        'question' =>
            'Preciso criar uma conta?',
        'answer' =>
            'Sim. A conta permite associar compras, materiais e futuramente resultados de simulados ao aluno correto.',
    ],
    [
        'question' =>
            'Posso acessar pelo celular?',
        'answer' =>
            'Sim. A plataforma esta sendo desenvolvida para funcionar em computadores, tablets e celulares.',
    ],
    [
        'question' =>
            'Os simulados ficam na mesma conta?',
        'answer' =>
            'Sim. A arquitetura da plataforma foi preparada para centralizar materiais e simulados na area do aluno.',
    ],
];

?>

<section class="fd-section fd-section--soft fd-home-faq">
    <div class="fd-container fd-home-faq__container">
        <?php
        get_template_part(
            'template-parts/components/section-heading',
            null,
            [
                'eyebrow' =>
                    'Duvidas frequentes',

                'title' =>
                    'Antes de comecar',

                'text' =>
                    'Algumas respostas rapidas sobre a plataforma.',
            ]
        );
        ?>

        <div class="fd-faq-list">
            <?php
            foreach ($items as $item) :
                ?>
                <details class="fd-faq-item">
                    <summary>
                        <?php
                        echo esc_html(
                            $item[
                                'question'
                            ]
                        );
                        ?>
                    </summary>

                    <div class="fd-faq-item__content">
                        <p>
                            <?php
                            echo esc_html(
                                $item[
                                    'answer'
                                ]
                            );
                            ?>
                        </p>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>

        <a
            class="fd-text-link"
            href="<?php
                echo esc_url(
                    fd_theme_page_url(
                        'faq'
                    )
                );
            ?>"
        >
            Ver todas as perguntas
        </a>
    </div>
</section>