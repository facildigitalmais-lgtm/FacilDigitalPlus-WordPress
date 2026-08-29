cat > tools/wp-configure.php <<'PHP'
<?php

if (!defined('ABSPATH')) {
    exit;
}

function fd_configure_page(
    string $title,
    string $slug,
    string $content = '',
    ?string $template = null
): int {
    $existing =
        get_page_by_path(
            $slug,
            OBJECT,
            'page'
        );

    if ($existing instanceof WP_Post) {
        $pageId =
            (int) $existing->ID;

        $updated =
            wp_update_post(
                [
                    'ID' =>
                        $pageId,

                    'post_title' =>
                        $title,

                    'post_status' =>
                        'publish',
                ],
                true
            );

        if (is_wp_error($updated)) {
            throw new RuntimeException(
                sprintf(
                    'Falha ao atualizar pagina %s: %s',
                    $slug,
                    $updated->get_error_message()
                )
            );
        }
    } else {
        $pageId =
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

        if (is_wp_error($pageId)) {
            throw new RuntimeException(
                sprintf(
                    'Falha ao criar pagina %s: %s',
                    $slug,
                    $pageId->get_error_message()
                )
            );
        }

        $pageId =
            (int) $pageId;
    }

    if ($template !== null) {
        update_post_meta(
            $pageId,
            '_wp_page_template',
            $template
        );
    } else {
        delete_post_meta(
            $pageId,
            '_wp_page_template'
        );
    }

    return $pageId;
}

$pages = [];

$pages['home'] =
    fd_configure_page(
        'Inicio',
        'inicio'
    );

$pages['shop'] =
    fd_configure_page(
        'Apostilas',
        'apostilas'
    );

$pages['cart'] =
    fd_configure_page(
        'Carrinho',
        'carrinho',
        '[woocommerce_cart]'
    );

$pages['checkout'] =
    fd_configure_page(
        'Checkout',
        'checkout',
        '[woocommerce_checkout]'
    );

$pages['account'] =
    fd_configure_page(
        'Minha Conta',
        'minha-conta',
        '[woocommerce_my_account]'
    );

$pages['login'] =
    fd_configure_page(
        'Entrar',
        'entrar',
        '',
        'templates/page-login.php'
    );

$pages['register'] =
    fd_configure_page(
        'Cadastro',
        'cadastro',
        '',
        'templates/page-register.php'
    );

$pages['lost_password'] =
    fd_configure_page(
        'Recuperar Senha',
        'recuperar-senha',
        '',
        'templates/page-lost-password.php'
    );

$pages['about'] =
    fd_configure_page(
        'Sobre',
        'sobre',
        '',
        'templates/page-about.php'
    );

$pages['contact'] =
    fd_configure_page(
        'Contato',
        'contato',
        '',
        'templates/page-contact.php'
    );

$pages['faq'] =
    fd_configure_page(
        'FAQ',
        'faq',
        '',
        'templates/page-faq.php'
    );

$pages['privacy'] =
    fd_configure_page(
        'Politica de Privacidade',
        'privacidade',
        '',
        'templates/page-privacy.php'
    );

$pages['terms'] =
    fd_configure_page(
        'Termos de Uso',
        'termos',
        '',
        'templates/page-terms.php'
    );

/*
 * WordPress.
 */

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
    'America/Sao_Paulo'
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
    'wp_page_for_privacy_policy',
    $pages['privacy']
);

/*
 * Paginas WooCommerce.
 */

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

/*
 * Localizacao comercial.
 */

update_option(
    'woocommerce_default_country',
    'BR'
);

update_option(
    'woocommerce_currency',
    'BRL'
);

/*
 * Formatacao monetaria brasileira.
 *
 * O valor interno continua utilizando
 * representacao decimal normalizada,
 * por exemplo:
 *
 * 14.50
 *
 * A apresentacao publica passa a ser:
 *
 * R$ 14,50
 */

update_option(
    'woocommerce_currency_pos',
    'left_space'
);

update_option(
    'woocommerce_price_thousand_sep',
    '.'
);

update_option(
    'woocommerce_price_decimal_sep',
    ','
);

update_option(
    'woocommerce_price_num_decimals',
    '2'
);

/*
 * Checkout e contas.
 */

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

update_option(
    'woocommerce_registration_generate_username',
    'yes'
);

update_option(
    'woocommerce_registration_generate_password',
    'no'
);

/*
 * URLs.
 */

update_option(
    'permalink_structure',
    '/%postname%/'
);

flush_rewrite_rules(
    false
);

echo wp_json_encode(
    [
        'status' =>
            'configured',

        'pages' =>
            $pages,

        'theme' =>
            'facil-digital',

        'theme_version' =>
            wp_get_theme(
                'facil-digital'
            )->get(
                'Version'
            ),

        'woocommerce' => [
            'country' =>
                get_option(
                    'woocommerce_default_country'
                ),

            'currency' =>
                get_option(
                    'woocommerce_currency'
                ),

            'currency_position' =>
                get_option(
                    'woocommerce_currency_pos'
                ),

            'thousand_separator' =>
                get_option(
                    'woocommerce_price_thousand_sep'
                ),

            'decimal_separator' =>
                get_option(
                    'woocommerce_price_decimal_sep'
                ),

            'decimals' =>
                get_option(
                    'woocommerce_price_num_decimals'
                ),
        ],
    ],
    JSON_PRETTY_PRINT
);

echo PHP_EOL;