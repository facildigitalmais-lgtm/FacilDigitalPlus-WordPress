<?php

declare(strict_types=1);

namespace FacilDigital\Core\Support;

final class Requirements
{
    /**
     * @return list<string>
     */
    public function validate(): array
    {
        $errors = [];

        if (
            version_compare(
                PHP_VERSION,
                '8.2.0',
                '<'
            )
        ) {
            $errors[] =
                'Facil Digital+ Core requer PHP 8.2 ou superior.';
        }

        $wordpressVersion =
            get_bloginfo(
                'version'
            );

        if (
            version_compare(
                $wordpressVersion,
                '7.0',
                '<'
            )
        ) {
            $errors[] =
                'Facil Digital+ Core requer WordPress 7.0 ou superior.';
        }

        if (
            !class_exists(
                'WooCommerce'
            )
        ) {
            $errors[] =
                'Facil Digital+ Core requer WooCommerce ativo.';
        }

        return $errors;
    }
}
