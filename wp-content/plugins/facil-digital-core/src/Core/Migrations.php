<?php

declare(strict_types=1);

namespace FacilDigital\Core\Core;

use RuntimeException;

final class Migrations
{
    private const LOCK_OPTION = 'facil_digital_core_migration_lock';
    private const LOCK_TTL = 300;

    public static function maybeRun(): void
    {
        if (
            version_compare(
                Database::installedVersion(),
                Database::SCHEMA_VERSION,
                '>='
            ) && Database::missingTables() === []
        ) {
            return;
        }

        self::run();
    }

    public static function run(): void
    {
        if (!self::acquireLock()) {
            return;
        }

        try {
            Installer::installSchema();

            $missing = Database::missingTables();
            if ($missing !== []) {
                throw new RuntimeException(
                    'Nao foi possivel criar todas as tabelas do Facil Digital+ Core.'
                );
            }

            update_option(
                Database::OPTION_SCHEMA_VERSION,
                Database::SCHEMA_VERSION,
                false
            );
        } finally {
            delete_option(self::LOCK_OPTION);
        }
    }

    private static function acquireLock(): bool
    {
        $now = time();
        $existing = (int) get_option(self::LOCK_OPTION, 0);

        if ($existing > 0 && ($now - $existing) > self::LOCK_TTL) {
            delete_option(self::LOCK_OPTION);
        }

        return add_option(
            self::LOCK_OPTION,
            (string) $now,
            '',
            false
        );
    }
}
