<?php
/**
 * Orders.
 *
 * Override visual da Fácil Digital+.
 *
 * @package FacilDigital
 * @version 9.5.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$buttonClass =
    isset($wp_button_class)
        ? (string) $wp_button_class
        : '';

?>

<header class="fd-account-section-header">
    <span class="fd-eyebrow">
        <?php
        echo esc_html__(
            'Histórico de compras',
            'facil-digital'
        );
        ?>
    </span>

    <h2>
        <?php
        echo esc_html__(
            'Pedidos',
            'facil-digital'
        );
        ?>
    </h2>

    <p>
        <?php
        echo esc_html__(
            'Consulte seus pedidos, valores e situação de processamento.',
            'facil-digital'
        );
        ?>
    </p>
</header>

<?php

do_action(
    'woocommerce_before_account_orders',
    $has_orders
);

?>

<?php if ($has_orders) : ?>
    <div class="fd-account-orders-table-wrap">
        <table
            class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table"
            aria-label="<?php
                echo esc_attr__(
                    'Histórico de pedidos',
                    'facil-digital'
                );
            ?>"
        >
            <thead>
                <tr>
                    <?php
                    foreach (
                        wc_get_account_orders_columns()
                        as $columnId => $columnName
                    ) :
                        ?>
                        <th
                            scope="col"
                            class="<?php
                                echo esc_attr(
                                    'woocommerce-orders-table__header '
                                    . 'woocommerce-orders-table__header-'
                                    . $columnId
                                );
                            ?>"
                        >
                            <span class="nobr">
                                <?php
                                echo esc_html(
                                    $columnName
                                );
                                ?>
                            </span>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>

            <tbody>
                <?php
                foreach (
                    $customer_orders->orders
                    as $customerOrder
                ) :
                    $order =
                        wc_get_order(
                            $customerOrder
                        );

                    if (
                        !$order instanceof WC_Order
                    ) {
                        continue;
                    }

                    $itemCount =
                        $order->get_item_count()
                        - $order->get_item_count_refunded();

                    ?>
                    <tr
                        class="<?php
                            echo esc_attr(
                                'woocommerce-orders-table__row '
                                . 'woocommerce-orders-table__row--status-'
                                . $order->get_status()
                                . ' order'
                            );
                        ?>"
                    >
                        <?php
                        foreach (
                            wc_get_account_orders_columns()
                            as $columnId => $columnName
                        ) :
                            $isOrderNumber =
                                $columnId
                                === 'order-number';

                            $tag =
                                $isOrderNumber
                                    ? 'th'
                                    : 'td';

                            ?>
                            <<?php echo $tag; ?>
                                class="<?php
                                    echo esc_attr(
                                        'woocommerce-orders-table__cell '
                                        . 'woocommerce-orders-table__cell-'
                                        . $columnId
                                    );
                                ?>"
                                data-title="<?php
                                    echo esc_attr(
                                        $columnName
                                    );
                                ?>"
                                <?php
                                if ($isOrderNumber) :
                                    ?>
                                    scope="row"
                                <?php endif; ?>
                            >
                                <?php
                                if (
                                    has_action(
                                        'woocommerce_my_account_my_orders_column_'
                                        . $columnId
                                    )
                                ) {
                                    do_action(
                                        'woocommerce_my_account_my_orders_column_'
                                        . $columnId,
                                        $order
                                    );
                                } elseif ($isOrderNumber) {
                                    ?>
                                    <a
                                        href="<?php
                                            echo esc_url(
                                                $order
                                                    ->get_view_order_url()
                                            );
                                        ?>"
                                        aria-label="<?php
                                            echo esc_attr(
                                                sprintf(
                                                    __(
                                                        'Ver pedido número %s',
                                                        'facil-digital'
                                                    ),
                                                    $order
                                                        ->get_order_number()
                                                )
                                            );
                                        ?>"
                                    >
                                        <?php
                                        echo esc_html(
                                            '#'
                                            . $order
                                                ->get_order_number()
                                        );
                                        ?>
                                    </a>
                                    <?php
                                } elseif (
                                    $columnId
                                    === 'order-date'
                                ) {
                                    $created =
                                        $order
                                            ->get_date_created();

                                    if ($created) {
                                        ?>
                                        <time
                                            datetime="<?php
                                                echo esc_attr(
                                                    $created
                                                        ->date('c')
                                                );
                                            ?>"
                                        >
                                            <?php
                                            echo esc_html(
                                                wc_format_datetime(
                                                    $created,
                                                    'd/m/Y'
                                                )
                                            );
                                            ?>
                                        </time>
                                        <?php
                                    }
                                } elseif (
                                    $columnId
                                    === 'order-status'
                                ) {
                                    echo esc_html(
                                        wc_get_order_status_name(
                                            $order
                                                ->get_status()
                                        )
                                    );
                                } elseif (
                                    $columnId
                                    === 'order-total'
                                ) {
                                    echo wp_kses_post(
                                        sprintf(
                                            _n(
                                                '%1$s por %2$s item',
                                                '%1$s por %2$s itens',
                                                $itemCount,
                                                'facil-digital'
                                            ),
                                            $order
                                                ->get_formatted_order_total(),
                                            $itemCount
                                        )
                                    );
                                } elseif (
                                    $columnId
                                    === 'order-actions'
                                ) {
                                    $actions =
                                        wc_get_account_orders_actions(
                                            $order
                                        );

                                    foreach (
                                        $actions
                                        as $key => $action
                                    ) {
                                        $name =
                                            isset(
                                                $action[
                                                    'name'
                                                ]
                                            )
                                                ? (string) $action[
                                                    'name'
                                                ]
                                                : '';

                                        $ariaLabel =
                                            isset(
                                                $action[
                                                    'aria-label'
                                                ]
                                            )
                                                ? (string) $action[
                                                    'aria-label'
                                                ]
                                                : sprintf(
                                                    __(
                                                        '%1$s — pedido %2$s',
                                                        'facil-digital'
                                                    ),
                                                    $name,
                                                    $order
                                                        ->get_order_number()
                                                );

                                        echo '<a href="'
                                            . esc_url(
                                                (string) $action[
                                                    'url'
                                                ]
                                            )
                                            . '" class="woocommerce-button '
                                            . esc_attr(
                                                $buttonClass
                                            )
                                            . ' button '
                                            . esc_attr(
                                                sanitize_html_class(
                                                    (string) $key
                                                )
                                            )
                                            . '" aria-label="'
                                            . esc_attr(
                                                $ariaLabel
                                            )
                                            . '">'
                                            . esc_html(
                                                $name
                                            )
                                            . '</a>';
                                    }
                                }
                                ?>
                            </<?php echo $tag; ?>>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    do_action(
        'woocommerce_before_account_orders_pagination'
    );
    ?>

    <?php
    if (
        1
        < $customer_orders->max_num_pages
    ) :
        ?>
        <nav
            class="woocommerce-pagination woocommerce-pagination--without-numbers woocommerce-Pagination fd-account-orders-pagination"
            aria-label="<?php
                echo esc_attr__(
                    'Paginação dos pedidos',
                    'facil-digital'
                );
            ?>"
        >
            <?php if (1 !== $current_page) : ?>
                <a
                    class="woocommerce-button woocommerce-button--previous woocommerce-Button woocommerce-Button--previous button"
                    href="<?php
                        echo esc_url(
                            wc_get_endpoint_url(
                                'orders',
                                $current_page - 1
                            )
                        );
                    ?>"
                >
                    <?php
                    echo esc_html__(
                        'Anterior',
                        'facil-digital'
                    );
                    ?>
                </a>
            <?php endif; ?>

            <?php
            if (
                (int) $customer_orders
                    ->max_num_pages
                !== $current_page
            ) :
                ?>
                <a
                    class="woocommerce-button woocommerce-button--next woocommerce-Button woocommerce-Button--next button"
                    href="<?php
                        echo esc_url(
                            wc_get_endpoint_url(
                                'orders',
                                $current_page + 1
                            )
                        );
                    ?>"
                >
                    <?php
                    echo esc_html__(
                        'Próxima',
                        'facil-digital'
                    );
                    ?>
                </a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

<?php else : ?>

    <div
        class="woocommerce-info fd-account-empty-state"
        role="status"
    >
        <strong>
            <?php
            echo esc_html__(
                'Você ainda não fez nenhum pedido.',
                'facil-digital'
            );
            ?>
        </strong>

        <span>
            <?php
            echo esc_html__(
                'Quando uma compra for realizada, ela aparecerá aqui.',
                'facil-digital'
            );
            ?>
        </span>

        <a
            class="woocommerce-Button wc-forward button"
            href="<?php
                echo esc_url(
                    apply_filters(
                        'woocommerce_return_to_shop_redirect',
                        wc_get_page_permalink(
                            'shop'
                        )
                    )
                );
            ?>"
        >
            <?php
            echo esc_html__(
                'Ver apostilas',
                'facil-digital'
            );
            ?>
        </a>
    </div>

<?php endif; ?>

<?php

do_action(
    'woocommerce_after_account_orders',
    $has_orders
);
