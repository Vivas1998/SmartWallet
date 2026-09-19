<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Audit\RecordProjectAudit;
use App\Actions\Budgets\ResolveMonthlyBudget;
use App\Actions\Projects\CreateProject;
use App\Actions\Reports\BuildProjectMonthSummary;
use App\Enums\FinancialAccountType;
use App\Models\AuditLog;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $projects = $request->user()
            ->projects()
            ->with(['latestAuditLog.actor'])
            ->withCount('activeMembers')
            ->orderByRaw('archived_at is not null')
            ->orderBy('name')
            ->get();

        $recentActivities = AuditLog::query()
            ->whereIn('project_id', $projects->modelKeys())
            ->with(['project:id,name,color,icon,archived_at', 'actor:id,name'])
            ->latest('created_at')
            ->latest('id')
            ->limit(10)
            ->get();

        return view('projects.index', compact('projects', 'recentActivities'));
    }

    public function create(): View
    {
        $budgetMonth = CarbonImmutable::now('Europe/Madrid')->startOfMonth();

        return view('projects.create', [
            'accountTypes' => FinancialAccountType::cases(),
            'initialDate' => CarbonImmutable::now('Europe/Madrid')->toDateString(),
            'budgetMonth' => $budgetMonth,
        ]);
    }

    public function store(Request $request, CreateProject $createProject): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['required', Rule::in(['home', 'wallet', 'personal', 'travel', 'heart'])],
            'account_name' => ['required', 'string', 'max:120'],
            'account_type' => ['required', Rule::enum(FinancialAccountType::class)],
            'initial_balance' => [
                'required',
                'regex:/^-?(?:(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?|\d+(?:\.\d{1,2})?)$/',
            ],
            'initial_balance_date' => ['required', 'date'],
            'monthly_budget' => [
                'required',
                'regex:/^(?:(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?|\d+(?:\.\d{1,2})?)$/',
            ],
        ], [
            'initial_balance.regex' => 'Introduce un importe válido con un máximo de dos decimales.',
            'monthly_budget.regex' => 'Introduce un presupuesto mensual válido y sin signo negativo.',
        ]);

        $project = $createProject->handle($request->user(), $validated);

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'Proyecto y cuenta principal creados correctamente.');
    }

    public function show(Request $request, Project $project, ResolveMonthlyBudget $resolveBudget, BuildProjectMonthSummary $monthlySummary): View
    {
        $this->authorize('view', $project);

        $month = CarbonImmutable::now('Europe/Madrid')->startOfMonth();
        $today = CarbonImmutable::now('Europe/Madrid')->toDateString();
        $budget = $project->isArchived()
            ? $resolveBudget->preview($project, $month)
            : $resolveBudget->handle($project, $month);
        $summary = $monthlySummary->handle($project, $month);

        $project->load([
            'activeMembers',
            'financialAccounts' => fn ($query) => $query
                ->withSum(['entries as entries_total' => fn ($entryQuery) => $entryQuery->whereDate('occurred_on', '<=', $today)], 'signed_amount_cents')
                ->orderByRaw('archived_at is not null')
                ->orderBy('position')
                ->orderBy('name'),
        ]);

        $recentMovements = $project->movements()
            ->with(['category', 'subcategory', 'account'])
            ->whereNull('trashed_at')
            ->latest('occurred_on')
            ->latest('id')
            ->limit(6)
            ->get();

        return view('projects.show', [
            'project' => $project,
            'month' => $month,
            'budget' => $budget,
            'expenseCents' => $summary['expense_cents'],
            'refundCents' => $summary['refund_cents'],
            'incomeCents' => $summary['income_cents'],
            'remainingCents' => (int) $budget->total_limit_cents - $summary['expense_cents'],
            'recentMovements' => $recentMovements,
            'recoveryNotices' => $project->recoveryNotices()->whereNull('dismissed_at')->latest('created_at')->get(),
        ]);
    }

    public function settings(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $project->load(['creator', 'archivedBy'])->loadCount([
            'activeMembers',
            'financialAccounts',
            'monthlyBudgets',
            'auditLogs',
        ]);

        return view('projects.settings', [
            'project' => $project,
            'activeMovementCount' => $project->movements()->whereNull('trashed_at')->count(),
            'trashCount' => $project->movements()->whereNotNull('trashed_at')->count(),
            'canUpdate' => $request->user()->can('update', $project),
            'canArchive' => $request->user()->can('archive', $project),
            'canRestore' => $request->user()->can('restore', $project),
        ]);
    }

    public function update(Request $request, Project $project, RecordProjectAudit $audit): RedirectResponse
    {
        $this->authorize('update', $project);
        $validated = $request->validate($this->identityRules());

        DB::transaction(function () use ($request, $project, $validated, $audit): void {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->id);
            abort_if($locked->isArchived(), 409, 'El proyecto ya está archivado.');
            $before = $locked->auditSnapshot();
            $locked->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?: null,
                'color' => $validated['color'],
                'icon' => $validated['icon'],
            ]);
            $audit->handle($locked, $request->user(), 'project', $locked->id, 'updated', $before, $locked->fresh()->auditSnapshot());
        });

        return redirect()->route('projects.settings', $project)->with('status', 'Datos del proyecto actualizados.');
    }

    public function archive(Request $request, Project $project, RecordProjectAudit $audit): RedirectResponse
    {
        $this->authorize('archive', $project);

        DB::transaction(function () use ($request, $project, $audit): void {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->id);
            abort_if($locked->isArchived(), 409, 'El proyecto ya está archivado.');
            $before = $locked->auditSnapshot();
            $locked->update([
                'archived_at' => now(),
                'archived_by_user_id' => $request->user()->id,
            ]);
            $audit->handle($locked, $request->user(), 'project', $locked->id, 'archived', $before, $locked->fresh()->auditSnapshot());
        });

        return redirect()->route('projects.settings', $project)->with('status', 'Proyecto archivado. Sus datos se conservan en modo de solo lectura.');
    }

    public function restore(Request $request, Project $project, RecordProjectAudit $audit): RedirectResponse
    {
        $this->authorize('restore', $project);

        DB::transaction(function () use ($request, $project, $audit): void {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->id);
            abort_unless($locked->isArchived(), 409, 'El proyecto ya está activo.');
            $before = $locked->auditSnapshot();
            $locked->update(['archived_at' => null, 'archived_by_user_id' => null]);
            $audit->handle($locked, $request->user(), 'project', $locked->id, 'reactivated', $before, $locked->fresh()->auditSnapshot());
        });

        return redirect()->route('projects.settings', $project)->with('status', 'Proyecto reactivado. Ya se pueden registrar y modificar datos.');
    }

    /** @return array<string, list<mixed>> */
    private function identityRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['required', Rule::in(['home', 'wallet', 'personal', 'travel', 'heart'])],
        ];
    }
}
