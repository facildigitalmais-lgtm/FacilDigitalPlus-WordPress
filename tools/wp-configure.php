
<?php

if (!defined('ABSPATH')) {
    exit(1);
}

/**
 * Configuracao idempotente da fundacao W1.
 */

function fd_w1_ensure_page(
    string $title,
    string $slug,
    string $content = ''
): int {
    $existing =
        get_page_by_path(
            $slug,
            OBJECT,
            'page'
        );

    if (
        $existing instanceof WP_Post
    ) {
        return (int) $existing->ID;
    }

    $result =
        wp_insert_post(
            [
                'post_type' =>
                    'page',

                'post_status' =>
                    'publish',

                'post_title' =>
                    $title,

                'post_name' =>
                    $slug,

                'post_content' =>
                    $content,
            ],
            true
        );

    if (
        is_wp_error(
            $result
        )
    ) {
        throw new RuntimeException(
            sprintf(
                'Falha ao criar pagina %s: %s',
                $slug,
                $result->get_error_message()
            )
        );
    }

    return (int) $result;
}

$pages = [
    'home' =>
        fd_w1_ensure_page(
            'Inicio',
            'inicio'
        ),

    'shop' =>
        fd_w1_ensure_page(
            'Apostilas',
            'apostilas'
        ),

    'cart' =>
        fd_w1_ensure_page(
            'Carrinho',
            'carrinho',
            '[woocommerce_cart]'
        ),

    'checkout' =>
        fd_w1_ensure_page(
            'Checkout',
            'checkout',
            '[woocommerce_checkout]'
        ),

    'account' =>
        fd_w1_ensure_page(
            'Minha Conta',
            'minha-conta',
            '[woocommerce_my_account]'
        ),

    'login' =>
        fd_w1_ensure_page(
            'Entrar',
            'entrar',
            'Pagina de login Facil Digital+ em desenvolvimento.'
        ),

    'register' =>
        fd_w1_ensure_page(
            'Cadastro',
            'cadastro',
            'Pagina de cadastro Facil Digital+ em desenvolvimento.'
        ),

    'lost_password' =>
        fd_w1_ensure_page(
            'Recuperar senha',
            'recuperar-senha',
            'Recuperacao de senha Facil Digital+ em desenvolvimento.'
        ),

    'about' =>
        fd_w1_ensure_page(
            'Sobre',
            'sobre'
        ),

    'contact' =>
        fd_w1_ensure_page(
            'Contato',
            'contato'
        ),

    'faq' =>
        fd_w1_ensure_page(
            'FAQ',
            'faq'
        ),

    'privacy' =>
        fd_w1_ensure_page(
            'Politica de Privacidade',
            'privacidade'
        ),

    'terms' =>
        fd_w1_ensure_page(
            'Termos de Uso',
            'termos'
        ),
];

update_option(
    'blogname',
    'Facil Digital+'
);

update_option(
    'blogdescription',
    'Apostilas digitais e simulados para concursos publicos.'
);

update_option(
    'show_on_front',
    'page'
);

update_option(
    'page_on_front',
    $pages['home']
);

update_option(
    'timezone_string',
    getenv(
        'WORDPRESS_TIMEZONE'
    )
        ?: 'America/Sao_Paulo'
);

update_option(
    'users_can_register',
    1
);

update_option(
    'default_role',
    'customer'
);

update_option(
    'woocommerce_shop_page_id',
    $pages['shop']
);

update_option(
    'woocommerce_cart_page_id',
    $pages['cart']
);

update_option(
    'woocommerce_checkout_page_id',
    $pages['checkout']
);

update_option(
    'woocommerce_myaccount_page_id',
    $pages['account']
);

update_option(
    'woocommerce_terms_page_id',
    $pages['terms']
);

update_option(
    'woocommerce_default_country',
    'BR'
);

update_option(
    'woocommerce_currency',
    'BRL'
);

update_option(
    'woocommerce_enable_guest_checkout',
    'no'
);

update_option(
    'woocommerce_enable_signup_and_login_from_checkout',
    'yes'
);

update_option(
    'woocommerce_enable_myaccount_registration',
    'yes'
);

global $wp_rewrite;

$wp_rewrite->set_permalink_structure(
    '/%postname%/'
);

$wp_rewrite->flush_rules(
    true
);

echo wp_json_encode(
    [
        'status' =>
            'configured',

        'pages' =>
            $pages,
    ],
    JSON_PRETTY_PRINT
);

echo PHP_EOL;
