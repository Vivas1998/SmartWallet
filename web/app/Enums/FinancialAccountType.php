<?php

declare(strict_types=1);

namespace App\Enums;

enum FinancialAccountType: string
{
    case Checking = 'checking';
    case Savings = 'savings';
    case Cash = 'cash';
    case CreditCard = 'credit_card';
    case ExternalInvestment = 'external_investment';

    public function label(): string
    {
        return match ($this) {
            self::Checking => 'Cuenta corriente',
            self::Savings => 'Ahorro',
            self::Cash => 'Efectivo',
            self::CreditCard => 'Tarjeta de crédito',
            self::ExternalInvestment => 'Inversión externa',
        };
    }
}
