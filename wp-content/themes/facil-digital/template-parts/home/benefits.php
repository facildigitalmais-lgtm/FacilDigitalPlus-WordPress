<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$benefits = [
    [
        'number' => '01',
        'title'  => 'Conteudo organizado',
        'text'   => 'Materiais estruturados para facilitar sua rotina de estudos.',
    ],
    [
        'number' => '02',
        'title'  => 'Simulados online',
        'text'   => 'Pratique, acompanhe seu desempenho e evolua com mais clareza.',
    ],
    [
        'number' => '03',
        'title'  => 'Acesso digital',
        'text'   => 'Consulte suas compras e materiais pela sua conta.',
    ],
    [
        'number' => '04',
        'title'  => 'Compra protegida',
        'text'   => 'Fluxo de compra integrado ao WooCommerce e meios de pagamento seguros.',
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
                    'Facil Digital+',

                'title' =>
                    'Uma plataforma feita para quem quer estudar melhor',

                'text' =>
                    'Da escolha da apostila a pratica com simulados, tudo reunido em uma unica experiencia.',

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