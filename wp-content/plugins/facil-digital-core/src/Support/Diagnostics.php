<?php

declare(strict_types=1);

namespace FacilDigital\Core\Support;

use FacilDigital\Core\Core\Capabilities;
use FacilDigital\Core\Core\Database;

final class Diagnostics
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $requirementsErrors = (new Requirements())->validate();
        $missingTables = Database::missingTables();
        $databaseReady = Database::isReady();
        $capabilitiesReady = Capabilities::isReady();

        return [
            'ready' =>
                $requirementsErrors === []
                && $databaseReady
                && $capabilitiesReady,

            'core_version' => defined('FACIL_DIGITAL_CORE_VERSION')
                ? FACIL_DIGITAL_CORE_VERSION
                : 'unknown',

            'schema_version' => Database::installedVersion(),
            'schema_target' => Database::SCHEMA_VERSION,
            'database_ready' => $databaseReady,
            'missing_tables' => $missingTables,

            'capabilities_version' => Capabilities::installedVersion(),
            'capabilities_target' => Capabilities::VERSION,
            'capabilities_ready' => $capabilitiesReady,

            'woocommerce_active' => class_exists('WooCommerce'),

            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'environment' => wp_get_environment_type(),

            'requirements_errors' => $requirementsErrors,
        ];
    }
}
