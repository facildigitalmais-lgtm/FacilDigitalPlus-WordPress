<?php

declare(strict_types=1);

namespace FacilDigital\Core\Contests;

use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Core\Capabilities;

final class ContestModule implements ModuleInterface
{
    public const TAXONOMY = 'fd_concurso';

    public function register(): void
    {
        add_action(
            'init',
            [$this, 'registerTaxonomy'],
            20
        );
    }

    public function registerTaxonomy(): void
    {
        $labels = [
            'name' => __('Concursos', 'facil-digital-core'),
            'singular_name' => __('Concurso', 'facil-digital-core'),
            'menu_name' => __('Concursos', 'facil-digital-core'),
            'all_items' => __('Todos os concursos', 'facil-digital-core'),
            'edit_item' => __('Editar concurso', 'facil-digital-core'),
            'view_item' => __('Ver concurso', 'facil-digital-core'),
            'update_item' => __('Atualizar concurso', 'facil-digital-core'),
            'add_new_item' => __('Adicionar concurso', 'facil-digital-core'),
            'new_item_name' => __('Nome do novo concurso', 'facil-digital-core'),
            'search_items' => __('Buscar concursos', 'facil-digital-core'),
            'not_found' => __('Nenhum concurso encontrado.', 'facil-digital-core'),
        ];

        register_taxonomy(
            self::TAXONOMY,
            ['product'],
            [
                'labels' => $labels,
                'public' => true,
                'publicly_queryable' => true,
                'hierarchical' => true,
                'show_ui' => true,
                'show_admin_column' => true,
                'show_in_rest' => true,
                'query_var' => true,
                'rewrite' => [
                    'slug' => 'concurso',
                    'with_front' => false,
                    'hierarchical' => true,
                ],
                'capabilities' => [
                    'manage_terms' => Capabilities::MANAGE_CONTESTS,
                    'edit_terms' => Capabilities::MANAGE_CONTESTS,
                    'delete_terms' => Capabilities::MANAGE_CONTESTS,
                    'assign_terms' => Capabilities::MANAGE_APOSTILAS,
                ],
            ]
        );
    }
}
