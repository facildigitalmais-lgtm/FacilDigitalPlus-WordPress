<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>

<section class="fd-section fd-home-simulations">
    <div class="fd-container fd-home-simulations__grid">
        <div>
            <span class="fd-eyebrow">
                Mais do que leitura
            </span>

            <h2>
                Pratique com simulados
                e acompanhe sua evolução
            </h2>

            <p>
                A Fácil Digital+ reúne
                apostilas, questões, simulados,
                tentativas e resultados
                em um único ambiente.
            </p>

            <a
                class="fd-button fd-button--primary"
                href="<?php
                    echo esc_url(
                        fd_theme_get_login_url()
                    );
                ?>"
            >
                Ir para a área do aluno
            </a>
        </div>

        <div class="fd-simulation-preview">
            <div class="fd-simulation-preview__header">
                <span>
                    Simulado
                </span>

                <strong>
                    Seu desempenho
                </strong>
            </div>

            <div class="fd-simulation-preview__score">
                <strong>
                    82%
                </strong>

                <span>
                    exemplo visual
                </span>
            </div>

            <div class="fd-simulation-preview__bars">
                <span style="--fd-progress: 88%"></span>
                <span style="--fd-progress: 72%"></span>
                <span style="--fd-progress: 84%"></span>
            </div>
        </div>
    </div>
</section>