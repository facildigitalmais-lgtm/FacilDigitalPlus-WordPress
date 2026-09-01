<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$faqs = [
    [
        'question' =>
            'Este produto é digital?',

        'answer' =>
            'Sim. Esta apostila é comercializada em formato digital pela Fácil Digital+.',
    ],

    [
        'question' =>
            'Como vejo o valor da compra?',

        'answer' =>
            'O valor da apostila é exibido nesta página. As opções disponíveis no checkout são apresentadas antes da finalização.',
    ],

    [
        'question' =>
            'Preciso ter uma conta?',

        'answer' =>
            'Sim. Sua conta é utilizada para associar pedidos, apostilas e outros recursos digitais disponibilizados pela plataforma.',
    ],

    [
        'question' =>
            'Como recebo o material?',

        'answer' =>
            'A liberação é vinculada ao pedido e à sua conta. Após o processamento, o material fica disponível na área do aluno conforme o status definido pela plataforma.',
    ],
];

?>

<section
    class="fd-product-section fd-product-faq"
    id="duvidas"
>
    <div class="fd-product-section__heading">
        <span class="fd-eyebrow">
            FAQ
        </span>

        <h2>
            <?php
            echo esc_html__(
                'Dúvidas sobre esta compra',
                'facil-digital'
            );
            ?>
        </h2>
    </div>

    <div class="fd-faq-list">
        <?php foreach ($faqs as $faq) : ?>
            <details class="fd-faq-item">
                <summary>
                    <?php
                    echo esc_html(
                        $faq['question']
                    );
                    ?>
                </summary>

                <div class="fd-faq-item__content">
                    <p>
                        <?php
                        echo esc_html(
                            $faq['answer']
                        );
                        ?>
                    </p>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
</section>
