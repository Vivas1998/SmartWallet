<?php

declare(strict_types=1);

namespace App\Enums;

enum ThemePreference: string
{
    case Auto = 'auto';
    case Light = 'light';
    case Dark = 'dark';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Automático',
            self::Light => 'Claro',
            self::Dark => 'Oscuro',
        };
    }
}
