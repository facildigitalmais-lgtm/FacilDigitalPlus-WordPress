<?php
/*
Template Name: Fácil Digital - Termos
*/

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();
    ?>

    <section class="fd-page-hero">
        <div class="fd-container fd-page-hero__inner">
            <span class="fd-eyebrow">
                <?php
                echo esc_html__(
                    'Legal',
                    'facil-digital'
                );
                ?>
            </span>

            <h1>
                <?php
                the_title();
                ?>
            </h1>

            <p>
                <?php
                echo esc_html__(
                    'Condições gerais de utilização da plataforma Fácil Digital+.',
                    'facil-digital'
                );
                ?>
            </p>
        </div>
    </section>

    <section class="fd-section">
        <div class="fd-container fd-legal-content">
            <p class="fd-legal-content__updated">
                <?php
                printf(
                    esc_html__(
                        'Última atualização: %s',
                        'facil-digital'
                    ),
                    esc_html(
                        get_the_modified_date(
                            'd/m/Y'
                        )
                    )
                );
                ?>
            </p>

            <?php
            the_content();
            ?>
        </div>
    </section>

    <?php
endwhile;

get_footer();