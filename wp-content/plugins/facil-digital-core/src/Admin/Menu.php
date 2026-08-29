<?php

declare(strict_types=1);

namespace FacilDigital\Core\Admin;

use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Core\Capabilities;
use FacilDigital\Core\Core\Database;

final class Menu implements ModuleInterface
{
    public const PARENT_SLUG = 'facil-digital';
    private const SETTINGS_SLUG = 'facil-digital-settings';

    public function __construct(
        private readonly DashboardService $dashboard = new DashboardService()
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
    }

    public function registerMenu(): void
    {
        add_menu_page(
            __('Fácil Digital+', 'facil-digital-core'),
            __('Fácil Digital+', 'facil-digital-core'),
            Capabilities::ACCESS_ADMIN,
            self::PARENT_SLUG,
            [$this, 'renderDashboard'],
            'dashicons-welcome-learn-more',
            56
        );

        add_submenu_page(
            self::PARENT_SLUG,
            __('Dashboard', 'facil-digital-core'),
            __('Dashboard', 'facil-digital-core'),
            Capabilities::ACCESS_ADMIN,
            self::PARENT_SLUG,
            [$this, 'renderDashboard']
        );

        add_submenu_page(
            self::PARENT_SLUG,
            __('Configurações', 'facil-digital-core'),
            __('Configurações', 'facil-digital-core'),
            Capabilities::MANAGE_SETTINGS,
            self::SETTINGS_SLUG,
            [$this, 'renderSettings']
        );
    }

    public function renderDashboard(): void
    {
        $this->guard(Capabilities::ACCESS_ADMIN);
        $metrics = $this->dashboard->snapshot();
        $woocommerceVersion = defined('WC_VERSION') ? WC_VERSION : 'indisponível';

        $cards = [
            ['Vendas hoje', function_exists('wc_price') ? wp_strip_all_tags(wc_price((float) $metrics['sales_today'])) : 'R$ ' . number_format((float) $metrics['sales_today'], 2, ',', '.')],
            ['Pedidos pagos hoje', (string) $metrics['orders_today']],
            ['Alunos', (string) $metrics['students']],
            ['Apostilas', (string) $metrics['apostilas']],
            ['Questões', (string) $metrics['questions']],
            ['Simulados', (string) $metrics['simulations']],
            ['Tentativas', (string) $metrics['attempts']],
            ['Média', number_format((float) $metrics['average_percentage'], 1, ',', '.') . '%'],
            ['PDFs prontos', (string) $metrics['ready_pdfs']],
            ['Downloads', (string) $metrics['downloads']],
        ];
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Fácil Digital+ Admin', 'facil-digital-core'); ?></h1>
            <p><?php echo esc_html__('Visão operacional da plataforma. Pedidos e pagamentos permanecem sob autoridade do WooCommerce.', 'facil-digital-core'); ?></p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;max-width:1180px;margin:20px 0">
                <?php foreach ($cards as [$label, $value]) : ?>
                    <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px">
                        <div style="font-size:13px;color:#646970"><?php echo esc_html($label); ?></div>
                        <strong style="display:block;font-size:26px;margin-top:8px"><?php echo esc_html($value); ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
            <h2><?php echo esc_html__('Saúde técnica', 'facil-digital-core'); ?></h2>
            <table class="widefat striped" style="max-width:760px"><tbody>
                <tr><th>Core</th><td><?php echo esc_html(FACIL_DIGITAL_CORE_VERSION); ?></td></tr>
                <tr><th>Schema</th><td><?php echo esc_html(Database::installedVersion()); ?></td></tr>
                <tr><th>Permissões</th><td><?php echo esc_html(Capabilities::installedVersion()); ?></td></tr>
                <tr><th>WordPress</th><td><?php echo esc_html(get_bloginfo('version')); ?></td></tr>
                <tr><th>WooCommerce</th><td><?php echo esc_html($woocommerceVersion); ?></td></tr>
                <tr><th>PHP</th><td><?php echo esc_html(PHP_VERSION); ?></td></tr>
                <tr><th>Ambiente</th><td><?php echo esc_html(wp_get_environment_type()); ?></td></tr>
            </tbody></table>
        </div>
        <?php
    }

    public function renderSettings(): void
    {
        $this->guard(Capabilities::MANAGE_SETTINGS);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Configurações — Fácil Digital+', 'facil-digital-core'); ?></h1>
            <p><?php echo esc_html__('As regras críticas ficam no Core. Credenciais financeiras continuam no plugin oficial do Mercado Pago/WooCommerce.', 'facil-digital-core'); ?></p>
        </div>
        <?php
    }

    private function guard(string $capability): void
    {
        if (!current_user_can($capability)) {
            wp_die(esc_html__('Acesso negado.', 'facil-digital-core'));
        }
    }
}
