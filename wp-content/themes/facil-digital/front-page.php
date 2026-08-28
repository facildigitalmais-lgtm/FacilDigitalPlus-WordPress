<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$core_active =
    defined(
        'FACIL_DIGITAL_CORE_BOOTED'
    );

$woocommerce_active =
    class_exists(
        'WooCommerce'
    );

?>

<section class="fd-hero">
    <div class="fd-container">
        <span class="fd-badge">
            Novo ambiente WordPress
        </span>

        <h1>
            Facil Digital<span>+</span>
        </h1>

        <p class="fd-hero__lead">
            Apostilas digitais e simulados
            para sua preparacao.
        </p>

        <div class="fd-hero__actions">
            <a
                class="fd-button"
                href="<?php
                    echo esc_url(
                        home_url(
                            '/apostilas/'
                        )
                    );
                ?>"
            >
                Ver apostilas
            </a>

            <a
                class="fd-button fd-button--secondary"
                href="<?php
                    echo esc_url(
                        home_url(
                            '/minha-conta/'
                        )
                    );
                ?>"
            >
                Area do aluno
            </a>
        </div>
    </div>
</section>

<section class="fd-status">
    <div class="fd-container">
        <h2>
            Ambiente de desenvolvimento
        </h2>

        <div class="fd-status-grid">
            <article class="fd-status-card">
                <strong>WordPress</strong>
                <span class="fd-status-ok">
                    Ativo
                </span>
            </article>

            <article class="fd-status-card">
                <strong>WooCommerce</strong>

                <span class="<?php
                    echo $woocommerce_active
                        ? 'fd-status-ok'
                        : 'fd-status-error';
                ?>">
                    <?php
                    echo esc_html(
                        $woocommerce_active
                            ? 'Ativo'
                            : 'Inativo'
                    );
                    ?>
                </span>
            </article>

            <article class="fd-status-card">
                <strong>
                    Facil Digital+ Core
                </strong>

                <span class="<?php
                    echo $core_active
                        ? 'fd-status-ok'
                        : 'fd-status-error';
                ?>">
                    <?php
                    echo esc_html(
                        $core_active
                            ? 'Ativo'
                            : 'Inativo'
                    );
                    ?>
                </span>
            </article>
        </div>
    </div>
</section>

<?php

get_footer();
