<?php

declare(strict_types=1);

namespace App\Actions\Movements;

use App\Enums\FinancialAccountType;
use App\Enums\MovementType;
use App\Models\FinancialAccount;
use App\Models\Movement;
use LogicException;

final class RebuildMovementEntries
{
    public function handle(Movement $movement): void
    {
        $movement->entries()->delete();

        if ($movement->trashed_at !== null) {
            return;
        }

        $source = $movement->account;
        if ($source === null) {
            throw new LogicException('El movimiento no tiene una cuenta de origen válida.');
        }

        match ($movement->type) {
            MovementType::Expense => $this->entry($movement, $source, $this->expenseSign($source) * $movement->amount_cents),
            MovementType::Income => $this->entry($movement, $source, $movement->amount_cents),
            MovementType::Refund => $this->entry($movement, $source, $this->refundSign($source) * $movement->amount_cents),
            MovementType::Transfer, MovementType::InvestmentContribution => $this->transfer($movement, $source),
        };
    }

    private function transfer(Movement $movement, FinancialAccount $source): void
    {
        $destination = $movement->destinationAccount;
        if ($destination === null || $destination->is($source)) {
            throw new LogicException('La transferencia necesita dos cuentas distintas.');
        }

        $this->entry($movement, $source, -$movement->amount_cents);
        $destinationSign = $destination->type === FinancialAccountType::CreditCard ? -1 : 1;
        $this->entry($movement, $destination, $destinationSign * $movement->amount_cents);
    }

    private function expenseSign(FinancialAccount $account): int
    {
        return $account->type === FinancialAccountType::CreditCard ? 1 : -1;
    }

    private function refundSign(FinancialAccount $account): int
    {
        return $account->type === FinancialAccountType::CreditCard ? -1 : 1;
    }

    private function entry(Movement $movement, FinancialAccount $account, int $signedAmountCents): void
    {
        $movement->entries()->create([
            'project_id' => $movement->project_id,
            'financial_account_id' => $account->id,
            'signed_amount_cents' => $signedAmountCents,
            'occurred_on' => $movement->occurred_on->toDateString(),
        ]);
    }
}
