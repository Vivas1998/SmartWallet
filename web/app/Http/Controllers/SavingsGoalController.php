<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Audit\RecordProjectAudit;
use App\Enums\FinancialAccountType;
use App\Enums\GoalAllocationDirection;
use App\Models\Project;
use App\Models\SavingsGoal;
use App\Support\CustomFieldValues;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SavingsGoalController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        return view('savings-goals.index', [
            'project' => $project,
            'goals' => $project->savingsGoals()->with(['account', 'allocations.movement'])
                ->orderByRaw('archived_at is not null')->orderBy('target_date')->orderBy('name')->get(),
            'eligibleAccounts' => $this->eligibleAccounts($project),
            'canManage' => $request->user()->can('manageSavingsGoals', $project),
        ]);
    }

    public function store(Request $request, Project $project, RecordProjectAudit $audit): RedirectResponse
    {
        $this->authorize('manageSavingsGoals', $project);
        $validated = $this->validated($request, $project);

        DB::transaction(function () use ($request, $project, $validated, $audit): void {
            $goal = SavingsGoal::create([
                'project_id' => $project->id,
                ...$validated,
                'created_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);
            $audit->handle($project, $request->user(), 'goal', $goal->id, 'created', null, $goal->auditSnapshot());
        });

        return back()->with('status', 'Objetivo de ahorro creado. Empieza a avanzar cuando vincules una transferencia.');
    }

    public function edit(Project $project, SavingsGoal $goal): View
    {
        $this->authorize('manageSavingsGoals', $project);
        $this->ensureGoal($project, $goal);

        return view('savings-goals.edit', [
            'project' => $project,
            'goal' => $goal,
            'eligibleAccounts' => $this->eligibleAccounts($project, $goal->financial_account_id),
        ]);
    }

    public function update(Request $request, Project $project, SavingsGoal $goal, RecordProjectAudit $audit): RedirectResponse
    {
        $this->authorize('manageSavingsGoals', $project);
        $this->ensureGoal($project, $goal);
        $validated = $this->validated($request, $project, $goal);
        if ($goal->allocations()->exists() && $validated['financial_account_id'] !== $goal->financial_account_id) {
            throw ValidationException::withMessages(['financial_account_id' => 'La cuenta no puede cambiar cuando el objetivo ya tiene aportaciones.']);
        }

        DB::transaction(function () use ($request, $project, $goal, $validated, $audit): void {
            $before = $goal->auditSnapshot();
            $goal->update([...$validated, 'updated_by_user_id' => $request->user()->id]);
            $audit->handle($project, $request->user(), 'goal', $goal->id, 'updated', $before, $goal->fresh()->auditSnapshot());
        });

        return redirect()->route('savings-goals.index', $project)->with('status', 'Objetivo actualizado.');
    }

    public function archive(Request $request, Project $project, SavingsGoal $goal, RecordProjectAudit $audit): RedirectResponse
    {
        $this->authorize('manageSavingsGoals', $project);
        $this->ensureGoal($project, $goal);
        if ($goal->archived_at !== null) {
            return back();
        }

        $pausedSeries = 0;
        DB::transaction(function () use ($request, $project, $goal, $audit, &$pausedSeries): void {
            $before = $goal->auditSnapshot();
            $goal->update(['archived_at' => now(), 'updated_by_user_id' => $request->user()->id]);
            $audit->handle($project, $request->user(), 'goal', $goal->id, 'archived', $before, $goal->fresh()->auditSnapshot());

            foreach ($project->recurrenceTemplates()->where('savings_goal_id', $goal->id)->whereNull('paused_at')->whereNotNull('next_occurrence_on')->get() as $template) {
                $templateBefore = $template->auditSnapshot();
                $template->update(['paused_at' => now(), 'updated_by_user_id' => $request->user()->id]);
                $audit->handle($project, $request->user(), 'recurrence', $template->id, 'paused', $templateBefore, $template->fresh()->auditSnapshot());
                $pausedSeries++;
            }
        });

        $detail = $pausedSeries > 0 ? ' También se han pausado '.$pausedSeries.' serie(s) vinculada(s).' : '';

        return back()->with('status', 'Objetivo archivado. Su progreso y sus movimientos se conservan.'.$detail);
    }

    public function contribute(Request $request, Project $project, SavingsGoal $goal, CustomFieldValues $customFields): View
    {
        $this->authorize('contributeToSavingsGoals', $project);
        $this->ensureGoal($project, $goal);
        abort_if($goal->archived_at !== null, 404);
        $direction = GoalAllocationDirection::tryFrom((string) $request->query('direction')) ?? GoalAllocationDirection::Contribution;

        return view('movements.transfer', [
            'project' => $project,
            'movement' => null,
            'accounts' => $project->financialAccounts()->whereNull('archived_at')->orderBy('position')->get(),
            'today' => now('Europe/Madrid')->toDateString(),
            'goal' => $goal->load('account'),
            'goalDirection' => $direction,
            'tags' => $project->tags()->whereNull('archived_at')->orderBy('name')->get(),
            'customFieldDefinitions' => $customFields->definitionsForForm($project),
            'customFieldValues' => collect(),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, Project $project, ?SavingsGoal $current = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'target_amount' => ['required', 'regex:/^(?:(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?|\d+(?:\.\d{1,2})?)$/'],
            'target_date' => ['nullable', 'date'],
            'financial_account_id' => ['required', 'integer'],
        ]);
        $amount = Money::toCents($validated['target_amount']);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['target_amount' => 'El importe objetivo debe ser mayor que cero.']);
        }
        $account = $project->financialAccounts()->whereKey((int) $validated['financial_account_id'])->first();
        $allowed = [FinancialAccountType::Savings, FinancialAccountType::ExternalInvestment];
        if ($account === null || ! in_array($account->type, $allowed, true) || ($account->archived_at !== null && $account->id !== $current?->financial_account_id)) {
            throw ValidationException::withMessages(['financial_account_id' => 'El objetivo necesita una cuenta activa de ahorro o inversión externa.']);
        }

        return [
            'name' => trim($validated['name']),
            'target_amount_cents' => $amount,
            'target_date' => $validated['target_date'] ?? null,
            'financial_account_id' => $account->id,
        ];
    }

    private function eligibleAccounts(Project $project, ?int $currentId = null)
    {
        return $project->financialAccounts()
            ->whereIn('type', [FinancialAccountType::Savings->value, FinancialAccountType::ExternalInvestment->value])
            ->where(fn ($query) => $query->whereNull('archived_at')->when($currentId, fn ($q, $id) => $q->orWhereKey($id)))
            ->orderBy('position')->get();
    }

    private function ensureGoal(Project $project, SavingsGoal $goal): void
    {
        abort_unless($goal->project_id === $project->id, 404);
    }
}
