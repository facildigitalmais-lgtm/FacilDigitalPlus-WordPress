<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$total =
    (int) wc_get_loop_prop(
        'total',
        0
    );

$perPage =
    (int) wc_get_loop_prop(
        'per_page',
        0
    );

$currentPage =
    max(
        1,
        (int) wc_get_loop_prop(
            'current_page',
            1
        )
    );

$first = 0;
$last = 0;

if (
    $total > 0
    && $perPage > 0
) {
    $first =
        (($currentPage - 1) * $perPage)
        + 1;

    $last =
        min(
            $first + $perPage - 1,
            $total
        );
}

$orderby =
    fd_theme_catalog_current_orderby();

$options =
    fd_theme_catalog_orderby_options();

$contests =
    function_exists(
        'fd_theme_catalog_contests'
    )
        ? fd_theme_catalog_contests()
        : [];

$currentContest =
    function_exists(
        'fd_theme_catalog_current_contest'
    )
        ? fd_theme_catalog_current_contest()
        : '';

$boards =
    function_exists(
        'fd_theme_catalog_boards'
    )
        ? fd_theme_catalog_boards()
        : [];

$positions =
    function_exists(
        'fd_theme_catalog_positions'
    )
        ? fd_theme_catalog_positions()
        : [];

$currentBoard =
    function_exists(
        'fd_theme_catalog_current_board'
    )
        ? fd_theme_catalog_current_board()
        : '';

$currentPosition =
    function_exists(
        'fd_theme_catalog_current_position'
    )
        ? fd_theme_catalog_current_position()
        : '';

$currentMinPrice =
    function_exists(
        'fd_theme_catalog_current_price'
    )
        ? fd_theme_catalog_current_price(
            'min_price'
        )
        : '';

$currentMaxPrice =
    function_exists(
        'fd_theme_catalog_current_price'
    )
        ? fd_theme_catalog_current_price(
            'max_price'
        )
        : '';

$currentSearch =
    function_exists(
        'fd_theme_catalog_current_search'
    )
        ? fd_theme_catalog_current_search()
        : '';

$hasActiveFilters =
    function_exists(
        'fd_theme_catalog_has_active_filters'
    )
        && fd_theme_catalog_has_active_filters();

?>

<div class="fd-catalog-toolbar">
    <div class="fd-catalog-toolbar__top">
        <p class="fd-catalog-toolbar__count">
            <?php
            if (
                $first > 0
                && $last > 0
            ) {
                printf(
                    esc_html__(
                        'Exibindo %1$d–%2$d de %3$d apostilas',
                        'facil-digital'
                    ),
                    $first,
                    $last,
                    $total
                );
            } else {
                printf(
                    esc_html(
                        _n(
                            '%d apostila encontrada',
                            '%d apostilas encontradas',
                            $total,
                            'facil-digital'
                        )
                    ),
                    $total
                );
            }
            ?>
        </p>

        <?php if ($hasActiveFilters) : ?>
            <a
                class="fd-catalog-clear"
                href="<?php
                    echo esc_url(
                        fd_theme_get_shop_url()
                    );
                ?>"
            >
                <?php
                echo esc_html__(
                    'Limpar filtros',
                    'facil-digital'
                );
                ?>
            </a>
        <?php endif; ?>
    </div>

    <form
        class="fd-catalog-ordering"
        method="get"
        action="<?php
            echo esc_url(
                fd_theme_get_shop_url()
            );
        ?>"
    >
        <?php if ($currentSearch !== '') : ?>
            <input
                type="hidden"
                name="busca"
                value="<?php
                    echo esc_attr(
                        $currentSearch
                    );
                ?>"
            >
        <?php endif; ?>

        <div class="fd-catalog-filter-grid">
            <?php if ($contests !== []) : ?>
                <div class="fd-catalog-filter-field">
                    <label for="fd-catalog-contest">
                        <?php
                        echo esc_html__(
                            'Concurso',
                            'facil-digital'
                        );
                        ?>
                    </label>

                    <select
                        id="fd-catalog-contest"
                        name="concurso"
                        data-fd-autosubmit
                    >
                        <option value="">
                            Todos
                        </option>

                        <?php foreach ($contests as $contest) : ?>
                            <option
                                value="<?php
                                    echo esc_attr(
                                        $contest->slug
                                    );
                                ?>"
                                <?php
                                selected(
                                    $currentContest,
                                    $contest->slug
                                );
                                ?>
                            >
                                <?php
                                echo esc_html(
                                    $contest->name
                                );
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($boards !== []) : ?>
                <div class="fd-catalog-filter-field">
                    <label for="fd-catalog-board">
                        Banca
                    </label>

                    <select
                        id="fd-catalog-board"
                        name="banca"
                        data-fd-autosubmit
                    >
                        <option value="">
                            Todas
                        </option>

                        <?php foreach ($boards as $board) : ?>
                            <option
                                value="<?php
                                    echo esc_attr(
                                        $board
                                    );
                                ?>"
                                <?php
                                selected(
                                    $currentBoard,
                                    $board
                                );
                                ?>
                            >
                                <?php
                                echo esc_html($board);
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($positions !== []) : ?>
                <div class="fd-catalog-filter-field">
                    <label for="fd-catalog-position">
                        Cargo
                    </label>

                    <select
                        id="fd-catalog-position"
                        name="cargo"
                        data-fd-autosubmit
                    >
                        <option value="">
                            Todos
                        </option>

                        <?php foreach ($positions as $position) : ?>
                            <option
                                value="<?php
                                    echo esc_attr(
                                        $position
                                    );
                                ?>"
                                <?php
                                selected(
                                    $currentPosition,
                                    $position
                                );
                                ?>
                            >
                                <?php
                                echo esc_html(
                                    $position
                                );
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="fd-catalog-filter-field">
                <label for="fd-catalog-orderby">
                    Ordenar por
                </label>

                <select
                    id="fd-catalog-orderby"
                    name="orderby"
                    data-fd-autosubmit
                >
                    <?php
                    foreach (
                        $options
                        as $value => $label
                    ) :
                        ?>
                        <option
                            value="<?php
                                echo esc_attr(
                                    $value
                                );
                            ?>"
                            <?php
                            selected(
                                $orderby,
                                $value
                            );
                            ?>
                        >
                            <?php
                            echo esc_html(
                                $label
                            );
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="fd-catalog-price">
            <div class="fd-catalog-filter-field">
                <label for="fd-catalog-min-price">
                    Preço mínimo
                </label>

                <input
                    id="fd-catalog-min-price"
                    type="number"
                    name="min_price"
                    min="0"
                    step="0.01"
                    inputmode="decimal"
                    placeholder="R$ mín."
                    value="<?php
                        echo esc_attr(
                            $currentMinPrice
                        );
                    ?>"
                >
            </div>

            <div class="fd-catalog-filter-field">
                <label for="fd-catalog-max-price">
                    Preço máximo
                </label>

                <input
                    id="fd-catalog-max-price"
                    type="number"
                    name="max_price"
                    min="0"
                    step="0.01"
                    inputmode="decimal"
                    placeholder="R$ máx."
                    value="<?php
                        echo esc_attr(
                            $currentMaxPrice
                        );
                    ?>"
                >
            </div>

            <div class="fd-catalog-filter-actions">
                <button
                    class="fd-button fd-button--primary"
                    type="submit"
                >
                    Aplicar filtros
                </button>

                <?php if ($hasActiveFilters) : ?>
                    <a
                        class="fd-button fd-button--secondary"
                        href="<?php
                            echo esc_url(
                                fd_theme_get_shop_url()
                            );
                        ?>"
                    >
                        Limpar
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php
        if (
            function_exists(
                'wc_query_string_form_fields'
            )
        ) {
            wc_query_string_form_fields(
                null,
                [
                    'busca',
                    'orderby',
                    'concurso',
                    'banca',
                    'cargo',
                    'min_price',
                    'max_price',
                    'submit',
                    'paged',
                    'product-page',
                ],
                '',
                true
            );
        }
        ?>
    </form>
</div>
