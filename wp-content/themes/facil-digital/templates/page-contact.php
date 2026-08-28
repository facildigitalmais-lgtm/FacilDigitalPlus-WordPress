<?php
/*
Template Name: Facil Digital - Contato
*/

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$email =
    sanitize_email(
        (string) get_option(
            'admin_email'
        )
    );

get_header();

?>

<section class="fd-page-hero fd-page-contact">
    <div class="fd-container fd-page-hero__inner">
        <span class="fd-eyebrow">
            Atendimento
        </span>

        <h1>
            Como podemos ajudar?
        </h1>

        <p>
            Entre em contato para duvidas
            sobre compras, acesso ou materiais.
        </p>
    </div>
</section>

<section class="fd-section">
    <div class="fd-container fd-contact-grid">
        <article class="fd-contact-card">
            <span>
                Atendimento por e-mail
            </span>

            <h2>
                Fale com a Facil Digital+
            </h2>

            <p>
                Envie sua solicitacao
                detalhando o pedido ou
                material relacionado.
            </p>

            <a
                class="fd-button fd-button--primary"
                href="<?php
                    echo esc_url(
                        'mailto:'
                        . $email
                    );
                ?>"
            >
                <?php
                echo esc_html(
                    antispambot(
                        $email
                    )
                );
                ?>
            </a>
        </article>

        <aside class="fd-contact-help">
            <h2>
                Antes de entrar em contato
            </h2>

            <ul>
                <li>
                    Consulte nossa pagina
                    de perguntas frequentes.
                </li>

                <li>
                    Se a duvida for sobre
                    uma compra, tenha o
                    numero do pedido em maos.
                </li>

                <li>
                    Nunca envie sua senha
                    por e-mail.
                </li>
            </ul>

            <a
                class="fd-text-link"
                href="<?php
                    echo esc_url(
                        fd_theme_page_url(
                            'faq'
                        )
                    );
                ?>"
            >
                Consultar FAQ
            </a>
        </aside>
    </div>
</section>

<?php

get_footer();