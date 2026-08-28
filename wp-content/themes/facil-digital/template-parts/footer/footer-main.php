<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="fd-footer-main">
    <div class="fd-container fd-footer-grid">
        <section class="fd-footer-brand">
            <a
                class="fd-footer-brand__logo"
                href="<?php
                    echo esc_url(
                        home_url('/')
                    );
                ?>"
            >
                Facil Digital<strong>+</strong>
            </a>

            <p>
                Apostilas digitais e simulados
                para quem busca uma preparacao
                objetiva e eficiente para
                concursos publicos.
            </p>
        </section>

        <section class="fd-footer-column">
            <h2>
                Institucional
            </h2>

            <?php
            if (
                has_nav_menu(
                    'footer-company'
                )
            ) {
                wp_nav_menu(
                    [
                        'theme_location' =>
                            'footer-company',

                        'container' =>
                            false,

                        'menu_class' =>
                            'fd-footer-menu',

                        'depth' =>
                            1,
                    ]
                );
            } else {
                fd_theme_render_footer_fallback(
                    'footer-company'
                );
            }
            ?>
        </section>

        <section class="fd-footer-column">
            <h2>
                Atendimento
            </h2>

            <?php
            if (
                has_nav_menu(
                    'footer-support'
                )
            ) {
                wp_nav_menu(
                    [
                        'theme_location' =>
                            'footer-support',

                        'container' =>
                            false,

                        'menu_class' =>
                            'fd-footer-menu',

                        'depth' =>
                            1,
                    ]
                );
            } else {
                fd_theme_render_footer_fallback(
                    'footer-support'
                );
            }
            ?>
        </section>

        <section class="fd-footer-column">
            <h2>
                Legal
            </h2>

            <?php
            if (
                has_nav_menu(
                    'footer-legal'
                )
            ) {
                wp_nav_menu(
                    [
                        'theme_location' =>
                            'footer-legal',

                        'container' =>
                            false,

                        'menu_class' =>
                            'fd-footer-menu',

                        'depth' =>
                            1,
                    ]
                );
            } else {
                fd_theme_render_footer_fallback(
                    'footer-legal'
                );
            }
            ?>
        </section>
    </div>
</div>