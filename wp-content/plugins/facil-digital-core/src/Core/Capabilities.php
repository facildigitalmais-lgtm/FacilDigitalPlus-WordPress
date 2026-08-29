<?php

declare(strict_types=1);

namespace FacilDigital\Core\Core;

use WP_Role;

final class Capabilities
{
    public const VERSION = '1.0.0';
    public const OPTION_VERSION = 'facil_digital_core_capabilities_version';

    public const ACCESS_ADMIN = 'facil_digital_access_admin';
    public const MANAGE_CONTESTS = 'facil_digital_manage_contests';
    public const MANAGE_APOSTILAS = 'facil_digital_manage_apostilas';
    public const MANAGE_ENTITLEMENTS = 'facil_digital_manage_entitlements';
    public const MANAGE_PDFS = 'facil_digital_manage_pdfs';
    public const MANAGE_DOWNLOADS = 'facil_digital_manage_downloads';
    public const MANAGE_QUESTIONS = 'facil_digital_manage_questions';
    public const MANAGE_SIMULATIONS = 'facil_digital_manage_simulations';
    public const VIEW_RESULTS = 'facil_digital_view_results';
    public const VIEW_RANKINGS = 'facil_digital_view_rankings';
    public const MANAGE_STUDENTS = 'facil_digital_manage_students';
    public const VIEW_REPORTS = 'facil_digital_view_reports';
    public const MANAGE_SETTINGS = 'facil_digital_manage_settings';

    public const ROLE_MANAGER = 'facil_digital_manager';
    public const ROLE_QUESTION_EDITOR = 'facil_digital_question_editor';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ACCESS_ADMIN,
            self::MANAGE_CONTESTS,
            self::MANAGE_APOSTILAS,
            self::MANAGE_ENTITLEMENTS,
            self::MANAGE_PDFS,
            self::MANAGE_DOWNLOADS,
            self::MANAGE_QUESTIONS,
            self::MANAGE_SIMULATIONS,
            self::VIEW_RESULTS,
            self::VIEW_RANKINGS,
            self::MANAGE_STUDENTS,
            self::VIEW_REPORTS,
            self::MANAGE_SETTINGS,
        ];
    }

    /**
     * @return list<string>
     */
    public static function managerCapabilities(): array
    {
        return array_values(
            array_filter(
                self::all(),
                static fn (string $capability): bool =>
                    $capability !== self::MANAGE_SETTINGS
            )
        );
    }

    /**
     * @return list<string>
     */
    public static function questionEditorCapabilities(): array
    {
        return [
            self::ACCESS_ADMIN,
            self::MANAGE_QUESTIONS,
        ];
    }

    public static function installedVersion(): string
    {
        return (string) get_option(self::OPTION_VERSION, '0.0.0');
    }

    public static function maybeRun(): void
    {
        if (
            version_compare(
                self::installedVersion(),
                self::VERSION,
                '>='
            ) && self::isReady()
        ) {
            return;
        }

        self::install();
    }

    public static function install(): void
    {
        self::syncAdministrator();
        self::syncCustomRole(
            self::ROLE_MANAGER,
            __('Gerente Fácil Digital+', 'facil-digital-core'),
            self::managerCapabilities()
        );
        self::syncCustomRole(
            self::ROLE_QUESTION_EDITOR,
            __('Editor de Questões', 'facil-digital-core'),
            self::questionEditorCapabilities()
        );

        update_option(
            self::OPTION_VERSION,
            self::VERSION,
            false
        );
    }

    public static function isReady(): bool
    {
        if (
            version_compare(
                self::installedVersion(),
                self::VERSION,
                '<'
            )
        ) {
            return false;
        }

        $administrator = get_role('administrator');
        $manager = get_role(self::ROLE_MANAGER);
        $questionEditor = get_role(self::ROLE_QUESTION_EDITOR);

        if (
            !$administrator instanceof WP_Role ||
            !$manager instanceof WP_Role ||
            !$questionEditor instanceof WP_Role
        ) {
            return false;
        }

        if (!self::roleHasCapabilities($administrator, self::all())) {
            return false;
        }

        if (!self::roleHasCapabilities($manager, self::managerCapabilities())) {
            return false;
        }

        if ($manager->has_cap(self::MANAGE_SETTINGS)) {
            return false;
        }

        if (
            !self::roleHasCapabilities(
                $questionEditor,
                self::questionEditorCapabilities()
            )
        ) {
            return false;
        }

        foreach (self::all() as $capability) {
            $shouldHave = in_array(
                $capability,
                self::questionEditorCapabilities(),
                true
            );

            if ($questionEditor->has_cap($capability) !== $shouldHave) {
                return false;
            }
        }

        return true;
    }

    private static function syncAdministrator(): void
    {
        $administrator = get_role('administrator');

        if (!$administrator instanceof WP_Role) {
            return;
        }

        foreach (self::all() as $capability) {
            $administrator->add_cap($capability, true);
        }
    }

    /**
     * @param list<string> $capabilities
     */
    private static function syncCustomRole(
        string $roleKey,
        string $displayName,
        array $capabilities
    ): void {
        $role = get_role($roleKey);

        if (!$role instanceof WP_Role) {
            add_role(
                $roleKey,
                $displayName,
                ['read' => true]
            );
            $role = get_role($roleKey);
        }

        if (!$role instanceof WP_Role) {
            return;
        }

        $role->add_cap('read', true);

        foreach (self::all() as $capability) {
            if (in_array($capability, $capabilities, true)) {
                $role->add_cap($capability, true);
                continue;
            }

            $role->remove_cap($capability);
        }
    }

    /**
     * @param list<string> $capabilities
     */
    private static function roleHasCapabilities(
        WP_Role $role,
        array $capabilities
    ): bool {
        foreach ($capabilities as $capability) {
            if (!$role->has_cap($capability)) {
                return false;
            }
        }

        return true;
    }
}
