<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

get_header();

?>

<section class="fd-not-found fd-section">
    <div class="fd-container fd-not-found__inner">
        <span class="fd-not-found__code">
            404
        </span>

        <span class="fd-eyebrow">
            <?php
            echo esc_html__(
                'Pagina nao encontrada',
                'facil-digital'
            );
            ?>
        </span>

        <h1>
            <?php
            echo esc_html__(
                'Este endereco nao existe ou foi movido.',
                'facil-digital'
            );
            ?>
        </h1>

        <p>
            <?php
            echo esc_html__(
                'Use a busca para encontrar uma apostila ou retorne ao inicio.',
                'facil-digital'
            );
            ?>
        </p>

        <div class="fd-not-found__search">
            <?php
            get_search_form();
            ?>
        </div>

        <div class="fd-not-found__actions">
            <a
                class="fd-button fd-button--primary"
                href="<?php
                    echo esc_url(
                        fd_theme_get_shop_url()
                    );
                ?>"
            >
                <?php
                echo esc_html__(
                    'Ver apostilas',
                    'facil-digital'
                );
                ?>
            </a>

            <a
                class="fd-button fd-button--secondary"
                href="<?php
                    echo esc_url(
                        home_url('/')
                    );
                ?>"
            >
                <?php
                echo esc_html__(
                    'Voltar ao inicio',
                    'facil-digital'
                );
                ?>
            </a>
        </div>
    </div>
</section>

<?php

get_footer();
