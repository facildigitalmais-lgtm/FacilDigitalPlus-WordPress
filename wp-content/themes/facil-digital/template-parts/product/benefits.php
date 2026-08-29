<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>

<section class="fd-product-section fd-product-benefits">
    <div class="fd-product-section__heading">
        <span class="fd-eyebrow">
            <?php
            echo esc_html__(
                'Facil Digital+',
                'facil-digital'
            );
            ?>
        </span>

        <h2>
            <?php
            echo esc_html__(
                'Preparacao digital em uma unica plataforma',
                'facil-digital'
            );
            ?>
        </h2>
    </div>

    <div class="fd-product-benefit-grid">
        <article>
            <strong>
                <?php
                echo esc_html__(
                    'Material digital',
                    'facil-digital'
                );
                ?>
            </strong>

            <p>
                <?php
                echo esc_html__(
                    'Produto comercializado em formato digital, sem envio fisico.',
                    'facil-digital'
                );
                ?>
            </p>
        </article>

        <article>
            <strong>
                <?php
                echo esc_html__(
                    'Conta do aluno',
                    'facil-digital'
                );
                ?>
            </strong>

            <p>
                <?php
                echo esc_html__(
                    'Pedidos e recursos ficam associados ao cliente autenticado.',
                    'facil-digital'
                );
                ?>
            </p>
        </article>

        <article>
            <strong>
                <?php
                echo esc_html__(
                    'Preco transparente',
                    'facil-digital'
                );
                ?>
            </strong>

            <p>
                <?php
                echo esc_html__(
                    'O produto possui um unico preco base, independentemente do meio de pagamento.',
                    'facil-digital'
                );
                ?>
            </p>
        </article>
    </div>
</section>
