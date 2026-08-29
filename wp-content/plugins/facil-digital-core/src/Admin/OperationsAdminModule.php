<?php

declare(strict_types=1);

namespace FacilDigital\Core\Admin;

use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Core\Capabilities;
use FacilDigital\Core\Core\Database;
use FacilDigital\Core\Ranking\RankingService;
use FacilDigital\Core\Security\SecurityAudit;
use FacilDigital\Core\Support\QaService;

final class OperationsAdminModule implements ModuleInterface
{
    public const RESULTS_SLUG = 'facil-digital-results';
    public const RANKINGS_SLUG = 'facil-digital-rankings';
    public const PDFS_SLUG = 'facil-digital-pdfs';
    public const DOWNLOADS_SLUG = 'facil-digital-downloads';
    public const STUDENTS_SLUG = 'facil-digital-students';
    public const REPORTS_SLUG = 'facil-digital-reports';
    public const SECURITY_SLUG = 'facil-digital-security';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 40);
    }

    public function menu(): void
    {
        $items = [
            ['Resultados', Capabilities::VIEW_RESULTS, self::RESULTS_SLUG, 'renderResults'],
            ['Rankings', Capabilities::VIEW_RANKINGS, self::RANKINGS_SLUG, 'renderRankings'],
            ['PDFs', Capabilities::MANAGE_PDFS, self::PDFS_SLUG, 'renderPdfs'],
            ['Downloads', Capabilities::MANAGE_DOWNLOADS, self::DOWNLOADS_SLUG, 'renderDownloads'],
            ['Alunos', Capabilities::MANAGE_STUDENTS, self::STUDENTS_SLUG, 'renderStudents'],
            ['Relatórios', Capabilities::VIEW_REPORTS, self::REPORTS_SLUG, 'renderReports'],
            ['Segurança', Capabilities::MANAGE_SETTINGS, self::SECURITY_SLUG, 'renderSecurity'],
        ];

        foreach ($items as [$label, $capability, $slug, $callback]) {
            add_submenu_page(
                Menu::PARENT_SLUG,
                __($label, 'facil-digital-core'),
                __($label, 'facil-digital-core'),
                $capability,
                $slug,
                [$this, $callback]
            );
        }
    }

    public function renderResults(): void
    {
        $this->guard(Capabilities::VIEW_RESULTS);
        global $wpdb;
        $attempts = Database::table('attempts');
        $simulations = Database::table('simulations');
        $rows = $wpdb->get_results(
            "SELECT a.id, a.user_id, a.percentage, a.correct_count, a.incorrect_count,
                    a.elapsed_seconds, a.submitted_at, s.title
             FROM {$attempts} a
             INNER JOIN {$simulations} s ON s.id = a.simulation_id
             WHERE a.status = 'completed'
             ORDER BY a.submitted_at DESC, a.id DESC
             LIMIT 100",
            ARRAY_A
        );
        ?>
        <div class="wrap"><h1>Resultados</h1>
            <table class="widefat striped"><thead><tr><th>ID</th><th>Aluno</th><th>Simulado</th><th>Resultado</th><th>Acertos</th><th>Erros</th><th>Tempo</th><th>Finalizado</th></tr></thead><tbody>
            <?php foreach (is_array($rows) ? $rows : [] as $row) : ?>
                <tr><td><?php echo esc_html((string) $row['id']); ?></td><td>#<?php echo esc_html((string) $row['user_id']); ?></td><td><?php echo esc_html((string) $row['title']); ?></td><td><?php echo esc_html(number_format((float) $row['percentage'], 1, ',', '.') . '%'); ?></td><td><?php echo esc_html((string) $row['correct_count']); ?></td><td><?php echo esc_html((string) $row['incorrect_count']); ?></td><td><?php echo esc_html(gmdate('H:i:s', (int) $row['elapsed_seconds'])); ?></td><td><?php echo esc_html((string) $row['submitted_at']); ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
        <?php
    }

    public function renderRankings(): void
    {
        $this->guard(Capabilities::VIEW_RANKINGS);
        $rows = (new RankingService())->general(100);
        ?>
        <div class="wrap"><h1>Rankings</h1><p>Ranking anonimizado: CPF, e-mail e telefone não fazem parte desta saída.</p>
        <table class="widefat striped"><thead><tr><th>#</th><th>Aluno</th><th>Média</th><th>Simulados</th></tr></thead><tbody>
        <?php foreach ($rows as $row) : ?><tr><td><?php echo esc_html((string) $row['rank']); ?></td><td><?php echo esc_html((string) $row['name']); ?></td><td><?php echo esc_html(number_format((float) $row['score'], 1, ',', '.') . '%'); ?></td><td><?php echo esc_html((string) $row['simulation_count']); ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php
    }

    public function renderPdfs(): void
    {
        $this->guard(Capabilities::MANAGE_PDFS);
        global $wpdb;
        $table = Database::table('pdf_files');
        $rows = $wpdb->get_results(
            "SELECT id, entitlement_id, user_id, product_id, order_id, product_version,
                    file_size, tracking_code, status, generation_attempts, generated_at
             FROM {$table} ORDER BY id DESC LIMIT 100",
            ARRAY_A
        );
        ?>
        <div class="wrap"><h1>PDFs</h1><p>Caminhos privados e storage keys nunca são exibidos.</p><table class="widefat striped"><thead><tr><th>ID</th><th>Entitlement</th><th>Aluno</th><th>Produto</th><th>Pedido</th><th>Versão</th><th>Status</th><th>Tracking</th><th>Tamanho</th></tr></thead><tbody>
        <?php foreach (is_array($rows) ? $rows : [] as $row) : ?><tr><td><?php echo esc_html((string) $row['id']); ?></td><td><?php echo esc_html((string) $row['entitlement_id']); ?></td><td>#<?php echo esc_html((string) $row['user_id']); ?></td><td>#<?php echo esc_html((string) $row['product_id']); ?></td><td>#<?php echo esc_html((string) $row['order_id']); ?></td><td><?php echo esc_html((string) $row['product_version']); ?></td><td><?php echo esc_html((string) $row['status']); ?></td><td><?php echo esc_html((string) $row['tracking_code']); ?></td><td><?php echo esc_html(size_format((int) ($row['file_size'] ?? 0))); ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php
    }

    public function renderDownloads(): void
    {
        $this->guard(Capabilities::MANAGE_DOWNLOADS);
        global $wpdb;
        $table = Database::table('downloads');
        $rows = $wpdb->get_results(
            "SELECT id, user_id, entitlement_id, pdf_file_id, product_id, order_id, downloaded_at
             FROM {$table} ORDER BY downloaded_at DESC, id DESC LIMIT 100",
            ARRAY_A
        );
        ?>
        <div class="wrap"><h1>Downloads</h1><p>IP e User-Agent são mantidos apenas como hashes e não são mostrados neste painel.</p><table class="widefat striped"><thead><tr><th>ID</th><th>Aluno</th><th>Entitlement</th><th>PDF</th><th>Produto</th><th>Pedido</th><th>Data</th></tr></thead><tbody>
        <?php foreach (is_array($rows) ? $rows : [] as $row) : ?><tr><td><?php echo esc_html((string) $row['id']); ?></td><td>#<?php echo esc_html((string) $row['user_id']); ?></td><td>#<?php echo esc_html((string) $row['entitlement_id']); ?></td><td>#<?php echo esc_html((string) $row['pdf_file_id']); ?></td><td>#<?php echo esc_html((string) $row['product_id']); ?></td><td>#<?php echo esc_html((string) $row['order_id']); ?></td><td><?php echo esc_html((string) $row['downloaded_at']); ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php
    }

    public function renderStudents(): void
    {
        $this->guard(Capabilities::MANAGE_STUDENTS);
        global $wpdb;
        $entitlements = Database::table('entitlements');
        $attempts = Database::table('attempts');
        $rows = $wpdb->get_results(
            "SELECT u.ID, u.display_name,
                    COUNT(DISTINCT e.id) AS entitlements,
                    COUNT(DISTINCT a.id) AS attempts,
                    COALESCE(AVG(CASE WHEN a.status = 'completed' THEN a.percentage END),0) AS average_percentage
             FROM {$wpdb->users} u
             LEFT JOIN {$entitlements} e ON e.user_id = u.ID AND e.status = 'active'
             LEFT JOIN {$attempts} a ON a.user_id = u.ID
             GROUP BY u.ID, u.display_name
             HAVING entitlements > 0 OR attempts > 0
             ORDER BY u.ID DESC LIMIT 100",
            ARRAY_A
        );
        ?>
        <div class="wrap"><h1>Alunos</h1><p>Este painel operacional não expõe CPF, e-mail ou telefone.</p><table class="widefat striped"><thead><tr><th>ID</th><th>Nome</th><th>Acessos</th><th>Tentativas</th><th>Média</th></tr></thead><tbody>
        <?php foreach (is_array($rows) ? $rows : [] as $row) : ?><tr><td><?php echo esc_html((string) $row['ID']); ?></td><td><?php echo esc_html((string) $row['display_name']); ?></td><td><?php echo esc_html((string) $row['entitlements']); ?></td><td><?php echo esc_html((string) $row['attempts']); ?></td><td><?php echo esc_html(number_format((float) $row['average_percentage'], 1, ',', '.') . '%'); ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php
    }

    public function renderReports(): void
    {
        $this->guard(Capabilities::VIEW_REPORTS);
        $metrics = (new DashboardService())->snapshot();
        $qa = (new QaService())->run();
        ?>
        <div class="wrap"><h1>Relatórios</h1><h2>Resumo</h2><table class="widefat striped" style="max-width:800px"><tbody>
        <?php foreach ($metrics as $key => $value) : ?><tr><th><?php echo esc_html((string) $key); ?></th><td><?php echo esc_html((string) $value); ?></td></tr><?php endforeach; ?>
        </tbody></table><h2>QA</h2><p><strong><?php echo $qa['ready'] ? 'Pronto' : 'Atenção'; ?></strong></p></div>
        <?php
    }

    public function renderSecurity(): void
    {
        $this->guard(Capabilities::MANAGE_SETTINGS);
        $report = (new SecurityAudit())->run();
        ?>
        <div class="wrap"><h1>Segurança</h1><p><strong><?php echo esc_html($report['ready'] ? 'Auditoria aprovada' : 'Auditoria com bloqueios'); ?></strong></p>
        <table class="widefat striped" style="max-width:960px"><thead><tr><th>Check</th><th>Status</th><th>Detalhe</th></tr></thead><tbody>
        <?php foreach ($report['checks'] as $check) : ?><tr><td><?php echo esc_html((string) $check['id']); ?></td><td><?php echo esc_html((string) $check['status']); ?></td><td><?php echo esc_html((string) $check['message']); ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php
    }

    private function guard(string $capability): void
    {
        if (!current_user_can($capability)) {
            wp_die(esc_html__('Acesso negado.', 'facil-digital-core'));
        }
    }
}
