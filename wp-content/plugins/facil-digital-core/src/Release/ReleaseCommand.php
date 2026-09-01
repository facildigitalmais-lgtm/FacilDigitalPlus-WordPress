<?php

declare(strict_types=1);

namespace FacilDigital\Core\Release;

use FacilDigital\Core\Contracts\ModuleInterface;

final class ReleaseCommand implements ModuleInterface
{
    public function __construct(
        private readonly ReleaseReadinessService $service = new ReleaseReadinessService()
    ) {
    }

    public function register(): void
    {
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }

        \WP_CLI::add_command('facil-digital release check', [$this, 'check']);
        \WP_CLI::add_command('facil-digital release payment', [$this, 'payment']);
    }

    /** @param list<string> $args @param array<string,mixed> $assocArgs */
    public function check(array $args, array $assocArgs): void
    {
        unset($args);
        $stage = isset($assocArgs['stage']) ? (string) $assocArgs['stage'] : 'sandbox';
        $format = isset($assocArgs['format']) ? (string) $assocArgs['format'] : 'table';
        $report = $this->service->report($stage);

        if ($format === 'json') {
            \WP_CLI::line((string) wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $rows = [];
            foreach ($report['checks'] as $check) {
                $rows[] = [
                    'check' => $check['id'],
                    'status' => $check['status'],
                    'message' => $check['message'],
                ];
            }
            \WP_CLI\Utils\format_items('table', $rows, ['check', 'status', 'message']);
            \WP_CLI::line('Automated ready: ' . ($report['ready_automated'] ? 'yes' : 'no'));
            \WP_CLI::line('Manual gates: ' . implode(', ', $report['manual_gates']));
        }

        if (!$report['ready_automated']) {
            \WP_CLI::halt(1);
        }
    }

    /** @param list<string> $args @param array<string,mixed> $assocArgs */
    public function payment(array $args, array $assocArgs): void
    {
        unset($args);
        $orderId = isset($assocArgs['order']) ? (int) $assocArgs['order'] : 0;
        $requirePdf = !isset($assocArgs['require-pdf']) || (string) $assocArgs['require-pdf'] !== '0';
        if ($orderId <= 0) {
            \WP_CLI::error('Informe --order=<ID>.');
        }

        $proof = $this->service->paymentProof($orderId, $requirePdf);
        \WP_CLI::line((string) wp_json_encode($proof, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if (!($proof['ready'] ?? false)) {
            \WP_CLI::halt(1);
        }
    }
}
