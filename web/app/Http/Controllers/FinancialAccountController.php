<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Audit\RecordProjectAudit;
use App\Enums\FinancialAccountType;
use App\Models\FinancialAccount;
use App\Models\Project;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FinancialAccountController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $accounts = $project->financialAccounts()
            ->withSum('entries as entries_total', 'signed_amount_cents')
            ->orderByRaw('archived_at is not null')
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $summaries = [
            'liquidity' => 0,
            'savings' => 0,
            'investment' => 0,
            'credit' => 0,
        ];
        foreach ($accounts->whereNull('archived_at') as $account) {
            $key = match ($account->type) {
                FinancialAccountType::Checking, FinancialAccountType::Cash => 'liquidity',
                FinancialAccountType::Savings => 'savings',
                FinancialAccountType::ExternalInvestment => 'investment',
                FinancialAccountType::CreditCard => 'credit',
            };
            $summaries[$key] += $account->currentBalanceCents();
        }

        return view('financial-accounts.index', [
            'project' => $project,
            'accounts' => $accounts,
            'accountTypes' => FinancialAccountType::cases(),
            'summaries' => $summaries,
            'today' => now('Europe/Madrid')->toDateString(),
            'canManage' => $request->user()->can('manageAccounts', $project),
        ]);
    }

    public function store(Request $request, Project $project, RecordProjectAudit $audit): RedirectResponse
    {
        $this->authorize('manageAccounts', $project);
        $validated = $this->validated($request);

        DB::transaction(function () use ($request, $project, $validated, $audit): void {
            $account = FinancialAccount::create([
                'project_id' => $project->id,
                ...$this->attributes($validated),
                'position' => ((int) $project->financialAccounts()->max('position')) + 10,
                'created_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);
            $audit->handle($project, $request->user(), 'account', $account->id, 'created', null, $account->auditSnapshot());
        });

        return back()->with('status', 'Cuenta creada correctamente.');
    }

    public function edit(Project $project, FinancialAccount $account): View
    {
        $this->authorize('manageAccounts', $project);
        $this->ensureAccount($project, $account);

        return view('financial-accounts.edit', [
            'project' => $project,
            'account' => $account,
            'accountTypes' => FinancialAccountType::cases(),
            'hasMovements' => $account->entries()->exists() || $project->movements()->where(fn ($query) => $query
                ->where('financial_account_id', $account->id)
                ->orWhere('destination_account_id', $account->id))->exists(),
        ]);
    }

    public function update(Request $request, Project $project, FinancialAccount $account, RecordProjectAudit $audit): RedirectResponse
    {
        $this->authorize('manageAccounts', $project);
        $this->ensureAccount($project, $account);
        $validated = $this->validated($request);
        $hasMovements = $project->movements()->where(fn ($query) => $query
            ->where('financial_account_id', $account->id)
            ->orWhere('destination_account_id', $account->id))->exists();

        if ($hasMovements && $validated['type'] !== $account->type->value) {
            throw ValidationException::withMessages(['type' => 'El tipo de una cuenta con movimientos no puede cambiarse.']);
        }

        $firstMovementDate = $project->movements()
            ->where(fn ($query) => $query->where('financial_account_id', $account->id)->orWhere('destination_account_id', $account->id))
            ->min('occurred_on');
        if ($firstMovementDate !== null && $validated['initial_balance_date'] > $firstMovementDate) {
            throw ValidationException::withMessages([
                'initial_balance_date' => 'La fecha inicial debe ser igual o anterior al primer movimiento de la cuenta ('.date('d/m/Y', strtotime($firstMovementDate)).').',
            ]);
        }

        DB::transaction(function () use ($request, $project, $account, $validated, $audit): void {
            $before = $account->auditSnapshot();
            $account->update([...$this->attributes($validated), 'updated_by_user_id' => $request->user()->id]);
            $audit->handle($project, $request->user(), 'account', $account->id, 'updated', $before, $account->fresh()->auditSnapshot());
        });

        return redirect()->route('financial-accounts.index', $project)->with('status', 'Cuenta actualizada correctamente.');
    }

    public function archive(Request $request, Project $project, FinancialAccount $account, RecordProjectAudit $audit): RedirectResponse
    {
        $this->authorize('manageAccounts', $project);
        $this->ensureAccount($project, $account);
        if ($account->archived_at !== null) {
            return back();
        }

        DB::transaction(function () use ($request, $project, $account, $audit): void {
            $before = $account->auditSnapshot();
            $account->update(['archived_at' => now(), 'updated_by_user_id' => $request->user()->id]);
            $audit->handle($project, $request->user(), 'account', $account->id, 'archived', $before, $account->fresh()->auditSnapshot());
        });

        return back()->with('status', 'Cuenta archivada. Su historial se conserva.');
    }

    public function restore(Request $request, Project $project, FinancialAccount $account, RecordProjectAudit $audit): RedirectResponse
    {
        $this->authorize('manageAccounts', $project);
        $this->ensureAccount($project, $account);
        if ($account->archived_at === null) {
            return back();
        }

        DB::transaction(function () use ($request, $project, $account, $audit): void {
            $before = $account->auditSnapshot();
            $account->update(['archived_at' => null, 'updated_by_user_id' => $request->user()->id]);
            $audit->handle($project, $request->user(), 'account', $account->id, 'restored', $before, $account->fresh()->auditSnapshot());
        });

        return back()->with('status', 'Cuenta reactivada.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(FinancialAccountType::class)],
            'initial_balance' => ['required', 'regex:/^-?(?:(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?|\d+(?:\.\d{1,2})?)$/'],
            'initial_balance_date' => ['required', 'date'],
            'credit_limit' => ['nullable', 'regex:/^(?:(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?|\d+(?:\.\d{1,2})?)$/'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['required', Rule::in(['wallet', 'bank', 'cash', 'card', 'savings', 'investment'])],
        ], [
            'initial_balance.regex' => 'Introduce un saldo inicial válido con un máximo de dos decimales.',
            'credit_limit.regex' => 'Introduce un límite válido con un máximo de dos decimales.',
        ]);
    }

    /** @param array<string, mixed> $validated @return array<string, mixed> */
    private function attributes(array $validated): array
    {
        $creditLimitCents = isset($validated['credit_limit']) && $validated['credit_limit'] !== ''
            ? Money::toCents($validated['credit_limit'])
            : null;

        return [
            'name' => trim($validated['name']),
            'type' => $validated['type'],
            'initial_balance_cents' => Money::toCents($validated['initial_balance']),
            'initial_balance_date' => $validated['initial_balance_date'],
            'credit_limit_cents' => $validated['type'] === FinancialAccountType::CreditCard->value ? $creditLimitCents : null,
            'color' => $validated['color'],
            'icon' => $validated['icon'],
        ];
    }

    private function ensureAccount(Project $project, FinancialAccount $account): void
    {
        abort_unless($account->project_id === $project->id, 404);
    }
}
