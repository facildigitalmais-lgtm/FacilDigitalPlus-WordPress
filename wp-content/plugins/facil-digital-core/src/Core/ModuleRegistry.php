<?php

declare(strict_types=1);

namespace FacilDigital\Core\Core;

use FacilDigital\Core\Admin\Menu;
use FacilDigital\Core\Admin\OperationsAdminModule;
use FacilDigital\Core\API\EntitlementController;
use FacilDigital\Core\API\HealthController;
use FacilDigital\Core\API\PdfController;
use FacilDigital\Core\API\SimulationController;
use FacilDigital\Core\API\StatusController;
use FacilDigital\Core\CLI\QaCommand;
use FacilDigital\Core\CLI\QuestionImportCommand;
use FacilDigital\Core\CLI\StatusCommand;
use FacilDigital\Core\Contests\ContestModule;
use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Entitlements\EntitlementModule;
use FacilDigital\Core\Import\ImportAdminModule;
use FacilDigital\Core\PDFs\DownloadModule;
use FacilDigital\Core\PDFs\PdfGenerationModule;
use FacilDigital\Core\PDFs\PdfMasterModule;
use FacilDigital\Core\Products\ProductMetadata;
use FacilDigital\Core\Release\ReleaseAdminModule;
use FacilDigital\Core\Release\ReleaseCommand;
use FacilDigital\Core\Questions\QuestionAdminModule;
use FacilDigital\Core\Simulations\SimulationAdminModule;
use FacilDigital\Core\Simulations\SimulationFrontendModule;
use FacilDigital\Core\Students\AccountModule;
use FacilDigital\Core\Students\SimulationAccountModule;
use FacilDigital\Core\WooCommerce\CheckoutModule;
use FacilDigital\Core\WooCommerce\MercadoPagoModule;

final class ModuleRegistry
{
    /** @return list<ModuleInterface> */
    public static function defaults(): array
    {
        return [
            new Menu(),
            new HealthController(),
            new StatusController(),
            new StatusCommand(),
            new QaCommand(),
            new ReleaseCommand(),
            new ReleaseAdminModule(),
            new QuestionImportCommand(),
            new ContestModule(),
            new ProductMetadata(),
            new CheckoutModule(),
            new MercadoPagoModule(),
            new EntitlementModule(),
            new EntitlementController(),
            new PdfMasterModule(),
            new PdfGenerationModule(),
            new DownloadModule(),
            new AccountModule(),
            new PdfController(),
            new QuestionAdminModule(),
            new SimulationAdminModule(),
            new SimulationFrontendModule(),
            new SimulationAccountModule(),
            new SimulationController(),
            new OperationsAdminModule(),
            new ImportAdminModule(),
        ];
    }
}
