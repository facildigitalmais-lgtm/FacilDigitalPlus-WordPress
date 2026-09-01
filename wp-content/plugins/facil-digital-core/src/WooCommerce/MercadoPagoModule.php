<?php

declare(strict_types=1);

namespace FacilDigital\Core\WooCommerce;

use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Core\Capabilities;

final class MercadoPagoModule implements ModuleInterface
{
    public function register(): void
    {
        add_action(
            'admin_notices',
            [$this, 'renderAdminNotice']
        );
    }

    public static function isOfficialPluginActive(): bool
    {
        return defined('MP_PLUGIN_FILE');
    }

    public function renderAdminNotice(): void
    {
        if (
            self::isOfficialPluginActive()
            || !current_user_can(Capabilities::MANAGE_SETTINGS)
        ) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__(
            'Fácil Digital+: o plugin oficial Mercado Pago para WooCommerce não está ativo. O Core não implementa gateway próprio.',
            'facil-digital-core'
        );
        echo '</p></div>';
    }
}
