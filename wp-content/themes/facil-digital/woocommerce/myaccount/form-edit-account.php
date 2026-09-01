<?php
/**
 * Edit account form.
 *
 * Mantém o processamento oficial do WooCommerce.
 *
 * @package FacilDigital
 * @version 11.0.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

do_action(
    'woocommerce_before_edit_account_form'
);

?>

<header class="fd-account-section-header">
    <span class="fd-eyebrow">
        <?php
        echo esc_html__(
            'Sua conta',
            'facil-digital'
        );
        ?>
    </span>

    <h2>
        <?php
        echo esc_html__(
            'Meus dados',
            'facil-digital'
        );
        ?>
    </h2>

    <p>
        <?php
        echo esc_html__(
            'Mantenha seus dados pessoais e informações de acesso atualizados.',
            'facil-digital'
        );
        ?>
    </p>
</header>

<form
    class="woocommerce-EditAccountForm edit-account fd-account-edit-form"
    action=""
    method="post"
    <?php
    do_action(
        'woocommerce_edit_account_form_tag'
    );
    ?>
>
    <?php
    do_action(
        'woocommerce_edit_account_form_start'
    );
    ?>

    <section
        class="fd-account-form-section"
        id="dados"
        aria-labelledby="fd-account-data-title"
    >
        <header class="fd-account-form-section__header">
            <h3 id="fd-account-data-title">
                <?php
                echo esc_html__(
                    'Dados pessoais',
                    'facil-digital'
                );
                ?>
            </h3>

            <p>
                <?php
                echo esc_html__(
                    'Essas informações identificam sua conta na plataforma.',
                    'facil-digital'
                );
                ?>
            </p>
        </header>

        <div class="fd-account-form-grid">
            <p
                class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first"
            >
                <label for="account_first_name">
                    <?php
                    echo esc_html__(
                        'Nome',
                        'facil-digital'
                    );
                    ?>

                    <span
                        class="required"
                        aria-hidden="true"
                    >*</span>

                    <span class="screen-reader-text">
                        <?php
                        echo esc_html__(
                            'Obrigatório',
                            'facil-digital'
                        );
                        ?>
                    </span>
                </label>

                <input
                    type="text"
                    class="woocommerce-Input woocommerce-Input--text input-text"
                    name="account_first_name"
                    id="account_first_name"
                    autocomplete="given-name"
                    value="<?php
                        echo esc_attr(
                            $user->first_name
                        );
                    ?>"
                    required
                    aria-required="true"
                >
            </p>

            <p
                class="woocommerce-form-row woocommerce-form-row--last form-row form-row-last"
            >
                <label for="account_last_name">
                    <?php
                    echo esc_html__(
                        'Sobrenome',
                        'facil-digital'
                    );
                    ?>

                    <span
                        class="required"
                        aria-hidden="true"
                    >*</span>

                    <span class="screen-reader-text">
                        <?php
                        echo esc_html__(
                            'Obrigatório',
                            'facil-digital'
                        );
                        ?>
                    </span>
                </label>

                <input
                    type="text"
                    class="woocommerce-Input woocommerce-Input--text input-text"
                    name="account_last_name"
                    id="account_last_name"
                    autocomplete="family-name"
                    value="<?php
                        echo esc_attr(
                            $user->last_name
                        );
                    ?>"
                    required
                    aria-required="true"
                >
            </p>
        </div>

        <p
            class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide"
        >
            <label for="account_display_name">
                <?php
                echo esc_html__(
                    'Nome de exibição',
                    'facil-digital'
                );
                ?>

                <span
                    class="required"
                    aria-hidden="true"
                >*</span>
            </label>

            <input
                type="text"
                class="woocommerce-Input woocommerce-Input--text input-text"
                name="account_display_name"
                id="account_display_name"
                aria-describedby="account_display_name_description"
                value="<?php
                    echo esc_attr(
                        $user->display_name
                    );
                ?>"
                required
                aria-required="true"
            >

            <small
                id="account_display_name_description"
                class="fd-account-field-help"
            >
                <?php
                echo esc_html__(
                    'Este é o nome exibido dentro da sua conta.',
                    'facil-digital'
                );
                ?>
            </small>
        </p>

        <p
            class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide"
        >
            <label for="account_email">
                <?php
                echo esc_html__(
                    'E-mail',
                    'facil-digital'
                );
                ?>

                <span
                    class="required"
                    aria-hidden="true"
                >*</span>
            </label>

            <input
                type="email"
                class="woocommerce-Input woocommerce-Input--email input-text"
                name="account_email"
                id="account_email"
                autocomplete="email"
                value="<?php
                    echo esc_attr(
                        $user->user_email
                    );
                ?>"
                required
                aria-required="true"
            >
        </p>

        <?php
        do_action(
            'woocommerce_edit_account_form_fields'
        );
        ?>
    </section>

    <section
        class="fd-account-form-section fd-account-security"
        id="seguranca"
        aria-labelledby="fd-account-security-title"
    >
        <header class="fd-account-form-section__header">
            <span class="fd-account-security__badge">
                <?php
                echo esc_html__(
                    'Segurança',
                    'facil-digital'
                );
                ?>
            </span>

            <h3 id="fd-account-security-title">
                <?php
                echo esc_html__(
                    'Alterar senha',
                    'facil-digital'
                );
                ?>
            </h3>

            <p>
                <?php
                echo esc_html__(
                    'Deixe os campos abaixo vazios se não quiser alterar sua senha.',
                    'facil-digital'
                );
                ?>
            </p>
        </header>

        <fieldset>
            <legend class="screen-reader-text">
                <?php
                echo esc_html__(
                    'Alteração de senha',
                    'facil-digital'
                );
                ?>
            </legend>

            <p
                class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide"
            >
                <label for="password_current">
                    <?php
                    echo esc_html__(
                        'Senha atual',
                        'facil-digital'
                    );
                    ?>
                </label>

                <input
                    type="password"
                    class="woocommerce-Input woocommerce-Input--password input-text"
                    name="password_current"
                    id="password_current"
                    autocomplete="current-password"
                >
            </p>

            <p
                class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide"
            >
                <label for="password_1">
                    <?php
                    echo esc_html__(
                        'Nova senha',
                        'facil-digital'
                    );
                    ?>
                </label>

                <input
                    type="password"
                    class="woocommerce-Input woocommerce-Input--password input-text"
                    name="password_1"
                    id="password_1"
                    autocomplete="new-password"
                >
            </p>

            <p
                class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide"
            >
                <label for="password_2">
                    <?php
                    echo esc_html__(
                        'Confirmar nova senha',
                        'facil-digital'
                    );
                    ?>
                </label>

                <input
                    type="password"
                    class="woocommerce-Input woocommerce-Input--password input-text"
                    name="password_2"
                    id="password_2"
                    autocomplete="new-password"
                >
            </p>
        </fieldset>
    </section>

    <?php
    do_action(
        'woocommerce_edit_account_form'
    );
    ?>

    <div class="fd-account-form-actions">
        <?php
        wp_nonce_field(
            'save_account_details',
            'save-account-details-nonce'
        );
        ?>

        <button
            type="submit"
            class="woocommerce-Button button fd-account-save"
            name="save_account_details"
            value="<?php
                echo esc_attr__(
                    'Salvar alterações',
                    'facil-digital'
                );
            ?>"
        >
            <?php
            echo esc_html__(
                'Salvar alterações',
                'facil-digital'
            );
            ?>
        </button>

        <input
            type="hidden"
            name="action"
            value="save_account_details"
        >
    </div>

    <?php
    do_action(
        'woocommerce_edit_account_form_end'
    );
    ?>
</form>

<?php

do_action(
    'woocommerce_after_edit_account_form'
);
