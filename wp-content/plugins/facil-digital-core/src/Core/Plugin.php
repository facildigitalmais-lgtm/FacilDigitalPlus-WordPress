<?php

declare(strict_types=1);

namespace FacilDigital\Core\Core;

use FacilDigital\Core\Contracts\ModuleInterface;

final class Plugin
{
    private bool $booted = false;

    /**
     * @var list<ModuleInterface>
     */
    private array $modules;

    /**
     * @param list<ModuleInterface>|null $modules
     */
    public function __construct(
        ?array $modules = null
    ) {
        $this->modules =
            $modules
            ?? ModuleRegistry::defaults();
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        if (
            !defined(
                'FACIL_DIGITAL_CORE_BOOTED'
            )
        ) {
            define(
                'FACIL_DIGITAL_CORE_BOOTED',
                true
            );
        }

        foreach ($this->modules as $module) {
            $module->register();
        }
    }
}
