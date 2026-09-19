<?php

declare(strict_types=1);

namespace App\Enums;

enum MovementType: string
{
    case Expense = 'expense';
    case Income = 'income';
    case Transfer = 'transfer';
    case Refund = 'refund';
    case InvestmentContribution = 'investment_contribution';

    public function label(): string
    {
        return match ($this) {
            self::Expense => 'Gasto',
            self::Income => 'Ingreso',
            self::Transfer => 'Transferencia',
            self::Refund => 'Devolución',
            self::InvestmentContribution => 'Aportación a inversión',
        };
    }
}
