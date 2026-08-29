<?php

declare(strict_types=1);

namespace FacilDigital\Core\Core;

use FacilDigital\Core\Admin\Menu;
use FacilDigital\Core\API\HealthController;
use FacilDigital\Core\Contracts\ModuleInterface;

final class Plugin
{
    private bool $booted = false;

    /**
     * @var list<ModuleInterface>
     */
    private array $modules;

    public function __construct()
    {
        $this->modules = [
            new Menu(),
            new HealthController(),
        ];
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        if (!defined('FACIL_DIGITAL_CORE_BOOTED')) {
            define('FACIL_DIGITAL_CORE_BOOTED', true);
        }

        foreach ($this->modules as $module) {
            $module->register();
        }
    }
}
