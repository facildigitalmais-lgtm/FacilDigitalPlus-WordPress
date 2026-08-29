<?php

declare(strict_types=1);

namespace FacilDigital\Core\Import;

use FacilDigital\Core\Admin\Menu;
use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Core\Capabilities;

final class ImportAdminModule implements ModuleInterface
{
    public const SLUG = 'facil-digital-import';
    private const REPORT_PREFIX = 'fd_import_report_';

    public function __construct(
        private readonly QuestionCsvService $service = new QuestionCsvService()
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 45);
        add_action('admin_post_fd_questions_import', [$this, 'process']);
        add_action('admin_post_fd_questions_export', [$this, 'export']);
    }

    public function menu(): void
    {
        add_submenu_page(
            Menu::PARENT_SLUG,
            __('Importação', 'facil-digital-core'),
            __('Importação', 'facil-digital-core'),
            Capabilities::MANAGE_QUESTIONS,
            self::SLUG,
            [$this, 'render']
        );
    }

    public function render(): void
    {
        $this->guard();
        $report = get_transient(self::REPORT_PREFIX . get_current_user_id());
        delete_transient(self::REPORT_PREFIX . get_current_user_id());
        $exportUrl = wp_nonce_url(
            admin_url('admin-post.php?action=fd_questions_export'),
            'fd_questions_export'
        );
        ?>
        <div class="wrap"><h1>Importação de questões</h1>
            <p>Execute primeiro em dry-run. O arquivo não é movido para a biblioteca pública de mídia.</p>
            <?php if (is_array($report)) : ?>
                <div class="notice <?php echo ($report['invalid'] ?? 0) > 0 ? 'notice-warning' : 'notice-success'; ?>"><p>
                    Linhas: <?php echo esc_html((string) ($report['rows'] ?? 0)); ?> — válidas: <?php echo esc_html((string) ($report['valid'] ?? 0)); ?> — inválidas: <?php echo esc_html((string) ($report['invalid'] ?? 0)); ?> — criadas: <?php echo esc_html((string) ($report['created'] ?? 0)); ?>.
                </p></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="fd_questions_import">
                <?php wp_nonce_field('fd_questions_import'); ?>
                <table class="form-table"><tbody>
                    <tr><th><label for="fd_questions_csv">CSV</label></th><td><input id="fd_questions_csv" name="questions_csv" type="file" accept=".csv,text/csv,text/plain" required><p class="description">Máximo: 10 MiB e 10.000 linhas.</p></td></tr>
                    <tr><th>Modo</th><td><label><input type="checkbox" name="dry_run" value="1" checked> Dry-run: validar sem persistir</label></td></tr>
                </tbody></table>
                <?php submit_button(__('Executar importação', 'facil-digital-core')); ?>
            </form>
            <p><a class="button" href="<?php echo esc_url($exportUrl); ?>">Exportar banco de questões em CSV</a></p>
        </div>
        <?php
    }

    public function process(): void
    {
        $this->guard();
        check_admin_referer('fd_questions_import');
        $file = $_FILES['questions_csv'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->storeAndRedirect(['rows' => 0, 'valid' => 0, 'invalid' => 1, 'created' => 0, 'errors' => [['line' => 0, 'code' => 'upload_failed']]]);
        }
        $tmp = is_string($file['tmp_name'] ?? null) ? (string) $file['tmp_name'] : '';
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || $size <= 0 || $size > QuestionCsvService::MAX_BYTES) {
            $this->storeAndRedirect(['rows' => 0, 'valid' => 0, 'invalid' => 1, 'created' => 0, 'errors' => [['line' => 0, 'code' => 'upload_size_invalid']]]);
        }
        try {
            $report = $this->service->import($tmp, get_current_user_id(), !empty($_POST['dry_run']));
        } catch (\Throwable $exception) {
            $report = ['rows' => 0, 'valid' => 0, 'invalid' => 1, 'created' => 0, 'errors' => [['line' => 0, 'code' => sanitize_key($exception->getMessage())]]];
        }
        $this->storeAndRedirect($report);
    }

    public function export(): never
    {
        $this->guard();
        check_admin_referer('fd_questions_export');
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="facil-digital-questoes-' . gmdate('Y-m-d') . '.csv"');
        $handle = fopen('php://output', 'wb');
        if ($handle === false) {
            wp_die('Não foi possível abrir a saída CSV.');
        }
        $this->service->exportToStream($handle);
        fclose($handle);
        exit;
    }

    /** @param array<string,mixed> $report */
    private function storeAndRedirect(array $report): never
    {
        set_transient(self::REPORT_PREFIX . get_current_user_id(), $report, 300);
        wp_safe_redirect(add_query_arg(['page' => self::SLUG], admin_url('admin.php')));
        exit;
    }

    private function guard(): void
    {
        if (!current_user_can(Capabilities::MANAGE_QUESTIONS)) {
            wp_die(esc_html__('Acesso negado.', 'facil-digital-core'));
        }
    }
}
