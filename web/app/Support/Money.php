<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function toCents(string $value): int
    {
        $value = str_replace([' ', "\u{00A0}"], '', trim($value));

        if (str_contains($value, ',')) {
            if (! preg_match('/^-?(?:\d{1,3}(?:\.\d{3})*|\d+)(?:,\d{1,2})?$/', $value)) {
                throw new InvalidArgumentException('El importe no tiene un formato válido.');
            }

            [$units, $decimals] = array_pad(explode(',', $value, 2), 2, '');
            $normalized = str_replace('.', '', $units).($decimals !== '' ? '.'.$decimals : '');
        } elseif (preg_match('/^-?\d{1,3}(?:\.\d{3})+$/', $value)) {
            $normalized = str_replace('.', '', $value);
        } else {
            $normalized = $value;
        }

        if (! preg_match('/^-?\d+(?:\.\d{1,2})?$/', $normalized)) {
            throw new InvalidArgumentException('El importe no tiene un formato válido.');
        }

        $negative = str_starts_with($normalized, '-');
        $unsigned = ltrim($normalized, '-');
        [$units, $decimals] = array_pad(explode('.', $unsigned, 2), 2, '');
        $cents = ((int) $units * 100) + (int) str_pad($decimals, 2, '0');

        return $negative ? -$cents : $cents;
    }
}
