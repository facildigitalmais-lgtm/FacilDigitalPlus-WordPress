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
            'Após a liberação do pedido, a apostila fica vinculada à sua conta e pode ser acessada pela área do aluno.',
    ],
    [
        'question' =>
            'Preciso criar uma conta?',
        'answer' =>
            'Sim. Sua conta reúne compras, apostilas, simulados, tentativas e resultados em um só lugar.',
    ],
    [
        'question' =>
            'Posso acessar pelo celular?',
        'answer' =>
            'Sim. A plataforma possui interface responsiva para computadores, tablets e celulares.',
    ],
    [
        'question' =>
            'Os simulados ficam na mesma conta?',
        'answer' =>
            'Sim. Os simulados liberados ficam disponíveis na área do aluno junto aos demais recursos da sua conta.',
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
                    'Dúvidas frequentes',

                'title' =>
                    'Antes de começar',

                'text' =>
                    'Respostas rápidas para as principais dúvidas sobre a plataforma.',
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