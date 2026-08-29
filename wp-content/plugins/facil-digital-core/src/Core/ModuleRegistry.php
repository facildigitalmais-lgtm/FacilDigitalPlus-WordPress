<?php

declare(strict_types=1);

namespace FacilDigital\Core\Core;

use FacilDigital\Core\Admin\Menu;
use FacilDigital\Core\API\HealthController;
use FacilDigital\Core\API\StatusController;
use FacilDigital\Core\CLI\StatusCommand;
use FacilDigital\Core\Contracts\ModuleInterface;

final class ModuleRegistry
{
    /**
     * @return list<ModuleInterface>
     */
    public static function defaults(): array
    {
        return [
            new Menu(),
            new HealthController(),
            new StatusController(),
            new StatusCommand(),
        ];
    }
}
