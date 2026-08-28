<?php
/*
Template Name: Facil Digital - Login
*/

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

get_header();

?>

<section class="fd-auth-page fd-auth-login">
    <div class="fd-container fd-auth-layout">
        <div class="fd-auth-intro">
            <span class="fd-eyebrow">
                Area do aluno
            </span>

            <h1>
                Bem-vindo de volta.
            </h1>

            <p>
                Entre para acessar seus
                pedidos e recursos da
                Facil Digital+.
            </p>

            <ul class="fd-auth-benefits">
                <li>
                    <?php
                    echo fd_theme_icon(
                        'check'
                    );
                    ?>
                    Seus pedidos em uma unica conta
                </li>

                <li>
                    <?php
                    echo fd_theme_icon(
                        'check'
                    );
                    ?>
                    Acesso aos produtos adquiridos
                </li>

                <li>
                    <?php
                    echo fd_theme_icon(
                        'lock'
                    );
                    ?>
                    Autenticacao protegida pelo WordPress
                </li>
            </ul>
        </div>

        <div class="fd-auth-card">
            <header class="fd-auth-card__header">
                <h2>
                    Entrar
                </h2>

                <p>
                    Use seu e-mail ou
                    nome de usuario.
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
            >
                <?php
                wp_nonce_field(
                    'fd_login',
                    'fd_nonce'
                );
                ?>

                <input
                    type="hidden"
                    name="fd_auth_action"
                    value="login"
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

                <div class="fd-form-field">
                    <label for="fd-login-user">
                        E-mail ou usuario
                    </label>

                    <input
                        id="fd-login-user"
                        type="text"
                        name="user_login"
                        autocomplete="username"
                        required
                        value="<?php
                            echo esc_attr(
                                fd_theme_auth_old(
                                    'user_login'
                                )
                            );
                        ?>"
                    >
                </div>

                <div class="fd-form-field">
                    <label for="fd-login-password">
                        Senha
                    </label>

                    <div class="fd-password-field">
                        <input
                            id="fd-login-password"
                            type="password"
                            name="user_password"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="fd-password-toggle"
                            data-fd-password-toggle
                            aria-label="Mostrar senha"
                        >
                            Mostrar
                        </button>
                    </div>
                </div>

                <div class="fd-auth-form__row">
                    <label class="fd-checkbox">
                        <input
                            type="checkbox"
                            name="remember"
                            value="1"
                        >

                        <span>
                            Manter conectado
                        </span>
                    </label>

                    <a
                        href="<?php
                            echo esc_url(
                                fd_theme_get_lost_password_url()
                            );
                        ?>"
                    >
                        Esqueci minha senha
                    </a>
                </div>

                <button
                    type="submit"
                    class="fd-button fd-button--primary fd-auth-submit"
                >
                    Entrar
                </button>
            </form>

            <p class="fd-auth-switch">
                Ainda nao possui conta?

                <a
                    href="<?php
                        echo esc_url(
                            fd_theme_get_register_url()
                        );
                    ?>"
                >
                    Criar conta
                </a>
            </p>
        </div>
    </div>
</section>

<?php

get_footer();