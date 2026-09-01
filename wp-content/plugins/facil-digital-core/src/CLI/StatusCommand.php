<?php

declare(strict_types=1);

namespace FacilDigital\Core\CLI;

use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Support\Diagnostics;

final class StatusCommand implements ModuleInterface
{
    public function register(): void
    {
        if (
            !defined('WP_CLI')
            || !WP_CLI
            || !class_exists('WP_CLI')
        ) {
            return;
        }

        \WP_CLI::add_command(
            'facil-digital status',
            [$this, 'status']
        );
    }

    /**
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function status(
        array $args,
        array $assocArgs
    ): void {
        unset($args);

        $snapshot = (new Diagnostics())->snapshot();

        $format = isset($assocArgs['format'])
            ? (string) $assocArgs['format']
            : 'table';

        if ($format === 'json') {
            \WP_CLI::line(
                (string) wp_json_encode(
                    $snapshot,
                    JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_UNICODE
                )
            );
        } else {
            $rows = [];

            foreach ($snapshot as $key => $value) {
                if (is_array($value)) {
                    $value = $value === []
                        ? '-'
                        : implode(
                            ', ',
                            array_map(
                                'strval',
                                $value
                            )
                        );
                } elseif (is_bool($value)) {
                    $value = $value
                        ? 'yes'
                        : 'no';
                }

                $rows[] = [
                    'check' => (string) $key,
                    'value' => (string) $value,
                ];
            }

            \WP_CLI\Utils\format_items(
                'table',
                $rows,
                [
                    'check',
                    'value',
                ]
            );
        }

        if (!$snapshot['ready']) {
            \WP_CLI::error(
                'Fácil Digital+ Core não está pronto.'
            );
        }

        if ($format !== 'json') {
            \WP_CLI::success(
                'Fácil Digital+ Core pronto.'
            );
        }
    }
}
