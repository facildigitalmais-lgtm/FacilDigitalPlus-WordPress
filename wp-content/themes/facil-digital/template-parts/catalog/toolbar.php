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
?>
<div class="fd-catalog-toolbar">
    <p class="fd-catalog-toolbar__count">
        <?php
        if (
            $first > 0
            && $last > 0
        ) {
            printf(
                esc_html__(
                    'Exibindo %1$d-%2$d de %3$d apostilas',
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

    <form
        class="fd-catalog-ordering"
        method="get"
        action="<?php
            echo esc_url(
                wc_get_page_permalink('shop')
            );
        ?>"
    >
        <?php if ($contests !== []) : ?>
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
                    <?php
                    echo esc_html__(
                        'Todos',
                        'facil-digital'
                    );
                    ?>
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
        <?php endif; ?>

        <label for="fd-catalog-orderby">
            <?php
            echo esc_html__(
                'Ordenar por',
                'facil-digital'
            );
            ?>
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

        <?php
        if (
            function_exists(
                'wc_query_string_form_fields'
            )
        ) {
            wc_query_string_form_fields(
                null,
                [
                    'orderby',
                    'concurso',
                    'submit',
                    'paged',
                    'product-page',
                ],
                '',
                true
            );
        }
        ?>

        <noscript>
            <button
                class="fd-button fd-button--secondary"
                type="submit"
            >
                <?php
                echo esc_html__(
                    'Aplicar',
                    'facil-digital'
                );
                ?>
            </button>
        </noscript>
    </form>
</div>
