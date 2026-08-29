<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$faqs = [
    [
        'question' =>
            'Este produto e digital?',

        'answer' =>
            'Sim. Esta pagina representa uma apostila digital comercializada pela plataforma Facil Digital+.',
    ],

    [
        'question' =>
            'O preco muda conforme a forma de pagamento?',

        'answer' =>
            'Nao. A Facil Digital+ trabalha com um unico preco base para o produto. Condicoes operacionais do meio de pagamento nao alteram o cadastro comercial da apostila.',
    ],

    [
        'question' =>
            'Preciso estar cadastrado?',

        'answer' =>
            'Sim. A conta do cliente e utilizada para associar pedidos e os recursos digitais disponibilizados pela plataforma.',
    ],

    [
        'question' =>
            'Quando receberei o material protegido?',

        'answer' =>
            'O fluxo definitivo de liberacao e protecao do PDF sera integrado ao status de pagamento nas fases especificas de entitlement e documentos protegidos.',
    ],
];

?>

<section class="fd-product-section fd-product-faq">
    <div class="fd-product-section__heading">
        <span class="fd-eyebrow">
            FAQ
        </span>

        <h2>
            <?php
            echo esc_html__(
                'Duvidas sobre esta compra',
                'facil-digital'
            );
            ?>
        </h2>
    </div>

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
</section>
