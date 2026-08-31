<?php
/**
 * Dashboard da área do aluno.
 *
 * WooCommerce permanece responsável pela conta.
 * O Fácil Digital+ Core injeta as métricas
 * através de woocommerce_account_dashboard.
 *
 * @package FacilDigital
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$currentUser =
    wp_get_current_user();

$firstName =
    trim(
        (string) $currentUser
            ->first_name
    );

$displayName =
    $firstName !== ''
        ? $firstName
        : (
            trim(
                (string) $currentUser
                    ->display_name
            )
            ?: __(
                'Aluno',
                'facil-digital'
            )
        );

$quickLinks = [
    [
        'label' =>
            __(
                'Minhas apostilas',
                'facil-digital'
            ),

        'description' =>
            __(
                'Acesse seus materiais liberados.',
                'facil-digital'
            ),

        'endpoint' =>
            'apostilas',
    ],

    [
        'label' =>
            __(
                'Simulados',
                'facil-digital'
            ),

        'description' =>
            __(
                'Continue sua preparação online.',
                'facil-digital'
            ),

        'endpoint' =>
            'simulados',
    ],

    [
        'label' =>
            __(
                'Pedidos',
                'facil-digital'
            ),

        'description' =>
            __(
                'Consulte seu histórico de compras.',
                'facil-digital'
            ),

        'endpoint' =>
            'orders',
    ],
];

?>

<div class="fd-account-dashboard">
    <header class="fd-account-dashboard__hero">
        <span class="fd-eyebrow">
            <?php
            echo esc_html__(
                'Área do aluno',
                'facil-digital'
            );
            ?>
        </span>

        <h1>
            <?php
            echo esc_html(
                sprintf(
                    __(
                        'Olá, %s.',
                        'facil-digital'
                    ),
                    $displayName
                )
            );
            ?>
        </h1>

        <p>
            <?php
            echo esc_html__(
                'Acompanhe suas apostilas, simulados, resultados e pedidos em um só lugar.',
                'facil-digital'
            );
            ?>
        </p>
    </header>

    <nav
        class="fd-account-quick-links"
        aria-label="<?php
            echo esc_attr__(
                'Atalhos da área do aluno',
                'facil-digital'
            );
        ?>"
    >
        <?php foreach ($quickLinks as $link) : ?>
            <a
                class="fd-account-quick-link"
                href="<?php
                    echo esc_url(
                        wc_get_account_endpoint_url(
                            $link['endpoint']
                        )
                    );
                ?>"
            >
                <strong>
                    <?php
                    echo esc_html(
                        $link['label']
                    );
                    ?>
                </strong>

                <span>
                    <?php
                    echo esc_html(
                        $link['description']
                    );
                    ?>
                </span>

                <span
                    class="fd-account-quick-link__arrow"
                    aria-hidden="true"
                >
                    →
                </span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="fd-account-dashboard__metrics">
        <?php
        /*
         * AccountModule:
         * - Apostilas
         * - PDFs prontos
         * - Downloads realizados
         *
         * SimulationAccountModule:
         * - Simulados
         * - Média
         * - Ranking
         */
        do_action(
            'woocommerce_account_dashboard'
        );
        ?>
    </div>
</div>
