<?php

declare(strict_types=1);

namespace FacilDigital\Core\Contests;

use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Core\Capabilities;

final class ContestModule implements ModuleInterface
{
    public const TAXONOMY = 'fd_concurso';

    public const HOME_FEATURED_META =
        '_fd_home_featured';

    private const HOME_FEATURED_NONCE_ACTION =
        'fd_save_contest_home_featured';

    private const HOME_FEATURED_NONCE_NAME =
        '_fd_contest_home_featured_nonce';

    public function register(): void
    {
        add_action(
            'init',
            [$this, 'registerTaxonomy'],
            20
        );

        add_action(
            self::TAXONOMY . '_add_form_fields',
            [$this, 'renderAddFields']
        );

        add_action(
            self::TAXONOMY . '_edit_form_fields',
            [$this, 'renderEditFields']
        );

        add_action(
            'created_' . self::TAXONOMY,
            [$this, 'saveHomeFeaturedMeta']
        );

        add_action(
            'edited_' . self::TAXONOMY,
            [$this, 'saveHomeFeaturedMeta']
        );
    }

    public function registerTaxonomy(): void
    {
        $labels = [
            'name' =>
                __('Concursos', 'facil-digital-core'),

            'singular_name' =>
                __('Concurso', 'facil-digital-core'),

            'menu_name' =>
                __('Concursos', 'facil-digital-core'),

            'all_items' =>
                __('Todos os concursos', 'facil-digital-core'),

            'edit_item' =>
                __('Editar concurso', 'facil-digital-core'),

            'view_item' =>
                __('Ver concurso', 'facil-digital-core'),

            'update_item' =>
                __('Atualizar concurso', 'facil-digital-core'),

            'add_new_item' =>
                __('Adicionar concurso', 'facil-digital-core'),

            'new_item_name' =>
                __('Nome do novo concurso', 'facil-digital-core'),

            'search_items' =>
                __('Buscar concursos', 'facil-digital-core'),

            'not_found' =>
                __('Nenhum concurso encontrado.', 'facil-digital-core'),
        ];

        register_taxonomy(
            self::TAXONOMY,
            ['product'],
            [
                'labels' =>
                    $labels,

                'public' =>
                    true,

                'publicly_queryable' =>
                    true,

                'hierarchical' =>
                    true,

                'show_ui' =>
                    true,

                'show_admin_column' =>
                    true,

                'show_in_rest' =>
                    true,

                'query_var' =>
                    true,

                'rewrite' => [
                    'slug' =>
                        'concurso',

                    'with_front' =>
                        false,

                    'hierarchical' =>
                        true,
                ],

                'capabilities' => [
                    'manage_terms' =>
                        Capabilities::MANAGE_CONTESTS,

                    'edit_terms' =>
                        Capabilities::MANAGE_CONTESTS,

                    'delete_terms' =>
                        Capabilities::MANAGE_CONTESTS,

                    'assign_terms' =>
                        Capabilities::MANAGE_APOSTILAS,
                ],
            ]
        );
    }

    public function renderAddFields(): void
    {
        ?>
        <div class="form-field">
            <label for="fd-home-featured">
                <?php
                echo esc_html__(
                    'Destaque na Home',
                    'facil-digital-core'
                );
                ?>
            </label>

            <label>
                <input
                    id="fd-home-featured"
                    type="checkbox"
                    name="fd_home_featured"
                    value="yes"
                >

                <?php
                echo esc_html__(
                    'Exibir este concurso na seção de destaques da página inicial.',
                    'facil-digital-core'
                );
                ?>
            </label>

            <?php
            wp_nonce_field(
                self::HOME_FEATURED_NONCE_ACTION,
                self::HOME_FEATURED_NONCE_NAME
            );
            ?>
        </div>
        <?php
    }

    public function renderEditFields(
        \WP_Term $term
    ): void {
        $featured =
            get_term_meta(
                $term->term_id,
                self::HOME_FEATURED_META,
                true
            ) === 'yes';

        ?>
        <tr class="form-field">
            <th scope="row">
                <label for="fd-home-featured">
                    <?php
                    echo esc_html__(
                        'Destaque na Home',
                        'facil-digital-core'
                    );
                    ?>
                </label>
            </th>

            <td>
                <label>
                    <input
                        id="fd-home-featured"
                        type="checkbox"
                        name="fd_home_featured"
                        value="yes"
                        <?php checked($featured); ?>
                    >

                    <?php
                    echo esc_html__(
                        'Exibir este concurso na seção de destaques da página inicial.',
                        'facil-digital-core'
                    );
                    ?>
                </label>

                <?php
                wp_nonce_field(
                    self::HOME_FEATURED_NONCE_ACTION,
                    self::HOME_FEATURED_NONCE_NAME
                );
                ?>
            </td>
        </tr>
        <?php
    }

    public function saveHomeFeaturedMeta(
        int $termId
    ): void {
        if (
            !current_user_can(
                Capabilities::MANAGE_CONTESTS
            )
        ) {
            return;
        }

        $nonce =
            isset(
                $_POST[
                    self::HOME_FEATURED_NONCE_NAME
                ]
            )
            && is_string(
                $_POST[
                    self::HOME_FEATURED_NONCE_NAME
                ]
            )
                ? sanitize_text_field(
                    wp_unslash(
                        $_POST[
                            self::HOME_FEATURED_NONCE_NAME
                        ]
                    )
                )
                : '';

        if (
            $nonce === ''
            || !wp_verify_nonce(
                $nonce,
                self::HOME_FEATURED_NONCE_ACTION
            )
        ) {
            return;
        }

        if (
            isset($_POST['fd_home_featured'])
            && $_POST['fd_home_featured'] === 'yes'
        ) {
            update_term_meta(
                $termId,
                self::HOME_FEATURED_META,
                'yes'
            );

            return;
        }

        delete_term_meta(
            $termId,
            self::HOME_FEATURED_META
        );
    }
}
