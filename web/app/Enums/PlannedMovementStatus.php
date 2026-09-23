<?php

declare(strict_types=1);

namespace App\Enums;

enum PlannedMovementStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Previsto',
            self::Completed => 'Realizado',
            self::Cancelled => 'Cancelado',
        };
    }
}
