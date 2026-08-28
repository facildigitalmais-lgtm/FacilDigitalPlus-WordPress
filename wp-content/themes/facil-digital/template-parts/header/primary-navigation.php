<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>

<nav
    id="fd-primary-navigation"
    class="fd-primary-nav"
    aria-label="<?php
        echo esc_attr__(
            'Navegacao principal',
            'facil-digital'
        );
    ?>"
    data-fd-primary-nav
>
    <?php
    wp_nav_menu(
        [
            'theme_location' =>
                'primary',

            'container' =>
                false,

            'menu_class' =>
                'fd-primary-nav__list',

            'menu_id' =>
                'fd-primary-menu',

            'fallback_cb' =>
                'fd_theme_primary_menu_fallback',

            'depth' =>
                2,
        ]
    );
    ?>
</nav>