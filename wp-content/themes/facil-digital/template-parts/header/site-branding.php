<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="fd-site-branding">
    <?php if (has_custom_logo()) : ?>
        <?php
        the_custom_logo();
        ?>
    <?php else : ?>
        <a
            class="fd-site-branding__text"
            href="<?php
                echo esc_url(
                    home_url('/')
                );
            ?>"
            rel="home"
        >
            <span>
                Fácil Digital
            </span>

            <strong>
                +
            </strong>
        </a>
    <?php endif; ?>
</div>
