<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$benefits = [
    [
        'number' => '01',
        'title'  => 'Conteúdo organizado',
        'text'   => 'Apostilas estruturadas para ajudar você a estudar com mais foco e organização.',
    ],
    [
        'number' => '02',
        'title'  => 'Simulados online',
        'text'   => 'Pratique questões, acompanhe resultados e identifique sua evolução.',
    ],
    [
        'number' => '03',
        'title'  => 'Tudo na sua conta',
        'text'   => 'Acesse apostilas, simulados, resultados e recursos liberados para você.',
    ],
    [
        'number' => '04',
        'title'  => 'Compra segura',
        'text'   => 'Finalize sua compra em um fluxo seguro e tenha o acesso associado à sua conta.',
    ],
];

?>

<section class="fd-section fd-home-benefits">
    <div class="fd-container">
        <?php
        get_template_part(
            'template-parts/components/section-heading',
            null,
            [
                'eyebrow' =>
                    'Fácil Digital+',

                'title' =>
                    'Uma plataforma para organizar sua preparação',

                'text' =>
                    'Da escolha da apostila à prática com simulados, tudo reunido em uma única experiência.',

                'center' =>
                    true,
            ]
        );
        ?>

        <div class="fd-benefit-grid">
            <?php
            foreach ($benefits as $benefit) :
                ?>
                <article class="fd-benefit-card">
                    <span>
                        <?php
                        echo esc_html(
                            $benefit[
                                'number'
                            ]
                        );
                        ?>
                    </span>

                    <h3>
                        <?php
                        echo esc_html(
                            $benefit[
                                'title'
                            ]
                        );
                        ?>
                    </h3>

                    <p>
                        <?php
                        echo esc_html(
                            $benefit[
                                'text'
                            ]
                        );
                        ?>
                    </p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>