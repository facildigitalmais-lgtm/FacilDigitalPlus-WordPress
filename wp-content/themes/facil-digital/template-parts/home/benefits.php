<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$benefits = [
    [
        'number' => '01',
        'title'  => 'Apostilas digitais',
        'text'   => 'Materiais organizados para ajudar você a estudar com mais foco e objetividade.',
    ],
    [
        'number' => '02',
        'title'  => 'Área do Aluno',
        'text'   => 'Compras, apostilas e recursos liberados reunidos em uma experiência simples.',
    ],
    [
        'number' => '03',
        'title'  => 'Simulados online',
        'text'   => 'Pratique questões, acompanhe resultados e identifique sua evolução.',
    ],
    [
        'number' => '04',
        'title'  => 'Acesso seguro',
        'text'   => 'Conteúdo vinculado à sua conta e protegido pelos controles da plataforma.',
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
                    'Sua preparação em um único lugar',

                'text' =>
                    'Escolha seu material, estude, pratique e acompanhe sua evolução com uma experiência integrada.',

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
