<?php
/*
Template Name: Facil Digital - Cadastro
*/

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

get_header();

?>

<section class="fd-auth-page fd-auth-register">
    <div class="fd-container fd-auth-layout">
        <div class="fd-auth-intro">
            <span class="fd-eyebrow">
                Criar conta
            </span>

            <h1>
                Sua área de estudos
                começa aqui.
            </h1>

            <p>
                Crie sua conta para manter
                pedidos e recursos da
                plataforma organizados.
            </p>
        </div>

        <div class="fd-auth-card">
            <header class="fd-auth-card__header">
                <h2>
                    Cadastre-se
                </h2>

                <p>
                    Preencha os dados abaixo.
                </p>
            </header>

            <?php
            get_template_part(
                'template-parts/components/auth-notices'
            );
            ?>

            <form
                class="fd-auth-form"
                method="post"
                action=""
                data-fd-register-form
            >
                <?php
                wp_nonce_field(
                    'fd_register',
                    'fd_nonce'
                );
                ?>

                <input
                    type="hidden"
                    name="fd_auth_action"
                    value="register"
                >

                <input
                    type="hidden"
                    name="redirect_to"
                    value="<?php
                        echo esc_attr(
                            fd_theme_get_account_url()
                        );
                    ?>"
                >

                <div class="fd-form-grid">
                    <div class="fd-form-field">
                        <label for="fd-first-name">
                            Nome
                        </label>

                        <input
                            id="fd-first-name"
                            type="text"
                            name="first_name"
                            autocomplete="given-name"
                            required
                            value="<?php
                                echo esc_attr(
                                    fd_theme_auth_old(
                                        'first_name'
                                    )
                                );
                            ?>"
                        >
                    </div>

                    <div class="fd-form-field">
                        <label for="fd-last-name">
                            Sobrenome
                        </label>

                        <input
                            id="fd-last-name"
                            type="text"
                            name="last_name"
                            autocomplete="family-name"
                            required
                            value="<?php
                                echo esc_attr(
                                    fd_theme_auth_old(
                                        'last_name'
                                    )
                                );
                            ?>"
                        >
                    </div>
                </div>

                <div class="fd-form-field">
                    <label for="fd-register-email">
                        E-mail
                    </label>

                    <input
                        id="fd-register-email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        required
                        value="<?php
                            echo esc_attr(
                                fd_theme_auth_old(
                                    'email'
                                )
                            );
                        ?>"
                    >
                </div>

                <div class="fd-form-field">
                    <label for="fd-register-password">
                        Senha
                    </label>

                    <div class="fd-password-field">
                        <input
                            id="fd-register-password"
                            type="password"
                            name="password"
                            autocomplete="new-password"
                            minlength="8"
                            required
                            data-fd-password
                            aria-describedby="fd-password-help"
                        >

                        <button
                            type="button"
                            class="fd-password-toggle"
                            data-fd-password-toggle
                            aria-label="Mostrar senha"
                            aria-pressed="false"
                        >
                            Mostrar
                        </button>
                    </div>

                    <small id="fd-password-help">
                        Mínimo de 8 caracteres.
                    </small>
                </div>

                <div class="fd-form-field">
                    <label for="fd-register-password-confirm">
                        Confirmar senha
                    </label>

                    <div class="fd-password-field">
                        <input
                            id="fd-register-password-confirm"
                            type="password"
                            name="password_confirm"
                            autocomplete="new-password"
                            minlength="8"
                            required
                            data-fd-password-confirm
                            aria-describedby="fd-password-match"
                        >

                        <button
                            type="button"
                            class="fd-password-toggle"
                            data-fd-password-toggle
                            aria-label="Mostrar senha"
                            aria-pressed="false"
                        >
                            Mostrar
                        </button>
                    </div>

                    <small
                        id="fd-password-match"
                        class="fd-password-match"
                        data-fd-password-match
                        aria-live="polite"
                    ></small>
                </div>

                <label class="fd-checkbox fd-auth-terms">
                    <input
                        type="checkbox"
                        name="accept_terms"
                        value="1"
                        required
                    >

                    <span>
                        Li e aceito os
                        <a
                            href="<?php
                                echo esc_url(
                                    fd_theme_get_terms_url()
                                );
                            ?>"
                            target="_blank"
                            rel="noopener"
                        >
                            Termos de Uso
                        </a>

                        e a

                        <a
                            href="<?php
                                echo esc_url(
                                    fd_theme_get_privacy_url()
                                );
                            ?>"
                            target="_blank"
                            rel="noopener"
                        >
                            Política de Privacidade
                        </a>.
                    </span>
                </label>

                <button
                    type="submit"
                    class="fd-button fd-button--primary fd-auth-submit"
                >
                    Criar minha conta
                </button>
            </form>

            <p class="fd-auth-switch">
                Já possui uma conta?

                <a
                    href="<?php
                        echo esc_url(
                            fd_theme_get_login_url()
                        );
                    ?>"
                >
                    Entrar
                </a>
            </p>
        </div>
    </div>
</section>

<?php

get_footer();
