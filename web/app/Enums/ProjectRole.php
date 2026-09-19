<?php

declare(strict_types=1);

namespace App\Enums;

enum ProjectRole: string
{
    case Owner = 'owner';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Propietario',
            self::Member => 'Miembro',
        };
    }
}
