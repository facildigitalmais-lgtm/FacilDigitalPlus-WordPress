<?php

declare(strict_types=1);

namespace FacilDigital\Core\Admin;

use DateTimeImmutable;
use DateTimeZone;
use FacilDigital\Core\PDFs\PrivateStorage;
use FacilDigital\Core\Products\ProductMetadata;
use WP_Admin_Bar;
use WP_User;

/**
 * ADMIN-A - painel administrativo e vendas.
 *
 * Responsabilidades:
 * - dashboard operacional dentro do wp-admin;
 * - KPIs comerciais usando APIs oficiais do WooCommerce;
 * - listagem de vendas recentes e produtos mais vendidos;
 * - atalhos para WooCommerce e operacao cotidiana;
 * - redirecionamento de administradores para o painel correto.
 *
 * Nao altera:
 * - status de pedidos;
 * - Mercado Pago;
 * - entitlements;
 * - PDFs/downloads;
 * - simulados/questoes;
 * - dados comerciais.
 */
final class AdminDashboardModule
{
    private const PAGE = 'facil-digital-admin';

    private const SALES_PAGE = 'facil-digital-vendas';

    private const PARENT_PAGE = 'facil-digital';

    private const CAPABILITY = 'manage_options';

    /**
     * Limite defensivo de pedidos usados em agregacoes de dashboard.
     * Em volumes maiores, o WooCommerce Analytics continua sendo
     * a fonte para relatorios extensos.
     */
    private const MAX_REPORT_ORDERS = 2500;

    public function register(): void
    {
        add_action(
            'admin_menu',
            [$this, 'registerMenu'],
            95
        );

        add_action(
            'admin_enqueue_scripts',
            [$this, 'enqueueAssets']
        );

        add_filter(
            'login_redirect',
            [$this, 'loginRedirect'],
            20,
            3
        );

        add_filter(
            'woocommerce_login_redirect',
            [$this, 'woocommerceLoginRedirect'],
            20,
            2
        );

        add_action(
            'admin_bar_menu',
            [$this, 'adminBar'],
            90
        );

        add_action(
            'template_redirect',
            [$this, 'redirectAdminFromStudentDashboard'],
            20
        );
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            self::PARENT_PAGE,
            __(
                'Painel Administrativo',
                'facil-digital-core'
            ),
            __(
                'Visão geral',
                'facil-digital-core'
            ),
            self::CAPABILITY,
            self::PAGE,
            [$this, 'renderDashboard']
        );

        add_submenu_page(
            self::PARENT_PAGE,
            __(
                'Vendas',
                'facil-digital-core'
            ),
            __(
                'Vendas',
                'facil-digital-core'
            ),
            self::CAPABILITY,
            self::SALES_PAGE,
            [$this, 'renderSales']
        );

        $this->promoteAdminPagesInSubmenu();
    }

    /**
     * Mantem todos os submenus ja registrados pelo Core,
     * mas coloca Visao geral e Vendas no topo.
     */
    private function promoteAdminPagesInSubmenu(): void
    {
        global $submenu;

        if (
            !isset($submenu[self::PARENT_PAGE])
            || !is_array(
                $submenu[self::PARENT_PAGE]
            )
        ) {
            return;
        }

        $priority = [];
        $remaining = [];

        foreach (
            $submenu[self::PARENT_PAGE]
            as $item
        ) {
            $slug =
                isset($item[2])
                ? (string) $item[2]
                : '';

            if ($slug === self::PAGE) {
                $priority[0] = $item;
                continue;
            }

            if ($slug === self::SALES_PAGE) {
                $priority[1] = $item;
                continue;
            }

            $remaining[] = $item;
        }

        ksort($priority);

        $submenu[self::PARENT_PAGE] =
            array_values(
                array_merge(
                    $priority,
                    $remaining
                )
            );
    }

    public function enqueueAssets(
        string $hookSuffix
    ): void {
        unset($hookSuffix);

        $page =
            isset($_GET['page'])
            ? sanitize_key(
                wp_unslash(
                    (string) $_GET['page']
                )
            )
            : '';

        if (
            !in_array(
                $page,
                [
                    self::PAGE,
                    self::SALES_PAGE,
                ],
                true
            )
        ) {
            return;
        }

        wp_enqueue_style(
            'facil-digital-admin-a',
            plugins_url(
                'assets/admin/admin-a.css',
                FACIL_DIGITAL_CORE_FILE
            ),
            [],
            defined('FACIL_DIGITAL_CORE_VERSION')
                ? FACIL_DIGITAL_CORE_VERSION
                : null
        );
    }

    /**
     * Login nativo WordPress.
     *
     * @param mixed $user
     */
    public function loginRedirect(
        string $redirectTo,
        string $requestedRedirectTo,
        $user
    ): string {
        unset($requestedRedirectTo);

        if (
            $user instanceof WP_User
            && user_can(
                $user,
                self::CAPABILITY
            )
        ) {
            return $this->dashboardUrl();
        }

        return $redirectTo;
    }

    /**
     * Login feito pela pagina Minha Conta do WooCommerce.
     *
     * @param mixed $user
     */
    public function woocommerceLoginRedirect(
        string $redirect,
        $user
    ): string {
        if (
            $user instanceof WP_User
            && user_can(
                $user,
                self::CAPABILITY
            )
        ) {
            return $this->dashboardUrl();
        }

        return $redirect;
    }

    /**
     * Quando um administrador clicar em Minha Conta,
     * a raiz da area do aluno o leva ao painel admin.
     *
     * Endpoints especificos continuam acessiveis para testes.
     * ?fd_student_view=1 permite abrir a raiz como aluno.
     */
    public function redirectAdminFromStudentDashboard(): void
    {
        if (
            !is_user_logged_in()
            || !current_user_can(
                self::CAPABILITY
            )
            || !function_exists(
                'is_account_page'
            )
            || !is_account_page()
        ) {
            return;
        }

        $studentView =
            isset($_GET['fd_student_view'])
            && sanitize_text_field(
                wp_unslash(
                    (string) $_GET[
                        'fd_student_view'
                    ]
                )
            ) === '1';

        if ($studentView) {
            return;
        }

        $endpoint = '';

        if (
            function_exists('WC')
            && WC()->query
        ) {
            $endpoint =
                (string)
                WC()->query
                    ->get_current_endpoint();
        }

        if ($endpoint !== '') {
            return;
        }

        wp_safe_redirect(
            $this->dashboardUrl()
        );

        exit;
    }

    public function adminBar(
        WP_Admin_Bar $adminBar
    ): void {
        if (
            !current_user_can(
                self::CAPABILITY
            )
        ) {
            return;
        }

        $adminBar->add_node(
            [
                'id' =>
                    'facil-digital-admin',
                'title' =>
                    __(
                        'Painel Fácil Digital+',
                        'facil-digital-core'
                    ),
                'href' =>
                    $this->dashboardUrl(),
            ]
        );
    }

    public function renderDashboard(): void
    {
        $this->guard();

        $period =
            $this->requestedPeriod();

        $report =
            $this->reportData(
                $period
            );

        $content =
            $this->contentMetrics();

        $system =
            $this->systemStatus();

        $currentUser =
            wp_get_current_user();

        ?>
        <div class="wrap fd-admin-a">
            <?php
            $this->renderHeader(
                __(
                    'Painel Administrativo',
                    'facil-digital-core'
                ),
                sprintf(
                    __(
                        'Olá, %s. Acompanhe vendas, conteúdo e operação da Fácil Digital+.',
                        'facil-digital-core'
                    ),
                    $currentUser->display_name
                        !== ''
                        ? $currentUser->display_name
                        : $currentUser->user_login
                )
            );
            ?>

            <?php
            $this->renderPeriodNav(
                $period,
                self::PAGE
            );
            ?>

            <section
                class="fd-admin-a__metrics"
                aria-label="<?php
                echo esc_attr__(
                    'Indicadores comerciais',
                    'facil-digital-core'
                );
                ?>"
            >
                <?php
                $this->metricCard(
                    __(
                        'Faturamento pago',
                        'facil-digital-core'
                    ),
                    $this->money(
                        (float) $report['revenue']
                    ),
                    $report['label'],
                    'dashicons-chart-line',
                    'primary'
                );

                $this->metricCard(
                    __(
                        'Pedidos pagos',
                        'facil-digital-core'
                    ),
                    (string) $report['orders_count'],
                    $report['label'],
                    'dashicons-cart',
                    'success'
                );

                $this->metricCard(
                    __(
                        'Clientes cadastrados',
                        'facil-digital-core'
                    ),
                    (string) $content['customers'],
                    __(
                        'Usuários com perfil de cliente',
                        'facil-digital-core'
                    ),
                    'dashicons-groups',
                    'violet'
                );

                $this->metricCard(
                    __(
                        'Ticket médio',
                        'facil-digital-core'
                    ),
                    $this->money(
                        (float) $report['average_ticket']
                    ),
                    $report['label'],
                    'dashicons-money-alt',
                    'amber'
                );
                ?>
            </section>

            <?php
            if (
                !empty(
                    $report['truncated']
                )
            ) {
                ?>
                <div class="notice notice-warning inline">
                    <p>
                        <?php
                        echo esc_html__(
                            'O período possui muitos pedidos. Os cards rápidos foram limitados para proteger o desempenho; use o WooCommerce Analytics para o relatório completo.',
                            'facil-digital-core'
                        );
                        ?>
                    </p>
                </div>
                <?php
            }
            ?>

            <section
                class="fd-admin-a__content-metrics"
                aria-label="<?php
                echo esc_attr__(
                    'Conteúdo e operação',
                    'facil-digital-core'
                );
                ?>"
            >
                <?php
                $this->compactMetric(
                    __(
                        'Apostilas',
                        'facil-digital-core'
                    ),
                    (string) $content['apostilas'],
                    __(
                        'Produtos marcados como apostila',
                        'facil-digital-core'
                    )
                );

                $this->compactMetric(
                    __(
                        'Simulados',
                        'facil-digital-core'
                    ),
                    (string) $content['simulations'],
                    __(
                        'Cadastrados no Core',
                        'facil-digital-core'
                    )
                );

                $this->compactMetric(
                    __(
                        'PDFs prontos',
                        'facil-digital-core'
                    ),
                    (string) $content['pdf_ready'],
                    __(
                        'Personalizados disponíveis',
                        'facil-digital-core'
                    )
                );

                $this->compactMetric(
                    __(
                        'Downloads',
                        'facil-digital-core'
                    ),
                    (string) $content['downloads'],
                    __(
                        'Registros protegidos',
                        'facil-digital-core'
                    )
                );
                ?>
            </section>

            <section class="fd-admin-a__quick">
                <div class="fd-admin-a__section-heading">
                    <div>
                        <span class="fd-admin-a__eyebrow">
                            <?php
                            echo esc_html__(
                                'Atalhos',
                                'facil-digital-core'
                            );
                            ?>
                        </span>

                        <h2>
                            <?php
                            echo esc_html__(
                                'Operação rápida',
                                'facil-digital-core'
                            );
                            ?>
                        </h2>
                    </div>
                </div>

                <div class="fd-admin-a__quick-grid">
                    <?php
                    $this->quickLink(
                        __(
                            'Nova apostila',
                            'facil-digital-core'
                        ),
                        __(
                            'Crie o produto comercial no WooCommerce.',
                            'facil-digital-core'
                        ),
                        admin_url(
                            'post-new.php?post_type=product'
                        ),
                        'dashicons-plus-alt2'
                    );

                    $this->quickLink(
                        __(
                            'Gerenciar apostilas',
                            'facil-digital-core'
                        ),
                        __(
                            'Consulte e edite os produtos cadastrados.',
                            'facil-digital-core'
                        ),
                        admin_url(
                            'edit.php?post_type=product'
                        ),
                        'dashicons-book-alt'
                    );

                    $this->quickLink(
                        __(
                            'Pedidos',
                            'facil-digital-core'
                        ),
                        __(
                            'Abra a gestão oficial de pedidos do WooCommerce.',
                            'facil-digital-core'
                        ),
                        admin_url(
                            'admin.php?page=wc-orders'
                        ),
                        'dashicons-list-view'
                    );

                    $this->quickLink(
                        __(
                            'Relatórios WooCommerce',
                            'facil-digital-core'
                        ),
                        __(
                            'Acesse receita, pedidos, clientes e produtos.',
                            'facil-digital-core'
                        ),
                        admin_url(
                            'admin.php?page=wc-admin&path=/analytics/revenue'
                        ),
                        'dashicons-chart-area'
                    );

                    $this->quickLink(
                        __(
                            'Ver site',
                            'facil-digital-core'
                        ),
                        __(
                            'Abra a experiência pública em uma nova navegação.',
                            'facil-digital-core'
                        ),
                        home_url('/'),
                        'dashicons-admin-home',
                        true
                    );

                    $this->quickLink(
                        __(
                            'Ver Área do Aluno',
                            'facil-digital-core'
                        ),
                        __(
                            'Visualize a conta como aluno sem perder o acesso administrativo.',
                            'facil-digital-core'
                        ),
                        $this->studentAreaUrl(),
                        'dashicons-welcome-learn-more',
                        true
                    );
                    ?>
                </div>
            </section>

            <div class="fd-admin-a__two-columns">
                <section class="fd-admin-a__panel">
                    <div class="fd-admin-a__section-heading fd-admin-a__section-heading--row">
                        <div>
                            <span class="fd-admin-a__eyebrow">
                                <?php
                                echo esc_html__(
                                    'Comercial',
                                    'facil-digital-core'
                                );
                                ?>
                            </span>

                            <h2>
                                <?php
                                echo esc_html__(
                                    'Vendas recentes',
                                    'facil-digital-core'
                                );
                                ?>
                            </h2>
                        </div>

                        <a
                            class="button button-secondary"
                            href="<?php
                            echo esc_url(
                                admin_url(
                                    'admin.php?page='
                                    . self::SALES_PAGE
                                )
                            );
                            ?>"
                        >
                            <?php
                            echo esc_html__(
                                'Ver vendas',
                                'facil-digital-core'
                            );
                            ?>
                        </a>
                    </div>

                    <?php
                    $this->renderOrdersTable(
                        $report['recent_orders'],
                        6
                    );
                    ?>
                </section>

                <section class="fd-admin-a__panel">
                    <div class="fd-admin-a__section-heading">
                        <div>
                            <span class="fd-admin-a__eyebrow">
                                <?php
                                echo esc_html__(
                                    'Sistema',
                                    'facil-digital-core'
                                );
                                ?>
                            </span>

                            <h2>
                                <?php
                                echo esc_html__(
                                    'Estado operacional',
                                    'facil-digital-core'
                                );
                                ?>
                            </h2>
                        </div>
                    </div>

                    <div class="fd-admin-a__status-list">
                        <?php
                        foreach ($system as $status) {
                            $this->statusRow(
                                (string) $status['label'],
                                (string) $status['value'],
                                (bool) $status['ok']
                            );
                        }
                        ?>
                    </div>
                </section>
            </div>
        </div>
        <?php
    }

    public function renderSales(): void
    {
        $this->guard();

        $period =
            $this->requestedPeriod();

        $report =
            $this->reportData(
                $period
            );

        ?>
        <div class="wrap fd-admin-a">
            <?php
            $this->renderHeader(
                __(
                    'Vendas',
                    'facil-digital-core'
                ),
                __(
                    'Resumo operacional das vendas realizadas. Para análises financeiras avançadas, use também o WooCommerce Analytics.',
                    'facil-digital-core'
                )
            );
            ?>

            <?php
            $this->renderPeriodNav(
                $period,
                self::SALES_PAGE
            );
            ?>

            <section
                class="fd-admin-a__metrics fd-admin-a__metrics--sales"
                aria-label="<?php
                echo esc_attr__(
                    'Indicadores de vendas',
                    'facil-digital-core'
                );
                ?>"
            >
                <?php
                $this->metricCard(
                    __(
                        'Faturamento pago',
                        'facil-digital-core'
                    ),
                    $this->money(
                        (float) $report['revenue']
                    ),
                    $report['label'],
                    'dashicons-chart-line',
                    'primary'
                );

                $this->metricCard(
                    __(
                        'Pedidos pagos',
                        'facil-digital-core'
                    ),
                    (string) $report['orders_count'],
                    $report['label'],
                    'dashicons-cart',
                    'success'
                );

                $this->metricCard(
                    __(
                        'Itens vendidos',
                        'facil-digital-core'
                    ),
                    (string) $report['items_sold'],
                    $report['label'],
                    'dashicons-products',
                    'violet'
                );

                $this->metricCard(
                    __(
                        'Ticket médio',
                        'facil-digital-core'
                    ),
                    $this->money(
                        (float) $report['average_ticket']
                    ),
                    $report['label'],
                    'dashicons-money-alt',
                    'amber'
                );
                ?>
            </section>

            <div class="fd-admin-a__two-columns fd-admin-a__two-columns--sales">
                <section class="fd-admin-a__panel">
                    <div class="fd-admin-a__section-heading fd-admin-a__section-heading--row">
                        <div>
                            <span class="fd-admin-a__eyebrow">
                                <?php
                                echo esc_html__(
                                    'Pedidos',
                                    'facil-digital-core'
                                );
                                ?>
                            </span>

                            <h2>
                                <?php
                                echo esc_html__(
                                    'Vendas recentes',
                                    'facil-digital-core'
                                );
                                ?>
                            </h2>
                        </div>

                        <a
                            class="button button-secondary"
                            href="<?php
                            echo esc_url(
                                admin_url(
                                    'admin.php?page=wc-orders'
                                )
                            );
                            ?>"
                        >
                            <?php
                            echo esc_html__(
                                'Todos os pedidos',
                                'facil-digital-core'
                            );
                            ?>
                        </a>
                    </div>

                    <?php
                    $this->renderOrdersTable(
                        $report['recent_orders'],
                        12
                    );
                    ?>
                </section>

                <section class="fd-admin-a__panel">
                    <div class="fd-admin-a__section-heading">
                        <div>
                            <span class="fd-admin-a__eyebrow">
                                <?php
                                echo esc_html__(
                                    'Produtos',
                                    'facil-digital-core'
                                );
                                ?>
                            </span>

                            <h2>
                                <?php
                                echo esc_html__(
                                    'Mais vendidos',
                                    'facil-digital-core'
                                );
                                ?>
                            </h2>
                        </div>
                    </div>

                    <?php
                    $this->renderTopProducts(
                        $report['top_products']
                    );
                    ?>

                    <div class="fd-admin-a__report-links">
                        <a
                            href="<?php
                            echo esc_url(
                                admin_url(
                                    'admin.php?page=wc-admin&path=/analytics/revenue'
                                )
                            );
                            ?>"
                        >
                            <?php
                            echo esc_html__(
                                'Receita no WooCommerce Analytics',
                                'facil-digital-core'
                            );
                            ?>
                        </a>

                        <a
                            href="<?php
                            echo esc_url(
                                admin_url(
                                    'admin.php?page=wc-admin&path=/analytics/orders'
                                )
                            );
                            ?>"
                        >
                            <?php
                            echo esc_html__(
                                'Relatório de pedidos',
                                'facil-digital-core'
                            );
                            ?>
                        </a>

                        <a
                            href="<?php
                            echo esc_url(
                                admin_url(
                                    'admin.php?page=wc-admin&path=/analytics/products'
                                )
                            );
                            ?>"
                        >
                            <?php
                            echo esc_html__(
                                'Relatório de produtos',
                                'facil-digital-core'
                            );
                            ?>
                        </a>

                        <a
                            href="<?php
                            echo esc_url(
                                admin_url(
                                    'admin.php?page=wc-admin&path=/analytics/customers'
                                )
                            );
                            ?>"
                        >
                            <?php
                            echo esc_html__(
                                'Relatório de clientes',
                                'facil-digital-core'
                            );
                            ?>
                        </a>
                    </div>
                </section>
            </div>

            <?php
            if (
                !empty(
                    $report['truncated']
                )
            ) {
                ?>
                <div class="notice notice-warning inline">
                    <p>
                        <?php
                        echo esc_html__(
                            'O painel rápido atingiu o limite defensivo de pedidos deste período. O WooCommerce Analytics permanece disponível para a visão completa.',
                            'facil-digital-core'
                        );
                        ?>
                    </p>
                </div>
                <?php
            }
            ?>
        </div>
        <?php
    }

    private function guard(): void
    {
        if (
            current_user_can(
                self::CAPABILITY
            )
        ) {
            return;
        }

        wp_die(
            esc_html__(
                'Você não tem permissão para acessar este painel.',
                'facil-digital-core'
            )
        );
    }

    /**
     * @return array{
     *   label:string,
     *   revenue:float,
     *   orders_count:int,
     *   average_ticket:float,
     *   items_sold:int,
     *   recent_orders:array,
     *   top_products:array,
     *   truncated:bool
     * }
     */
    private function reportData(
        string $period
    ): array {
        $cacheKey =
            'fd_admin_a_'
            . md5(
                $period
                . '|'
                . (
                    defined('WC_VERSION')
                    ? WC_VERSION
                    : 'no-wc'
                )
            );

        $cached =
            get_transient(
                $cacheKey
            );

        if (
            is_array($cached)
            && isset(
                $cached['revenue']
            )
        ) {
            return $cached;
        }

        $range =
            $this->periodRange(
                $period
            );

        $default = [
            'label' =>
                $range['label'],
            'revenue' =>
                0.0,
            'orders_count' =>
                0,
            'average_ticket' =>
                0.0,
            'items_sold' =>
                0,
            'recent_orders' =>
                [],
            'top_products' =>
                [],
            'truncated' =>
                false,
        ];

        if (
            !function_exists(
                'wc_get_orders'
            )
            || !function_exists(
                'wc_get_is_paid_statuses'
            )
        ) {
            return $default;
        }

        $dateQuery =
            $range['start']
                ->format(
                    'Y-m-d H:i:s'
                )
            . '...'
            . $range['end']
                ->format(
                    'Y-m-d H:i:s'
                );

        $statuses =
            wc_get_is_paid_statuses();

        $page = 1;
        $processed = 0;
        $revenue = 0.0;
        $itemsSold = 0;
        $products = [];
        $recentOrders = [];
        $maxPages = 1;
        $truncated = false;

        do {
            $result =
                wc_get_orders(
                    [
                        'status' =>
                            $statuses,
                        'date_paid' =>
                            $dateQuery,
                        'orderby' =>
                            'date',
                        'order' =>
                            'DESC',
                        'limit' =>
                            100,
                        'page' =>
                            $page,
                        'paginate' =>
                            true,
                    ]
                );

            if (!is_object($result)) {
                break;
            }

            $orders =
                isset($result->orders)
                && is_array(
                    $result->orders
                )
                ? $result->orders
                : [];

            $maxPages =
                isset(
                    $result->max_num_pages
                )
                ? (int)
                    $result->max_num_pages
                : 1;

            foreach ($orders as $order) {
                if (
                    !is_object($order)
                    || !method_exists(
                        $order,
                        'get_id'
                    )
                ) {
                    continue;
                }

                if (
                    count($recentOrders)
                    < 12
                ) {
                    $recentOrders[] =
                        $order;
                }

                $netOrder =
                    max(
                        0.0,
                        (float)
                        $order->get_total()
                        -
                        (
                            method_exists(
                                $order,
                                'get_total_refunded'
                            )
                            ? (float)
                                $order
                                    ->get_total_refunded()
                            : 0.0
                        )
                    );

                $revenue +=
                    $netOrder;

                foreach (
                    $order->get_items(
                        'line_item'
                    )
                    as $item
                ) {
                    $quantity =
                        max(
                            0,
                            (int)
                            $item->get_quantity()
                        );

                    $itemsSold +=
                        $quantity;

                    $productId =
                        (int)
                        $item->get_product_id();

                    $key =
                        $productId > 0
                        ? (string)
                            $productId
                        : 'name:'
                            . md5(
                                (string)
                                $item->get_name()
                            );

                    if (
                        !isset(
                            $products[$key]
                        )
                    ) {
                        $products[$key] = [
                            'product_id' =>
                                $productId,
                            'name' =>
                                (string)
                                $item->get_name(),
                            'quantity' =>
                                0,
                            'total' =>
                                0.0,
                        ];
                    }

                    $products[$key]['quantity'] +=
                        $quantity;

                    $products[$key]['total'] +=
                        (float)
                        $item->get_total();
                }

                $processed++;

                if (
                    $processed
                    >= self::MAX_REPORT_ORDERS
                ) {
                    $truncated = true;
                    break 2;
                }
            }

            $page++;
        } while ($page <= $maxPages);

        uasort(
            $products,
            static function (
                array $a,
                array $b
            ): int {
                $quantityCompare =
                    ((int) $b['quantity'])
                    <=>
                    ((int) $a['quantity']);

                if ($quantityCompare !== 0) {
                    return $quantityCompare;
                }

                return
                    ((float) $b['total'])
                    <=>
                    ((float) $a['total']);
            }
        );

        $topProducts =
            array_slice(
                array_values(
                    $products
                ),
                0,
                6
            );

        $data = [
            'label' =>
                $range['label'],
            'revenue' =>
                $revenue,
            'orders_count' =>
                $processed,
            'average_ticket' =>
                $processed > 0
                ? $revenue
                    / $processed
                : 0.0,
            'items_sold' =>
                $itemsSold,
            'recent_orders' =>
                $recentOrders,
            'top_products' =>
                $topProducts,
            'truncated' =>
                $truncated,
        ];

        set_transient(
            $cacheKey,
            $data,
            MINUTE_IN_SECONDS
        );

        return $data;
    }

    /**
     * @return array{
     *   label:string,
     *   start:DateTimeImmutable,
     *   end:DateTimeImmutable
     * }
     */
    private function periodRange(
        string $period
    ): array {
        $timezone =
            function_exists(
                'wp_timezone'
            )
            ? wp_timezone()
            : new DateTimeZone('UTC');

        $now =
            new DateTimeImmutable(
                'now',
                $timezone
            );

        if ($period === 'today') {
            return [
                'label' =>
                    __(
                        'Hoje',
                        'facil-digital-core'
                    ),
                'start' =>
                    $now->setTime(
                        0,
                        0,
                        0
                    ),
                'end' =>
                    $now,
            ];
        }

        if ($period === '7days') {
            return [
                'label' =>
                    __(
                        'Últimos 7 dias',
                        'facil-digital-core'
                    ),
                'start' =>
                    $now->modify(
                        '-6 days'
                    )->setTime(
                        0,
                        0,
                        0
                    ),
                'end' =>
                    $now,
            ];
        }

        if ($period === 'month') {
            return [
                'label' =>
                    __(
                        'Este mês',
                        'facil-digital-core'
                    ),
                'start' =>
                    $now
                        ->modify(
                            'first day of this month'
                        )
                        ->setTime(
                            0,
                            0,
                            0
                        ),
                'end' =>
                    $now,
            ];
        }

        return [
            'label' =>
                __(
                    'Últimos 30 dias',
                    'facil-digital-core'
                ),
            'start' =>
                $now->modify(
                    '-29 days'
                )->setTime(
                    0,
                    0,
                    0
                ),
            'end' =>
                $now,
        ];
    }

    private function requestedPeriod(): string
    {
        $period =
            isset($_GET['fd_period'])
            ? sanitize_key(
                wp_unslash(
                    (string)
                    $_GET['fd_period']
                )
            )
            : '30days';

        return
            in_array(
                $period,
                [
                    'today',
                    '7days',
                    '30days',
                    'month',
                ],
                true
            )
            ? $period
            : '30days';
    }

    /**
     * @return array{
     *   customers:int,
     *   apostilas:int,
     *   simulations:int,
     *   pdf_ready:int,
     *   downloads:int
     * }
     */
    private function contentMetrics(): array
    {
        global $wpdb;

        $users =
            count_users();

        $customers =
            isset(
                $users['avail_roles']['customer']
            )
            ? (int)
                $users['avail_roles']['customer']
            : 0;

        $apostilas =
            $this->countApostilas();

        $simulations =
            $this->safeCoreCount(
                $wpdb->prefix
                . 'fd_simulations'
            );

        $pdfReady =
            $this->safeCoreCount(
                $wpdb->prefix
                . 'fd_pdf_files',
                "status = 'ready'"
            );

        $downloads =
            $this->safeCoreCount(
                $wpdb->prefix
                . 'fd_downloads'
            );

        return [
            'customers' =>
                $customers,
            'apostilas' =>
                $apostilas,
            'simulations' =>
                $simulations,
            'pdf_ready' =>
                $pdfReady,
            'downloads' =>
                $downloads,
        ];
    }

    private function countApostilas(): int
    {
        if (
            !function_exists(
                'wc_get_products'
            )
            || !class_exists(
                ProductMetadata::class
            )
        ) {
            return 0;
        }

        $ids =
            wc_get_products(
                [
                    'status' =>
                        [
                            'publish',
                            'draft',
                            'pending',
                            'private',
                        ],
                    'limit' =>
                        -1,
                    'return' =>
                        'ids',
                ]
            );

        $count = 0;

        foreach ($ids as $productId) {
            if (
                ProductMetadata::isApostila(
                    (int) $productId
                )
            ) {
                $count++;
            }
        }

        return $count;
    }

    private function safeCoreCount(
        string $table,
        string $where = ''
    ): int {
        global $wpdb;

        $exists =
            $wpdb->get_var(
                $wpdb->prepare(
                    'SHOW TABLES LIKE %s',
                    $table
                )
            );

        if (
            !is_string($exists)
            || $exists !== $table
        ) {
            return 0;
        }

        $sql =
            "SELECT COUNT(*) FROM `{$table}`";

        if ($where !== '') {
            $sql .=
                ' WHERE '
                . $where;
        }

        return
            (int)
            $wpdb->get_var($sql);
    }

    /**
     * @return array<int,array{
     *   label:string,
     *   value:string,
     *   ok:bool
     * }>
     */
    private function systemStatus(): array
    {
        $storageReady = false;

        if (
            class_exists(
                PrivateStorage::class
            )
        ) {
            try {
                $storage =
                    new PrivateStorage();

                $storageReady =
                    $storage->isReady();
            } catch (\Throwable $throwable) {
                unset($throwable);

                $storageReady = false;
            }
        }

        $mpSync =
            (string)
            get_option(
                '_mp_cron_sync_mode',
                ''
            );

        $mpActive =
            function_exists(
                'is_plugin_active'
            )
            ? is_plugin_active(
                'woocommerce-mercadopago/woocommerce-mercadopago.php'
            )
            : false;

        if (
            !function_exists(
                'is_plugin_active'
            )
        ) {
            require_once
                ABSPATH
                . 'wp-admin/includes/plugin.php';

            $mpActive =
                is_plugin_active(
                    'woocommerce-mercadopago/woocommerce-mercadopago.php'
                );
        }

        return [
            [
                'label' =>
                    __(
                        'Ambiente',
                        'facil-digital-core'
                    ),
                'value' =>
                    wp_get_environment_type(),
                'ok' =>
                    in_array(
                        wp_get_environment_type(),
                        [
                            'staging',
                            'production',
                        ],
                        true
                    ),
            ],
            [
                'label' =>
                    __(
                        'WooCommerce',
                        'facil-digital-core'
                    ),
                'value' =>
                    defined('WC_VERSION')
                    ? 'v' . WC_VERSION
                    : __(
                        'Indisponível',
                        'facil-digital-core'
                    ),
                'ok' =>
                    defined(
                        'WC_VERSION'
                    ),
            ],
            [
                'label' =>
                    __(
                        'Mercado Pago',
                        'facil-digital-core'
                    ),
                'value' =>
                    $mpActive
                    ? __(
                        'Ativo',
                        'facil-digital-core'
                    )
                    : __(
                        'Inativo',
                        'facil-digital-core'
                    ),
                'ok' =>
                    $mpActive,
            ],
            [
                'label' =>
                    __(
                        'Sincronização MP',
                        'facil-digital-core'
                    ),
                'value' =>
                    $mpSync !== ''
                    ? $mpSync
                    : __(
                        'Não configurada',
                        'facil-digital-core'
                    ),
                'ok' =>
                    $mpSync !== '',
            ],
            [
                'label' =>
                    __(
                        'Storage privado',
                        'facil-digital-core'
                    ),
                'value' =>
                    $storageReady
                    ? __(
                        'Pronto',
                        'facil-digital-core'
                    )
                    : __(
                        'Atenção',
                        'facil-digital-core'
                    ),
                'ok' =>
                    $storageReady,
            ],
        ];
    }

    private function renderHeader(
        string $title,
        string $description
    ): void {
        ?>
        <header class="fd-admin-a__hero">
            <div>
                <span class="fd-admin-a__eyebrow">
                    <?php
                    echo esc_html__(
                        'Fácil Digital+',
                        'facil-digital-core'
                    );
                    ?>
                </span>

                <h1>
                    <?php
                    echo esc_html($title);
                    ?>
                </h1>

                <p>
                    <?php
                    echo esc_html(
                        $description
                    );
                    ?>
                </p>
            </div>

            <div class="fd-admin-a__hero-actions">
                <a
                    class="button button-primary"
                    href="<?php
                    echo esc_url(
                        admin_url(
                            'post-new.php?post_type=product'
                        )
                    );
                    ?>"
                >
                    <?php
                    echo esc_html__(
                        'Nova apostila',
                        'facil-digital-core'
                    );
                    ?>
                </a>

                <a
                    class="button"
                    href="<?php
                    echo esc_url(
                        home_url('/')
                    );
                    ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <?php
                    echo esc_html__(
                        'Ver site',
                        'facil-digital-core'
                    );
                    ?>
                </a>
            </div>
        </header>
        <?php
    }

    private function renderPeriodNav(
        string $current,
        string $page
    ): void {
        $periods = [
            'today' =>
                __(
                    'Hoje',
                    'facil-digital-core'
                ),
            '7days' =>
                __(
                    '7 dias',
                    'facil-digital-core'
                ),
            '30days' =>
                __(
                    '30 dias',
                    'facil-digital-core'
                ),
            'month' =>
                __(
                    'Este mês',
                    'facil-digital-core'
                ),
        ];

        ?>
        <nav
            class="fd-admin-a__periods"
            aria-label="<?php
            echo esc_attr__(
                'Período do relatório',
                'facil-digital-core'
            );
            ?>"
        >
            <?php
            foreach (
                $periods
                as $key => $label
            ) {
                $url =
                    add_query_arg(
                        [
                            'page' =>
                                $page,
                            'fd_period' =>
                                $key,
                        ],
                        admin_url(
                            'admin.php'
                        )
                    );

                ?>
                <a
                    class="<?php
                    echo esc_attr(
                        $current === $key
                        ? 'is-active'
                        : ''
                    );
                    ?>"
                    href="<?php
                    echo esc_url($url);
                    ?>"
                >
                    <?php
                    echo esc_html($label);
                    ?>
                </a>
                <?php
            }
            ?>
        </nav>
        <?php
    }

    private function metricCard(
        string $label,
        string $value,
        string $caption,
        string $icon,
        string $tone
    ): void {
        ?>
        <article
            class="fd-admin-a__metric fd-admin-a__metric--<?php
            echo esc_attr($tone);
            ?>"
        >
            <div class="fd-admin-a__metric-icon">
                <span
                    class="dashicons <?php
                    echo esc_attr($icon);
                    ?>"
                    aria-hidden="true"
                ></span>
            </div>

            <div>
                <span class="fd-admin-a__metric-label">
                    <?php
                    echo esc_html($label);
                    ?>
                </span>

                <strong>
                    <?php
                    echo wp_kses_post(
                        $value
                    );
                    ?>
                </strong>

                <small>
                    <?php
                    echo esc_html($caption);
                    ?>
                </small>
            </div>
        </article>
        <?php
    }

    private function compactMetric(
        string $label,
        string $value,
        string $caption
    ): void {
        ?>
        <article class="fd-admin-a__compact-metric">
            <strong>
                <?php
                echo esc_html($value);
                ?>
            </strong>

            <div>
                <span>
                    <?php
                    echo esc_html($label);
                    ?>
                </span>

                <small>
                    <?php
                    echo esc_html($caption);
                    ?>
                </small>
            </div>
        </article>
        <?php
    }

    private function quickLink(
        string $title,
        string $description,
        string $url,
        string $icon,
        bool $external = false
    ): void {
        ?>
        <a
            class="fd-admin-a__quick-link"
            href="<?php
            echo esc_url($url);
            ?>"
            <?php
            if ($external) {
                ?>
                target="_blank"
                rel="noopener noreferrer"
                <?php
            }
            ?>
        >
            <span
                class="dashicons <?php
                echo esc_attr($icon);
                ?>"
                aria-hidden="true"
            ></span>

            <strong>
                <?php
                echo esc_html($title);
                ?>
            </strong>

            <small>
                <?php
                echo esc_html(
                    $description
                );
                ?>
            </small>

            <span
                class="fd-admin-a__quick-arrow"
                aria-hidden="true"
            >
                →
            </span>
        </a>
        <?php
    }

    /**
     * @param array<int,mixed> $orders
     */
    private function renderOrdersTable(
        array $orders,
        int $limit
    ): void {
        $orders =
            array_slice(
                $orders,
                0,
                $limit
            );

        if ($orders === []) {
            ?>
            <div class="fd-admin-a__empty">
                <strong>
                    <?php
                    echo esc_html__(
                        'Nenhuma venda paga neste período.',
                        'facil-digital-core'
                    );
                    ?>
                </strong>

                <span>
                    <?php
                    echo esc_html__(
                        'Os pedidos pagos aparecerão aqui automaticamente.',
                        'facil-digital-core'
                    );
                    ?>
                </span>
            </div>
            <?php

            return;
        }

        ?>
        <div class="fd-admin-a__table-wrap">
            <table class="fd-admin-a__table">
                <thead>
                    <tr>
                        <th>
                            <?php
                            echo esc_html__(
                                'Pedido',
                                'facil-digital-core'
                            );
                            ?>
                        </th>

                        <th>
                            <?php
                            echo esc_html__(
                                'Cliente',
                                'facil-digital-core'
                            );
                            ?>
                        </th>

                        <th>
                            <?php
                            echo esc_html__(
                                'Produto',
                                'facil-digital-core'
                            );
                            ?>
                        </th>

                        <th>
                            <?php
                            echo esc_html__(
                                'Data',
                                'facil-digital-core'
                            );
                            ?>
                        </th>

                        <th>
                            <?php
                            echo esc_html__(
                                'Total',
                                'facil-digital-core'
                            );
                            ?>
                        </th>

                        <th>
                            <?php
                            echo esc_html__(
                                'Status',
                                'facil-digital-core'
                            );
                            ?>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    foreach ($orders as $order) {
                        if (
                            !is_object($order)
                            || !method_exists(
                                $order,
                                'get_id'
                            )
                        ) {
                            continue;
                        }

                        $name =
                            trim(
                                (string)
                                $order
                                    ->get_formatted_billing_full_name()
                            );

                        if ($name === '') {
                            $name =
                                (string)
                                $order
                                    ->get_billing_email();
                        }

                        $items = [];

                        foreach (
                            $order->get_items(
                                'line_item'
                            )
                            as $item
                        ) {
                            $items[] =
                                (string)
                                $item->get_name();

                            if (
                                count($items)
                                >= 2
                            ) {
                                break;
                            }
                        }

                        $itemLabel =
                            implode(
                                ', ',
                                $items
                            );

                        if (
                            count(
                                $order->get_items(
                                    'line_item'
                                )
                            )
                            > 2
                        ) {
                            $itemLabel .=
                                ' +';
                        }

                        $date =
                            $order->get_date_paid()
                            ?: $order->get_date_created();

                        ?>
                        <tr>
                            <td>
                                <a
                                    href="<?php
                                    echo esc_url(
                                        $order
                                            ->get_edit_order_url()
                                    );
                                    ?>"
                                >
                                    #<?php
                                    echo esc_html(
                                        (string)
                                        $order->get_id()
                                    );
                                    ?>
                                </a>
                            </td>

                            <td>
                                <?php
                                echo esc_html($name);
                                ?>
                            </td>

                            <td>
                                <?php
                                echo esc_html(
                                    $itemLabel
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo esc_html(
                                    $date
                                    ? $date
                                        ->date_i18n(
                                            'd/m/Y H:i'
                                        )
                                    : '—'
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo wp_kses_post(
                                    wc_price(
                                        (float)
                                        $order->get_total()
                                    )
                                );
                                ?>
                            </td>

                            <td>
                                <span
                                    class="fd-admin-a__status-badge"
                                >
                                    <?php
                                    echo esc_html(
                                        wc_get_order_status_name(
                                            $order->get_status()
                                        )
                                    );
                                    ?>
                                </span>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * @param array<int,array{
     *   product_id:int,
     *   name:string,
     *   quantity:int,
     *   total:float
     * }> $products
     */
    private function renderTopProducts(
        array $products
    ): void {
        if ($products === []) {
            ?>
            <div class="fd-admin-a__empty">
                <strong>
                    <?php
                    echo esc_html__(
                        'Sem produtos vendidos neste período.',
                        'facil-digital-core'
                    );
                    ?>
                </strong>
            </div>
            <?php

            return;
        }

        ?>
        <ol class="fd-admin-a__top-products">
            <?php
            foreach (
                $products
                as $index => $product
            ) {
                $editUrl =
                    (int)
                    $product['product_id']
                    > 0
                    ? get_edit_post_link(
                        (int)
                        $product['product_id'],
                        ''
                    )
                    : '';

                ?>
                <li>
                    <span class="fd-admin-a__rank">
                        <?php
                        echo esc_html(
                            (string)
                            ($index + 1)
                        );
                        ?>
                    </span>

                    <div>
                        <?php
                        if (
                            is_string($editUrl)
                            && $editUrl !== ''
                        ) {
                            ?>
                            <a
                                href="<?php
                                echo esc_url($editUrl);
                                ?>"
                            >
                                <?php
                                echo esc_html(
                                    (string)
                                    $product['name']
                                );
                                ?>
                            </a>
                            <?php
                        } else {
                            ?>
                            <strong>
                                <?php
                                echo esc_html(
                                    (string)
                                    $product['name']
                                );
                                ?>
                            </strong>
                            <?php
                        }
                        ?>

                        <small>
                            <?php
                            printf(
                                esc_html__(
                                    '%1$d itens · %2$s',
                                    'facil-digital-core'
                                ),
                                (int)
                                $product['quantity'],
                                wp_kses_post(
                                    $this->money(
                                        (float)
                                        $product['total']
                                    )
                                )
                            );
                            ?>
                        </small>
                    </div>
                </li>
                <?php
            }
            ?>
        </ol>
        <?php
    }

    private function statusRow(
        string $label,
        string $value,
        bool $ok
    ): void {
        ?>
        <div class="fd-admin-a__status-row">
            <span
                class="<?php
                echo esc_attr(
                    $ok
                    ? 'is-ok'
                    : 'is-warning'
                );
                ?>"
                aria-hidden="true"
            ></span>

            <strong>
                <?php
                echo esc_html($label);
                ?>
            </strong>

            <span>
                <?php
                echo esc_html($value);
                ?>
            </span>
        </div>
        <?php
    }

    private function money(
        float $amount
    ): string {
        if (
            function_exists(
                'wc_price'
            )
        ) {
            return
                wc_price($amount);
        }

        return
            'R$ '
            . number_format(
                $amount,
                2,
                ',',
                '.'
            );
    }

    private function dashboardUrl(): string
    {
        return
            admin_url(
                'admin.php?page='
                . self::PAGE
            );
    }

    private function studentAreaUrl(): string
    {
        $base =
            function_exists(
                'wc_get_page_permalink'
            )
            ? wc_get_page_permalink(
                'myaccount'
            )
            : home_url(
                '/minha-conta/'
            );

        return
            add_query_arg(
                'fd_student_view',
                '1',
                $base
            );
    }
}