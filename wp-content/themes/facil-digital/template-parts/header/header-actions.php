<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$accountUrl =
    fd_theme_get_account_url();

$cartUrl =
    fd_theme_get_cart_url();

$cartCount =
    fd_theme_get_cart_count();

?>

<div class="fd-header-actions">
    <button
        type="button"
        class="fd-icon-button fd-header-search-toggle"
        aria-expanded="false"
        aria-controls="fd-header-search"
        aria-label="<?php
            echo esc_attr__(
                'Abrir busca',
                'facil-digital'
            );
        ?>"
        data-fd-search-toggle
    >
        <?php
        echo fd_theme_icon(
            'search'
        );
        ?>
    </button>

    <a
        class="fd-header-account"
        href="<?php
            echo esc_url(
                $accountUrl
            );
        ?>"
    >
        <?php
        echo fd_theme_icon(
            'user'
        );
        ?>

        <span class="fd-header-account__label">
            <?php
            echo esc_html(
                is_user_logged_in()
                    ? __(
                        'Minha conta',
                        'facil-digital'
                    )
                    : __(
                        'Entrar',
                        'facil-digital'
                    )
            );
            ?>
        </span>
    </a>

    <a
        class="fd-header-cart"
        href="<?php
            echo esc_url(
                $cartUrl
            );
        ?>"
        aria-label="<?php
            echo esc_attr__(
                'Ver carrinho',
                'facil-digital'
            );
        ?>"
    >
        <?php
        echo fd_theme_icon(
            'cart'
        );
        ?>

        <span
            class="fd-header-cart__count"
            aria-label="<?php
                echo esc_attr(
                    sprintf(
                        _n(
                            '%d item no carrinho',
                            '%d itens no carrinho',
                            $cartCount,
                            'facil-digital'
                        ),
                        $cartCount
                    )
                );
            ?>"
        >
            <?php
            echo esc_html(
                (string) $cartCount
            );
            ?>
        </span>
    </a>

    <button
        type="button"
        class="fd-menu-toggle"
        aria-expanded="false"
        aria-controls="fd-primary-navigation"
        aria-label="<?php
            echo esc_attr__(
                'Abrir menu',
                'facil-digital'
            );
        ?>"
        data-fd-menu-toggle
    >
        <span
            class="fd-menu-toggle__bars"
            aria-hidden="true"
        >
            <span></span>
            <span></span>
            <span></span>
        </span>
    </button>
</div>