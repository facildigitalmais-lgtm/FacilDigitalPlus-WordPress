<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?><!doctype html>

<html <?php language_attributes(); ?>>

<head>
    <meta
        charset="<?php
            bloginfo(
                'charset'
            );
        ?>"
    >

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <?php wp_head(); ?>
</head>

<body <?php body_class('fd-theme'); ?>>

<?php wp_body_open(); ?>

<a
    class="fd-skip-link"
    href="#fd-main-content"
>
    <?php
    echo esc_html__(
        'Ir para o conteudo',
        'facil-digital'
    );
    ?>
</a>

<header
    class="fd-site-header"
    data-fd-site-header
>
    <div class="fd-container fd-site-header__inner">
        <?php
        get_template_part(
            'template-parts/header/site-branding'
        );
        ?>

        <?php
        get_template_part(
            'template-parts/header/primary-navigation'
        );
        ?>

        <?php
        get_template_part(
            'template-parts/header/header-actions'
        );
        ?>
    </div>

    <div
        id="fd-header-search"
        class="fd-header-search"
        data-fd-search-panel
        hidden
    >
        <div class="fd-container">
            <?php
            get_search_form();
            ?>
        </div>
    </div>
</header>

<div
    class="fd-navigation-overlay"
    data-fd-navigation-overlay
    hidden
    aria-hidden="true"
></div>

<main
    id="fd-main-content"
    class="fd-site-main"
>