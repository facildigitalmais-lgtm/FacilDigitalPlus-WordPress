<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$contactEmail =
    sanitize_email(
        (string) get_option(
            'admin_email'
        )
    );

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
                Fácil Digital<strong>+</strong>
            </a>

            <p>
                Apostilas digitais, simulados e
                ferramentas para uma preparação
                mais organizada para concursos públicos.
            </p>

            <?php if ($contactEmail !== '') : ?>
                <a
                    class="fd-footer-brand__contact"
                    href="mailto:<?php
                        echo esc_attr(
                            $contactEmail
                        );
                    ?>"
                >
                    <?php
                    echo esc_html(
                        $contactEmail
                    );
                    ?>
                </a>
            <?php endif; ?>
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
