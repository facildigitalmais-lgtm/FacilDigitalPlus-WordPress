<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="fd-header">
    <div class="fd-container fd-header__inner">
        <a
            class="fd-brand"
            href="<?php echo esc_url(home_url('/')); ?>"
        >
            Facil Digital<span>+</span>
        </a>

        <nav
            class="fd-nav"
            aria-label="<?php
                echo esc_attr__(
                    'Navegacao principal',
                    'facil-digital'
                );
            ?>"
        >
            <a href="<?php echo esc_url(home_url('/')); ?>">
                Inicio
            </a>

            <a href="<?php echo esc_url(home_url('/apostilas/')); ?>">
                Apostilas
            </a>

            <a href="<?php echo esc_url(home_url('/faq/')); ?>">
                FAQ
            </a>

            <?php if (is_user_logged_in()) : ?>
                <a
                    class="fd-nav__account"
                    href="<?php
                        echo esc_url(
                            home_url(
                                '/minha-conta/'
                            )
                        );
                    ?>"
                >
                    Minha conta
                </a>
            <?php else : ?>
                <a
                    class="fd-nav__account"
                    href="<?php
                        echo esc_url(
                            home_url(
                                '/entrar/'
                            )
                        );
                    ?>"
                >
                    Entrar
                </a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="fd-main">
