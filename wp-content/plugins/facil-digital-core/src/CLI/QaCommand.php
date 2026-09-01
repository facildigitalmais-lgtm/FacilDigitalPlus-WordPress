<?php

declare(strict_types=1);

namespace FacilDigital\Core\CLI;

use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Security\SecurityAudit;
use FacilDigital\Core\Support\QaService;

final class QaCommand implements ModuleInterface
{
    public function register(): void
    {
        if (!defined('WP_CLI') || !WP_CLI || !class_exists('WP_CLI')) {
            return;
        }
        \WP_CLI::add_command('facil-digital qa', [$this, 'qa']);
        \WP_CLI::add_command('facil-digital security-audit', [$this, 'security']);
    }

    /** @param list<string> $args @param array<string,mixed> $assocArgs */
    public function qa(array $args, array $assocArgs): void
    {
        unset($args);
        $report = (new QaService())->run();
        $this->output($report, (string) ($assocArgs['format'] ?? 'table'));
        if (!$report['ready']) {
            \WP_CLI::error('QA Fácil Digital+ encontrou bloqueios.');
        }
        if (($assocArgs['format'] ?? 'table') !== 'json') {
            \WP_CLI::success('QA Fácil Digital+ aprovado.');
        }
    }

    /** @param list<string> $args @param array<string,mixed> $assocArgs */
    public function security(array $args, array $assocArgs): void
    {
        unset($args);
        $report = (new SecurityAudit())->run();
        $this->output($report, (string) ($assocArgs['format'] ?? 'table'));
        if (!$report['ready']) {
            \WP_CLI::error('Auditoria de segurança encontrou bloqueios.');
        }
        if (($assocArgs['format'] ?? 'table') !== 'json') {
            \WP_CLI::success('Auditoria de segurança aprovada.');
        }
    }

    /** @param array<string,mixed> $report */
    private function output(array $report, string $format): void
    {
        if ($format === 'json') {
            \WP_CLI::line((string) wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return;
        }
        $rows = [];
        foreach ($report as $key => $value) {
            if (is_array($value)) {
                $value = (string) wp_json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($value)) {
                $value = $value ? 'yes' : 'no';
            }
            $rows[] = ['check' => (string) $key, 'value' => (string) $value];
        }
        \WP_CLI\Utils\format_items('table', $rows, ['check','value']);
    }
}
