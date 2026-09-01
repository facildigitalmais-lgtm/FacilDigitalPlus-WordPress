<?php

declare(strict_types=1);

namespace FacilDigital\Core\Core;

use FacilDigital\Core\Support\Requirements;
use RuntimeException;
use Throwable;

final class Activator
{
    public static function activate(): void
    {
        $errors = (new Requirements())->validate();

        if ($errors !== []) {
            wp_die(
                esc_html(implode(' ', $errors)),
                esc_html__('Fácil Digital+ Core', 'facil-digital-core'),
                ['back_link' => true]
            );
        }

        try {
            Migrations::run();
            Capabilities::install();

            if (!Database::isReady()) {
                throw new RuntimeException(
                    'Schema do Core não ficou pronto.'
                );
            }

            if (!Capabilities::isReady()) {
                throw new RuntimeException(
                    'Permissões do Core não ficaram prontas.'
                );
            }
        } catch (Throwable $exception) {
            unset($exception);

            wp_die(
                esc_html__(
                    'Falha ao preparar o Fácil Digital+ Core. Execute os validadores W3.',
                    'facil-digital-core'
                ),
                esc_html__('Fácil Digital+ Core', 'facil-digital-core'),
                ['back_link' => true]
            );
        }
    }
}
