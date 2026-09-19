<?php

declare(strict_types=1);

namespace App\Enums;

enum GoalAllocationDirection: string
{
    case Contribution = 'contribution';
    case Withdrawal = 'withdrawal';

    public function label(): string
    {
        return $this === self::Contribution ? 'Aportación' : 'Retirada';
    }
}
