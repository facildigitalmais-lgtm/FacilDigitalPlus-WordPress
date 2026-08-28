<?php
/*
Template Name: Facil Digital - Recuperar Senha
*/

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$sent =
    isset($_GET['sent'])
    && sanitize_key(
        wp_unslash(
            (string) $_GET['sent']
        )
    ) === '1';

get_header();

?>

<section class="fd-auth-page fd-auth-lost-password">
    <div class="fd-container fd-auth-layout">
        <div class="fd-auth-intro">
            <span class="fd-eyebrow">
                Recuperacao de acesso
            </span>

            <h1>
                Vamos recuperar
                seu acesso.
            </h1>

            <p>
                Informe seu e-mail
                ou nome de usuario.
            </p>
        </div>

        <div class="fd-auth-card">
            <header class="fd-auth-card__header">
                <h2>
                    Recuperar senha
                </h2>

                <p>
                    Enviaremos as instrucoes
                    do fluxo oficial do
                    WordPress.
                </p>
            </header>

            <?php
            get_template_part(
                'template-parts/components/auth-notices',
                null,
                [
                    'success' =>
                        $sent
                            ? 'Se existir uma conta correspondente aos dados informados, as instrucoes de recuperacao serao enviadas.'
                            : '',
                ]
            );
            ?>

            <?php if (!$sent) : ?>
                <form
                    class="fd-auth-form"
                    method="post"
                    action=""
                >
                    <?php
                    wp_nonce_field(
                        'fd_lost_password',
                        'fd_nonce'
                    );
                    ?>

                    <input
                        type="hidden"
                        name="fd_auth_action"
                        value="lost_password"
                    >

                    <div class="fd-form-field">
                        <label for="fd-lost-user">
                            E-mail ou usuario
                        </label>

                        <input
                            id="fd-lost-user"
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

                    <button
                        type="submit"
                        class="fd-button fd-button--primary fd-auth-submit"
                    >
                        Enviar instrucoes
                    </button>
                </form>
            <?php endif; ?>

            <p class="fd-auth-switch">
                <a
                    href="<?php
                        echo esc_url(
                            fd_theme_get_login_url()
                        );
                    ?>"
                >
                    Voltar para o login
                </a>
            </p>
        </div>
    </div>
</section>

<?php

get_footer();