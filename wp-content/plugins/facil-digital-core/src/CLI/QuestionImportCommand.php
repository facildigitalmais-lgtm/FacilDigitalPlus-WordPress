<?php

declare(strict_types=1);

namespace FacilDigital\Core\CLI;

use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Import\QuestionCsvService;

final class QuestionImportCommand implements ModuleInterface
{
    public function __construct(
        private readonly QuestionCsvService $service = new QuestionCsvService()
    ) {
    }

    public function register(): void
    {
        if (!defined('WP_CLI') || !WP_CLI || !class_exists('WP_CLI')) {
            return;
        }
        \WP_CLI::add_command('facil-digital questions import', [$this, 'import']);
        \WP_CLI::add_command('facil-digital questions export', [$this, 'export']);
    }

    /** @param list<string> $args @param array<string,mixed> $assocArgs */
    public function import(array $args, array $assocArgs): void
    {
        $path = (string) ($args[0] ?? '');
        if ($path === '') {
            \WP_CLI::error('Informe o caminho do CSV.');
        }
        $dryRun = isset($assocArgs['dry-run']);
        $userId = isset($assocArgs['user-id']) ? absint($assocArgs['user-id']) : 0;
        $report = $this->service->import($path, $userId, $dryRun);
        $format = isset($assocArgs['format']) ? (string) $assocArgs['format'] : 'table';
        if ($format === 'json') {
            \WP_CLI::line((string) wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            \WP_CLI\Utils\format_items('table', [[
                'mode' => $dryRun ? 'dry-run' : 'import',
                'rows' => (string) $report['rows'],
                'valid' => (string) $report['valid'],
                'invalid' => (string) $report['invalid'],
                'created' => (string) $report['created'],
            ]], ['mode','rows','valid','invalid','created']);
        }
        if ((int) $report['invalid'] > 0) {
            \WP_CLI::warning('O CSV contém linhas inválidas. Consulte o relatório.');
        }
        if (!$dryRun && (int) $report['created'] > 0) {
            \WP_CLI::success('Questões importadas.');
        }
    }

    /** @param list<string> $args @param array<string,mixed> $assocArgs */
    public function export(array $args, array $assocArgs): void
    {
        unset($assocArgs);
        $path = (string) ($args[0] ?? '');
        if ($path === '') {
            \WP_CLI::error('Informe o caminho de saída.');
        }
        $count = $this->service->export($path);
        \WP_CLI::success(sprintf('%d questões exportadas.', $count));
    }
}
