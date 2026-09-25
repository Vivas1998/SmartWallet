<?php

declare(strict_types=1);

namespace App\Enums;

enum CustomFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Boolean = 'boolean';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Texto corto',
            self::Number => 'Número decimal',
            self::Date => 'Fecha',
            self::Boolean => 'Sí/no',
        };
    }
}
