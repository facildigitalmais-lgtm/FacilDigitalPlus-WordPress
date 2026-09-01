<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$steps = [
    [
        'number' => '1',
        'title'  => 'Encontre',
        'text'   => 'Busque pelo concurso, banca ou cargo desejado.',
    ],
    [
        'number' => '2',
        'title'  => 'Escolha',
        'text'   => 'Confira os detalhes e selecione sua apostila.',
    ],
    [
        'number' => '3',
        'title'  => 'Compre',
        'text'   => 'Finalize seu pedido pelo checkout seguro da plataforma.',
    ],
    [
        'number' => '4',
        'title'  => 'Estude',
        'text'   => 'Acesse sua área do aluno e comece sua preparação.',
    ],
];

?>

<section class="fd-section fd-section--soft fd-home-steps">
    <div class="fd-container">
        <?php
        get_template_part(
            'template-parts/components/section-heading',
            null,
            [
                'eyebrow' =>
                    'Como funciona',

                'title' =>
                    'Da escolha da apostila ao início dos estudos',

                'center' =>
                    true,
            ]
        );
        ?>

        <ol class="fd-step-grid">
            <?php
            foreach ($steps as $step) :
                ?>
                <li class="fd-step-card">
                    <span class="fd-step-card__number">
                        <?php
                        echo esc_html(
                            $step['number']
                        );
                        ?>
                    </span>

                    <h3>
                        <?php
                        echo esc_html(
                            $step['title']
                        );
                        ?>
                    </h3>

                    <p>
                        <?php
                        echo esc_html(
                            $step['text']
                        );
                        ?>
                    </p>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>