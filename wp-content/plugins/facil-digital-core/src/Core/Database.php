<?php

declare(strict_types=1);

namespace FacilDigital\Core\Core;

use InvalidArgumentException;

final class Database
{
    public const SCHEMA_VERSION = '1.1.0';
    public const OPTION_SCHEMA_VERSION = 'facil_digital_core_db_version';

    /**
     * @var array<string, string>
     */
    private const TABLES = [
        'questions' => 'fd_questions',
        'question_options' => 'fd_question_options',
        'simulations' => 'fd_simulations',
        'simulation_questions' => 'fd_simulation_questions',
        'simulation_products' => 'fd_simulation_products',
        'attempts' => 'fd_attempts',
        'attempt_answers' => 'fd_attempt_answers',
        'entitlements' => 'fd_entitlements',
        'pdf_files' => 'fd_pdf_files',
        'downloads' => 'fd_downloads',
    ];

    public static function installedVersion(): string
    {
        return (string) get_option(self::OPTION_SCHEMA_VERSION, '0.0.0');
    }

    public static function table(string $key): string
    {
        global $wpdb;

        if (!isset(self::TABLES[$key])) {
            throw new InvalidArgumentException('Tabela Facil Digital+ desconhecida.');
        }

        return $wpdb->prefix . self::TABLES[$key];
    }

    /**
     * @return array<string, string>
     */
    public static function tables(): array
    {
        $tables = [];

        foreach (array_keys(self::TABLES) as $key) {
            $tables[$key] = self::table($key);
        }

        return $tables;
    }

    /**
     * @return list<string>
     */
    public static function missingTables(): array
    {
        global $wpdb;

        $missing = [];

        foreach (self::tables() as $table) {
            $found = $wpdb->get_var(
                $wpdb->prepare(
                    'SHOW TABLES LIKE %s',
                    $wpdb->esc_like($table)
                )
            );

            if ($found !== $table) {
                $missing[] = $table;
            }
        }

        return $missing;
    }

    public static function isReady(): bool
    {
        return version_compare(
            self::installedVersion(),
            self::SCHEMA_VERSION,
            '>='
        ) && self::missingTables() === [];
    }
}
