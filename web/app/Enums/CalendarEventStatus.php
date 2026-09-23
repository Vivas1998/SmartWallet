<?php

declare(strict_types=1);

namespace App\Enums;

enum CalendarEventStatus: string
{
    case Planned = 'planned';
    case Today = 'today';
    case Overdue = 'overdue';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Previsto',
            self::Today => 'Hoy',
            self::Overdue => 'Vencido',
            self::Completed => 'Realizado',
            self::Cancelled => 'Cancelado',
            self::Skipped => 'Omitido',
        };
    }
}
