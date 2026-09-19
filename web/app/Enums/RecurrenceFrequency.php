<?php

declare(strict_types=1);

namespace App\Enums;

enum RecurrenceFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Annual = 'annual';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Diaria',
            self::Weekly => 'Semanal',
            self::Monthly => 'Mensual',
            self::Annual => 'Anual',
        };
    }
}
