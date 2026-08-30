<?php

declare(strict_types=1);

namespace FacilDigital\Core\Release;

use FacilDigital\Core\Admin\Menu;
use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Core\Capabilities;

final class ReleaseAdminModule implements ModuleInterface
{
    public const SLUG = 'facil-digital-go-live';

    public function __construct(
        private readonly ReleaseReadinessService $service = new ReleaseReadinessService()
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            Menu::PARENT_SLUG,
            __('Go-live', 'facil-digital-core'),
            __('Go-live', 'facil-digital-core'),
            Capabilities::MANAGE_SETTINGS,
            self::SLUG,
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE_SETTINGS)) {
            wp_die(esc_html__('Acesso negado.', 'facil-digital-core'));
        }

        $sandbox = $this->service->report('sandbox');
        $production = $this->service->report('production');

        echo '<div class="wrap"><h1>Fácil Digital+ — Go-live</h1>';
        echo '<p>Este painel não ativa credenciais, não troca domínio e não executa pagamentos. Ele apenas valida prontidão técnica.</p>';
        $this->renderReport('Sandbox / homologação', $sandbox);
        $this->renderReport('Produção', $production);
        echo '</div>';
    }

    /** @param array<string,mixed> $report */
    private function renderReport(string $title, array $report): void
    {
        echo '<h2>' . esc_html($title) . '</h2>';
        echo '<p><strong>Automático:</strong> ' . ($report['ready_automated'] ? 'pronto' : 'bloqueado') . '</p>';
        echo '<table class="widefat striped"><thead><tr><th>Check</th><th>Status</th><th>Detalhe</th></tr></thead><tbody>';
        foreach ($report['checks'] as $check) {
            echo '<tr><td>' . esc_html((string) $check['id']) . '</td><td>' . esc_html((string) $check['status']) . '</td><td>' . esc_html((string) $check['message']) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<h3>Gates manuais obrigatórios</h3><ul>';
        foreach ($report['manual_gates'] as $gate) {
            echo '<li>' . esc_html((string) $gate) . '</li>';
        }
        echo '</ul>';
    }
}
