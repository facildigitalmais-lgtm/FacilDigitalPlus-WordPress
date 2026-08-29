<?php

declare(strict_types=1);

namespace FacilDigital\Core\Security;

final class Cpf
{
    public static function normalize(string $value): string
    {
        $digits = preg_replace(
            '/\D+/',
            '',
            $value
        );

        return is_string($digits)
            ? $digits
            : '';
    }

    public static function isValid(string $value): bool
    {
        $cpf = self::normalize($value);

        if (strlen($cpf) !== 11) {
            return false;
        }

        if (preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        for ($position = 9; $position <= 10; $position++) {
            $sum = 0;

            for ($index = 0; $index < $position; $index++) {
                $sum +=
                    (int) $cpf[$index]
                    * (($position + 1) - $index);
            }

            $digit = (10 * $sum) % 11;

            if ($digit === 10) {
                $digit = 0;
            }

            if ((int) $cpf[$position] !== $digit) {
                return false;
            }
        }

        return true;
    }

    public static function mask(string $value): string
    {
        $cpf = self::normalize($value);

        if (strlen($cpf) !== 11) {
            return '***.***.***-**';
        }

        return '***.***.***-' . substr($cpf, -2);
    }
}
