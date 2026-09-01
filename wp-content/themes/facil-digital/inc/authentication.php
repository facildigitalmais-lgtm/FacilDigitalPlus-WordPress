<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function fd_theme_auth_add_error(
    string $message
): void {
    if (
        !isset(
            $GLOBALS[
                'fd_theme_auth_errors'
            ]
        )
        || !is_array(
            $GLOBALS[
                'fd_theme_auth_errors'
            ]
        )
    ) {
        $GLOBALS[
            'fd_theme_auth_errors'
        ] = [];
    }

    $GLOBALS[
        'fd_theme_auth_errors'
    ][] = $message;
}

function fd_theme_auth_get_errors(): array
{
    $errors =
        $GLOBALS[
            'fd_theme_auth_errors'
        ] ?? [];

    return is_array($errors)
        ? $errors
        : [];
}

function fd_theme_auth_set_old(
    string $key,
    string $value
): void {
    if (
        !isset(
            $GLOBALS[
                'fd_theme_auth_old'
            ]
        )
        || !is_array(
            $GLOBALS[
                'fd_theme_auth_old'
            ]
        )
    ) {
        $GLOBALS[
            'fd_theme_auth_old'
        ] = [];
    }

    $GLOBALS[
        'fd_theme_auth_old'
    ][$key] = $value;
}

function fd_theme_auth_old(
    string $key
): string {
    $value =
        $GLOBALS[
            'fd_theme_auth_old'
        ][$key] ?? '';

    return is_string($value)
        ? $value
        : '';
}

function fd_theme_auth_nonce_is_valid(
    string $action
): bool {
    $nonce = '';

    if (
        isset($_POST['fd_nonce'])
        && is_string($_POST['fd_nonce'])
    ) {
        $nonce =
            sanitize_text_field(
                wp_unslash(
                    $_POST['fd_nonce']
                )
            );
    }

    if (
        $nonce === ''
        || !wp_verify_nonce(
            $nonce,
            $action
        )
    ) {
        fd_theme_auth_add_error(
            __(
                'Não foi possível validar esta solicitação. Atualize a página e tente novamente.',
                'facil-digital'
            )
        );

        return false;
    }

    return true;
}

function fd_theme_auth_redirect_target(
    string $fallback
): string {
    $candidate = '';

    if (
        isset($_POST['redirect_to'])
        && is_string(
            $_POST['redirect_to']
        )
    ) {
        $candidate =
            esc_url_raw(
                wp_unslash(
                    $_POST['redirect_to']
                )
            );
    }

    return wp_validate_redirect(
        $candidate,
        $fallback
    );
}

function fd_theme_handle_login(): void
{
    if (
        !fd_theme_auth_nonce_is_valid(
            'fd_login'
        )
    ) {
        return;
    }

    $login = '';

    if (
        isset($_POST['user_login'])
        && is_string(
            $_POST['user_login']
        )
    ) {
        $login =
            sanitize_text_field(
                wp_unslash(
                    $_POST['user_login']
                )
            );
    }

    $password = '';

    if (
        isset($_POST['user_password'])
        && is_string(
            $_POST['user_password']
        )
    ) {
        $password =
            (string) wp_unslash(
                $_POST['user_password']
            );
    }

    $remember =
        isset($_POST['remember']);

    fd_theme_auth_set_old(
        'user_login',
        $login
    );

    if (
        $login === ''
        || $password === ''
    ) {
        fd_theme_auth_add_error(
            __(
                'Informe seu e-mail ou usuário e sua senha.',
                'facil-digital'
            )
        );

        return;
    }

    $user =
        wp_signon(
            [
                'user_login'    => $login,
                'user_password' => $password,
                'remember'      => $remember,
            ],
            is_ssl()
        );

    if (is_wp_error($user)) {
        fd_theme_auth_add_error(
            __(
                'E-mail, usuário ou senha inválidos.',
                'facil-digital'
            )
        );

        return;
    }

    wp_set_current_user(
        $user->ID
    );

    $redirect =
        fd_theme_auth_redirect_target(
            fd_theme_get_account_url()
        );

    wp_safe_redirect(
        $redirect
    );

    exit;
}

function fd_theme_handle_register(): void
{
    if (
        !fd_theme_auth_nonce_is_valid(
            'fd_register'
        )
    ) {
        return;
    }

    $firstName = '';
    $lastName  = '';
    $email     = '';
    $password  = '';
    $confirm   = '';

    if (
        isset($_POST['first_name'])
        && is_string(
            $_POST['first_name']
        )
    ) {
        $firstName =
            sanitize_text_field(
                wp_unslash(
                    $_POST['first_name']
                )
            );
    }

    if (
        isset($_POST['last_name'])
        && is_string(
            $_POST['last_name']
        )
    ) {
        $lastName =
            sanitize_text_field(
                wp_unslash(
                    $_POST['last_name']
                )
            );
    }

    if (
        isset($_POST['email'])
        && is_string(
            $_POST['email']
        )
    ) {
        $email =
            sanitize_email(
                wp_unslash(
                    $_POST['email']
                )
            );
    }

    if (
        isset($_POST['password'])
        && is_string(
            $_POST['password']
        )
    ) {
        $password =
            (string) wp_unslash(
                $_POST['password']
            );
    }

    if (
        isset($_POST['password_confirm'])
        && is_string(
            $_POST['password_confirm']
        )
    ) {
        $confirm =
            (string) wp_unslash(
                $_POST['password_confirm']
            );
    }

    $acceptedTerms =
        isset($_POST['accept_terms']);

    fd_theme_auth_set_old(
        'first_name',
        $firstName
    );

    fd_theme_auth_set_old(
        'last_name',
        $lastName
    );

    fd_theme_auth_set_old(
        'email',
        $email
    );

    if (
        get_option(
            'woocommerce_enable_myaccount_registration',
            'no'
        ) !== 'yes'
    ) {
        fd_theme_auth_add_error(
            __(
                'O cadastro de novas contas está temporariamente indisponível.',
                'facil-digital'
            )
        );

        return;
    }

    if (
        $firstName === ''
        || $lastName === ''
    ) {
        fd_theme_auth_add_error(
            __(
                'Informe seu nome e sobrenome.',
                'facil-digital'
            )
        );
    }

    if (
        $email === ''
        || !is_email($email)
    ) {
        fd_theme_auth_add_error(
            __(
                'Informe um endereço de e-mail válido.',
                'facil-digital'
            )
        );
    }

    if (
        $email !== ''
        && email_exists($email)
    ) {
        fd_theme_auth_add_error(
            __(
                'Já existe uma conta cadastrada com este e-mail.',
                'facil-digital'
            )
        );
    }

    if (
        strlen($password) < 8
    ) {
        fd_theme_auth_add_error(
            __(
                'A senha deve possuir pelo menos 8 caracteres.',
                'facil-digital'
            )
        );
    }

    if (
        $password !== $confirm
    ) {
        fd_theme_auth_add_error(
            __(
                'As senhas informadas não coincidem.',
                'facil-digital'
            )
        );
    }

    if (!$acceptedTerms) {
        fd_theme_auth_add_error(
            __(
                'Você precisa aceitar os Termos de Uso e a Política de Privacidade.',
                'facil-digital'
            )
        );
    }

    if (
        fd_theme_auth_get_errors()
        !== []
    ) {
        return;
    }

    if (
        !function_exists(
            'wc_create_new_customer'
        )
    ) {
        fd_theme_auth_add_error(
            __(
                'O serviço de cadastro está temporariamente indisponível.',
                'facil-digital'
            )
        );

        return;
    }

    $customerId =
        wc_create_new_customer(
            $email,
            '',
            $password,
            [
                'first_name' =>
                    $firstName,

                'last_name' =>
                    $lastName,

                'display_name' =>
                    trim(
                        $firstName
                        . ' '
                        . $lastName
                    ),
            ]
        );

    if (is_wp_error($customerId)) {
        fd_theme_auth_add_error(
            __(
                'Não foi possível criar sua conta. Verifique os dados e tente novamente.',
                'facil-digital'
            )
        );

        return;
    }

    if (
        function_exists(
            'wc_set_customer_auth_cookie'
        )
    ) {
        wc_set_customer_auth_cookie(
            (int) $customerId
        );
    } else {
        wp_set_current_user(
            (int) $customerId
        );

        wp_set_auth_cookie(
            (int) $customerId,
            true,
            is_ssl()
        );
    }

    $redirect =
        fd_theme_auth_redirect_target(
            fd_theme_get_account_url()
        );

    wp_safe_redirect(
        $redirect
    );

    exit;
}

function fd_theme_handle_lost_password(): void
{
    if (
        !fd_theme_auth_nonce_is_valid(
            'fd_lost_password'
        )
    ) {
        return;
    }

    $identifier = '';

    if (
        isset($_POST['user_login'])
        && is_string(
            $_POST['user_login']
        )
    ) {
        $identifier =
            sanitize_text_field(
                wp_unslash(
                    $_POST['user_login']
                )
            );
    }

    fd_theme_auth_set_old(
        'user_login',
        $identifier
    );

    if ($identifier === '') {
        fd_theme_auth_add_error(
            __(
                'Informe seu e-mail ou nome de usuário.',
                'facil-digital'
            )
        );

        return;
    }

    retrieve_password(
        $identifier
    );

    $redirect =
        add_query_arg(
            'sent',
            '1',
            fd_theme_get_lost_password_url()
        );

    wp_safe_redirect(
        $redirect
    );

    exit;
}

function fd_theme_handle_auth_requests(): void
{
    $authTemplates = [
        'templates/page-login.php',
        'templates/page-register.php',
        'templates/page-lost-password.php',
    ];

    $isAuthPage = false;

    foreach (
        $authTemplates
        as $template
    ) {
        if (
            is_page_template(
                $template
            )
        ) {
            $isAuthPage = true;
            break;
        }
    }

    if (!$isAuthPage) {
        return;
    }

    if (is_user_logged_in()) {
        wp_safe_redirect(
            fd_theme_get_account_url()
        );

        exit;
    }

    $method =
        isset(
            $_SERVER[
                'REQUEST_METHOD'
            ]
        )
        ? strtoupper(
            sanitize_text_field(
                wp_unslash(
                    $_SERVER[
                        'REQUEST_METHOD'
                    ]
                )
            )
        )
        : 'GET';

    if ($method !== 'POST') {
        return;
    }

    $action = '';

    if (
        isset(
            $_POST[
                'fd_auth_action'
            ]
        )
        && is_string(
            $_POST[
                'fd_auth_action'
            ]
        )
    ) {
        $action =
            sanitize_key(
                wp_unslash(
                    $_POST[
                        'fd_auth_action'
                    ]
                )
            );
    }

    if (
        $action === 'login'
        && is_page_template(
            'templates/page-login.php'
        )
    ) {
        fd_theme_handle_login();
        return;
    }

    if (
        $action === 'register'
        && is_page_template(
            'templates/page-register.php'
        )
    ) {
        fd_theme_handle_register();
        return;
    }

    if (
        $action === 'lost_password'
        && is_page_template(
            'templates/page-lost-password.php'
        )
    ) {
        fd_theme_handle_lost_password();
    }
}

add_action(
    'template_redirect',
    'fd_theme_handle_auth_requests',
    1
);
