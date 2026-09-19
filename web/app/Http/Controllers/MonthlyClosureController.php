<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Audit\RecordProjectAudit;
use App\Actions\Budgets\BuildMonthlyClosure;
use App\Actions\Movements\RebuildMovementEntries;
use App\Actions\SavingsGoals\SyncGoalAllocation;
use App\Enums\FinancialAccountType;
use App\Enums\GoalAllocationDirection;
use App\Enums\MovementType;
use App\Models\FinancialAccount;
use App\Models\MonthlyLeftoverAllocation;
use App\Models\Movement;
use App\Models\Project;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MonthlyClosureController extends Controller
{
    public function show(Request $request, Project $project, BuildMonthlyClosure $closures): View
    {
        $this->authorize('view', $project);
        $validated = $request->validate(['month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);
        $month = $this->monthFrom($validated['month'] ?? null);
        $monthEnd = $month->endOfMonth();
        $completed = $monthEnd->isBefore(CarbonImmutable::now('Europe/Madrid')->startOfDay());
        $summary = $closures->handle($project, $month);

        $accountsAtClose = $project->financialAccounts()
            ->whereDate('initial_balance_date', '<=', $monthEnd->toDateString())
            ->withSum(['entries as entries_total' => fn ($query) => $query->whereDate('occurred_on', '<=', $monthEnd->toDateString())], 'signed_amount_cents')
            ->orderBy('position')->get();
        $sourceAccounts = $project->financialAccounts()
            ->whereNull('archived_at')
            ->whereIn('type', $this->sourceTypes())
            ->withSum('entries as entries_total', 'signed_amount_cents')
            ->orderBy('position')->get()
            ->filter(fn (FinancialAccount $account): bool => $account->currentBalanceCents() > 0)
            ->values();
        $destinationAccounts = $project->financialAccounts()
            ->whereNull('archived_at')
            ->whereIn('type', $this->destinationTypes())
            ->orderBy('position')->get();

        $canManage = $request->user()->can('manageBudgets', $project);
        $hasCompatiblePair = $sourceAccounts->contains(fn (FinancialAccount $source): bool => $destinationAccounts->contains(fn (FinancialAccount $destination): bool => ! $source->is($destination)));

        return view('budgets.closure', [
            'project' => $project,
            'month' => $month,
            'summary' => $summary,
            'completed' => $completed,
            'accountsAtClose' => $accountsAtClose,
            'sourceAccounts' => $sourceAccounts,
            'destinationAccounts' => $destinationAccounts,
            'goals' => $project->savingsGoals()->whereNull('archived_at')->whereIn('financial_account_id', $destinationAccounts->modelKeys())->with('account')->orderBy('name')->get(),
            'canManage' => $canManage,
            'hasCompatiblePair' => $hasCompatiblePair,
            'canAllocate' => $completed
                && $canManage
                && $summary['available_to_allocate_cents'] > 0
                && $hasCompatiblePair,
            'today' => CarbonImmutable::now('Europe/Madrid')->toDateString(),
            'minimumDate' => $monthEnd->toDateString(),
        ]);
    }

    public function store(
        Request $request,
        Project $project,
        BuildMonthlyClosure $closures,
        RebuildMovementEntries $entries,
        SyncGoalAllocation $goalAllocation,
        RecordProjectAudit $audit,
    ): RedirectResponse {
        $this->authorize('manageBudgets', $project);
        $validated = $request->validate([
            'month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'amount' => ['required', 'regex:/^(?:(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?|\d+(?:\.\d{1,2})?)$/'],
            'financial_account_id' => ['required', 'integer'],
            'destination_account_id' => ['required', 'integer', 'different:financial_account_id'],
            'savings_goal_id' => ['nullable', 'integer'],
            'occurred_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'amount.regex' => 'Introduce un importe positivo válido con un máximo de dos decimales.',
            'destination_account_id.different' => 'Elige una cuenta de destino distinta.',
        ]);
        $month = $this->monthFrom($validated['month']);
        $amountCents = Money::toCents($validated['amount']);
        $occurredOn = CarbonImmutable::parse($validated['occurred_on'], 'Europe/Madrid')->startOfDay();
        $today = CarbonImmutable::now('Europe/Madrid')->startOfDay();
        if (! $month->endOfMonth()->isBefore($today)) {
            throw ValidationException::withMessages(['month' => 'El cierre estará disponible cuando termine el mes.']);
        }
        if ($amountCents <= 0) {
            throw ValidationException::withMessages(['amount' => 'El importe debe ser mayor que cero.']);
        }
        if ($occurredOn->isAfter($today) || $occurredOn->isBefore($month->endOfMonth()->startOfDay())) {
            throw ValidationException::withMessages(['occurred_on' => 'La transferencia debe fecharse entre el final del mes y hoy.']);
        }

        DB::transaction(function () use ($request, $project, $closures, $entries, $goalAllocation, $audit, $validated, $month, $amountCents, $occurredOn): void {
            Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $summary = $closures->handle($project, $month);
            if ($amountCents > $summary['available_to_allocate_cents']) {
                throw ValidationException::withMessages(['amount' => 'El importe supera el sobrante que todavía puede destinarse.']);
            }

            $accounts = FinancialAccount::query()
                ->where('project_id', $project->id)
                ->whereIn('id', [(int) $validated['financial_account_id'], (int) $validated['destination_account_id']])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $accounts->get((int) $validated['financial_account_id']);
            $destination = $accounts->get((int) $validated['destination_account_id']);
            $this->ensureAccounts($source, $destination, $occurredOn);
            if ($source->currentBalanceCents() < $amountCents) {
                throw ValidationException::withMessages(['financial_account_id' => 'La cuenta de origen no tiene saldo suficiente para esta transferencia.']);
            }

            $goal = null;
            if (! empty($validated['savings_goal_id'])) {
                $goal = $project->savingsGoals()->whereNull('archived_at')->whereKey((int) $validated['savings_goal_id'])->first();
                if ($goal === null || $goal->financial_account_id !== $destination->id) {
                    throw ValidationException::withMessages(['savings_goal_id' => 'El objetivo debe estar activo y vinculado a la cuenta de destino.']);
                }
            }
            $type = $destination->type === FinancialAccountType::ExternalInvestment
                ? MovementType::InvestmentContribution
                : MovementType::Transfer;
            $movement = Movement::create([
                'project_id' => $project->id,
                'type' => $type,
                'amount_cents' => $amountCents,
                'occurred_on' => $occurredOn->toDateString(),
                'concept' => 'Sobrante de '.$month->locale('es')->translatedFormat('F Y').' destinado a '.$destination->name,
                'financial_account_id' => $source->id,
                'destination_account_id' => $destination->id,
                'notes' => $this->nullableTrim($validated['notes'] ?? null),
                'created_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);
            MonthlyLeftoverAllocation::create([
                'project_id' => $project->id,
                'movement_id' => $movement->id,
                'budget_month' => $month->toDateString(),
            ]);
            $entries->handle($movement);
            $goalAllocation->handle($movement, $goal, $goal === null ? null : GoalAllocationDirection::Contribution);
            $audit->handle($project, $request->user(), 'movement', $movement->id, 'created', null, $movement->fresh()->auditSnapshot());
        });

        return redirect()->route('budgets.closure', ['project' => $project, 'month' => $month->format('Y-m')])
            ->with('status', 'Sobrante destinado mediante una transferencia real. El resultado del mes no ha cambiado.');
    }

    /** @return list<string> */
    private function sourceTypes(): array
    {
        return [FinancialAccountType::Checking->value, FinancialAccountType::Savings->value, FinancialAccountType::Cash->value];
    }

    /** @return list<string> */
    private function destinationTypes(): array
    {
        return [FinancialAccountType::Savings->value, FinancialAccountType::ExternalInvestment->value];
    }

    private function ensureAccounts(?FinancialAccount $source, ?FinancialAccount $destination, CarbonImmutable $occurredOn): void
    {
        if ($source === null || $source->archived_at !== null || ! in_array($source->type->value, $this->sourceTypes(), true)) {
            throw ValidationException::withMessages(['financial_account_id' => 'Selecciona una cuenta de origen activa con saldo disponible.']);
        }
        if ($destination === null || $destination->archived_at !== null || ! in_array($destination->type->value, $this->destinationTypes(), true)) {
            throw ValidationException::withMessages(['destination_account_id' => 'Selecciona una cuenta activa de ahorro o inversión externa.']);
        }
        if ($source->is($destination)) {
            throw ValidationException::withMessages(['destination_account_id' => 'Elige una cuenta de destino distinta.']);
        }
        if ($occurredOn->isBefore($source->initial_balance_date->startOfDay()) || $occurredOn->isBefore($destination->initial_balance_date->startOfDay())) {
            throw ValidationException::withMessages(['occurred_on' => 'La fecha no puede ser anterior al saldo inicial de las cuentas seleccionadas.']);
        }
    }

    private function monthFrom(?string $value): CarbonImmutable
    {
        $key = $value ?? CarbonImmutable::now('Europe/Madrid')->subMonth()->format('Y-m');

        return CarbonImmutable::createFromFormat('Y-m-d', $key.'-01', 'Europe/Madrid')->startOfMonth();
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
