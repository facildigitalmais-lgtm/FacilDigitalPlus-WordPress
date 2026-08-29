<?php

declare(strict_types=1);

namespace FacilDigital\Core\Core;

use FacilDigital\Core\Support\Requirements;
use Throwable;

final class Activator
{
    public static function activate(): void
    {
        $errors = (new Requirements())->validate();

        if ($errors !== []) {
            wp_die(
                esc_html(implode(' ', $errors)),
                esc_html__('Facil Digital+ Core', 'facil-digital-core'),
                ['back_link' => true]
            );
        }

        try {
            Migrations::run();

            if (!Database::isReady()) {
                throw new \RuntimeException('Schema do Core nao ficou pronto.');
            }
        } catch (Throwable $exception) {
            unset($exception);

            wp_die(
                esc_html__(
                    'Falha ao preparar a base de dados do Facil Digital+ Core. Consulte os logs do ambiente e tente novamente.',
                    'facil-digital-core'
                ),
                esc_html__('Facil Digital+ Core', 'facil-digital-core'),
                ['back_link' => true]
            );
        }
    }
}
