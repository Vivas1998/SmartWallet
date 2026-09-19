<?php

declare(strict_types=1);

namespace App\Enums;

enum CategoryType: string
{
    case Expense = 'expense';
    case Income = 'income';

    public function label(): string
    {
        return match ($this) {
            self::Expense => 'Gastos',
            self::Income => 'Ingresos',
        };
    }
}
