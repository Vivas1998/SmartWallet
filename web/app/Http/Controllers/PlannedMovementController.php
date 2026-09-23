<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Audit\RecordProjectAudit;
use App\Actions\Calendar\CompletePlannedMovement;
use App\Enums\CategoryType;
use App\Enums\FinancialAccountType;
use App\Enums\MovementType;
use App\Enums\PlannedMovementStatus;
use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\Movement;
use App\Models\PlannedMovement;
use App\Models\Project;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlannedMovementController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        return view('planned-movements.index', [
            'project' => $project,
            'plannedMovements' => $project->plannedMovements()
                ->with(['category', 'subcategory', 'account', 'destinationAccount', 'paidBy', 'movement', 'tags'])
                ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'completed' THEN 1 ELSE 2 END")
                ->orderBy('due_on')
                ->orderBy('id')
                ->paginate(30),
            'canManage' => $request->user()->can('managePlannedMovements', $project),
        ]);
    }

    public function create(Project $project): View
    {
        $this->authorize('managePlannedMovements', $project);

        return view('planned-movements.form', $this->formData($project));
    }

    public function store(Request $request, Project $project, RecordProjectAudit $audit): RedirectResponse
    {
        $this->authorize('managePlannedMovements', $project);
        $attributes = $this->validatedPlanAttributes($request, $project);
        $tagIds = $this->validatedTagIds($request, $project);

        DB::transaction(function () use ($request, $project, $attributes, $tagIds, $audit): void {
            $plan = PlannedMovement::create([
                'project_id' => $project->id,
                ...$attributes,
                'status' => PlannedMovementStatus::Pending,
                'created_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);
            $plan->tags()->sync($tagIds);
            $audit->handle($project, $request->user(), 'planned_movement', $plan->id, 'created', null, $plan->auditSnapshot());
        });

        return redirect()->route('planned-movements.index', $project)
            ->with('status', 'Planificación creada. No afectará a tus cuentas hasta que la registres como realizada.');
    }

    public function edit(Project $project, PlannedMovement $plannedMovement): View
    {
        $this->authorize('managePlannedMovements', $project);
        $this->ensurePlan($project, $plannedMovement);
        $this->ensurePending($plannedMovement);
        $plannedMovement->load('tags');

        return view('planned-movements.form', $this->formData($project, $plannedMovement));
    }

    public function update(
        Request $request,
        Project $project,
        PlannedMovement $plannedMovement,
        RecordProjectAudit $audit,
    ): RedirectResponse {
        $this->authorize('managePlannedMovements', $project);
        $this->ensurePlan($project, $plannedMovement);
        $this->ensurePending($plannedMovement);
        $attributes = $this->validatedPlanAttributes($request, $project, $plannedMovement);
        $tagIds = $this->validatedTagIds($request, $project, $plannedMovement);

        DB::transaction(function () use ($request, $project, $plannedMovement, $attributes, $tagIds, $audit): void {
            $plan = PlannedMovement::query()->with('tags')->lockForUpdate()->findOrFail($plannedMovement->id);
            $this->ensurePending($plan);
            $before = $plan->auditSnapshot();
            $plan->update([...$attributes, 'updated_by_user_id' => $request->user()->id]);
            $plan->tags()->sync($tagIds);
            $audit->handle($project, $request->user(), 'planned_movement', $plan->id, 'updated', $before, $plan->fresh()->auditSnapshot());
        }, 3);

        return redirect()->route('planned-movements.index', $project)
            ->with('status', 'Planificación actualizada sin modificar saldos ni presupuestos.');
    }

    public function cancel(
        Request $request,
        Project $project,
        PlannedMovement $plannedMovement,
        RecordProjectAudit $audit,
    ): RedirectResponse {
        $this->authorize('managePlannedMovements', $project);
        $this->ensurePlan($project, $plannedMovement);
        $this->ensurePending($plannedMovement);

        DB::transaction(function () use ($request, $project, $plannedMovement, $audit): void {
            $plan = PlannedMovement::query()->with('tags')->lockForUpdate()->findOrFail($plannedMovement->id);
            $this->ensurePending($plan);
            $before = $plan->auditSnapshot();
            $plan->update([
                'status' => PlannedMovementStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);
            $audit->handle($project, $request->user(), 'planned_movement', $plan->id, 'cancelled', $before, $plan->fresh()->auditSnapshot());
        }, 3);

        return back()->with('status', 'Planificación cancelada. Se conserva en el historial del calendario.');
    }

    public function complete(Request $request, Project $project, PlannedMovement $plannedMovement): View
    {
        $this->authorize('managePlannedMovements', $project);
        $this->ensurePlan($project, $plannedMovement);
        $this->ensurePending($plannedMovement);
        $plannedMovement->load('tags');

        return view('planned-movements.complete', array_merge(
            $this->formData($project, $plannedMovement),
            [
                'possibleDuplicate' => $request->session()->get('possible_duplicate'),
                'today' => CarbonImmutable::now('Europe/Madrid')->toDateString(),
            ],
        ));
    }

    public function storeCompletion(
        Request $request,
        Project $project,
        PlannedMovement $plannedMovement,
        CompletePlannedMovement $complete,
    ): RedirectResponse {
        $this->authorize('managePlannedMovements', $project);
        $this->ensurePlan($project, $plannedMovement);
        $this->ensurePending($plannedMovement);
        [$attributes, $category, $subcategory, $goal] = $this->validatedCompletion($request, $project, $plannedMovement);
        $tagIds = $this->validatedTagIds($request, $project, $plannedMovement);

        if ($category !== null) {
            $duplicate = $this->possibleDuplicate(
                $project,
                $category,
                $subcategory,
                CarbonImmutable::parse($attributes['occurred_on'], 'Europe/Madrid'),
                $attributes['amount_cents'],
            );
            if ($duplicate !== null && ! $request->boolean('allow_duplicate')) {
                return $this->duplicateResponse($duplicate);
            }
        }

        $movement = $complete->handle($plannedMovement, $request->user(), $attributes, $tagIds, $goal);

        return redirect()->route('planned-movements.index', $project)
            ->with('status', 'Planificación registrada como realizada el '.$movement->occurred_on->format('d/m/Y').'. Saldos y presupuesto actualizados.');
    }

    /** @return array<string, mixed> */
    private function validatedPlanAttributes(
        Request $request,
        Project $project,
        ?PlannedMovement $current = null,
    ): array {
        $validated = $request->validate([
            'movement_kind' => ['required', Rule::in(['expense', 'income', 'transfer'])],
            'amount' => ['required', 'regex:/^(?:(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?|\d+(?:\.\d{1,2})?)$/'],
            'due_on' => ['required', 'date'],
            'concept' => ['required', 'string', 'max:180'],
            'category_id' => ['nullable', 'integer'],
            'subcategory_id' => ['nullable', 'integer'],
            'financial_account_id' => ['required', 'integer'],
            'destination_account_id' => ['nullable', 'integer', 'different:financial_account_id'],
            'paid_by_user_id' => ['nullable', 'integer'],
            'savings_goal_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'tag_ids' => ['nullable', 'array', 'max:20'],
            'tag_ids.*' => ['integer', 'distinct'],
        ], ['amount.regex' => 'Introduce un importe positivo válido con un máximo de dos decimales.']);

        $amountCents = Money::toCents($validated['amount']);
        if ($amountCents <= 0) {
            throw ValidationException::withMessages(['amount' => 'El importe debe ser mayor que cero.']);
        }

        $dueOn = CarbonImmutable::parse($validated['due_on'], 'Europe/Madrid')->startOfDay();
        $kind = $validated['movement_kind'];
        $allowedExistingAccounts = array_values(array_filter([
            $current?->financial_account_id,
            $current?->destination_account_id,
        ]));
        $source = $this->usableAccount(
            $project,
            (int) $validated['financial_account_id'],
            $allowedExistingAccounts,
            'financial_account_id',
        );
        $destination = null;
        $category = null;
        $subcategory = null;
        $paidBy = null;
        $goal = null;

        if ($kind === 'transfer') {
            if (empty($validated['destination_account_id'])) {
                throw ValidationException::withMessages(['destination_account_id' => 'Selecciona una cuenta de destino.']);
            }
            $destination = $this->usableAccount(
                $project,
                (int) $validated['destination_account_id'],
                $allowedExistingAccounts,
                'destination_account_id',
            );
            if ($source->is($destination)) {
                throw ValidationException::withMessages(['destination_account_id' => 'Elige una cuenta de destino distinta.']);
            }
            if ($source->type === FinancialAccountType::CreditCard) {
                throw ValidationException::withMessages(['financial_account_id' => 'Una tarjeta de crédito no puede ser la cuenta de origen.']);
            }
            $type = ($source->type === FinancialAccountType::ExternalInvestment || $destination->type === FinancialAccountType::ExternalInvestment)
                ? MovementType::InvestmentContribution
                : MovementType::Transfer;
            $goal = $this->goal($project, $validated['savings_goal_id'] ?? null, $destination, $current?->savings_goal_id);
        } else {
            $type = MovementType::from($kind);
            $categoryType = $type === MovementType::Expense ? CategoryType::Expense : CategoryType::Income;
            $category = $this->mainCategory($project, (int) ($validated['category_id'] ?? 0), $categoryType, $current?->category_id);
            $subcategory = $this->subcategory($project, $validated['subcategory_id'] ?? null, $category, $current?->subcategory_id);
            $this->ensureCompatibleAccount($source, $type, $current?->financial_account_id);
            $paidBy = $this->activeMember($project, $validated['paid_by_user_id'] ?? $request->user()->id);
        }

        $this->ensureDateAfterInitial($dueOn, $source, 'due_on');
        if ($destination !== null) {
            $this->ensureDateAfterInitial($dueOn, $destination, 'due_on');
        }

        return [
            'type' => $type,
            'amount_cents' => $amountCents,
            'due_on' => $dueOn->toDateString(),
            'concept' => trim($validated['concept']),
            'category_id' => $category?->id,
            'subcategory_id' => $subcategory?->id,
            'financial_account_id' => $source->id,
            'destination_account_id' => $destination?->id,
            'paid_by_user_id' => $paidBy?->id,
            'savings_goal_id' => $goal?->id,
            'notes' => $this->nullableTrim($validated['notes'] ?? null),
        ];
    }

    /** @return array{array<string, mixed>, ?Category, ?Category, ?SavingsGoal} */
    private function validatedCompletion(
        Request $request,
        Project $project,
        PlannedMovement $plannedMovement,
    ): array {
        $validated = $request->validate([
            'amount' => ['required', 'regex:/^(?:(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?|\d+(?:\.\d{1,2})?)$/'],
            'occurred_on' => ['required', 'date'],
            'concept' => ['required', 'string', 'max:180'],
            'category_id' => ['nullable', 'integer'],
            'subcategory_id' => ['nullable', 'integer'],
            'financial_account_id' => ['required', 'integer'],
            'destination_account_id' => ['nullable', 'integer', 'different:financial_account_id'],
            'paid_by_user_id' => ['nullable', 'integer'],
            'savings_goal_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'allow_duplicate' => ['nullable', 'boolean'],
            'tag_ids' => ['nullable', 'array', 'max:20'],
            'tag_ids.*' => ['integer', 'distinct'],
        ], ['amount.regex' => 'Introduce un importe positivo válido con un máximo de dos decimales.']);

        $amountCents = Money::toCents($validated['amount']);
        if ($amountCents <= 0) {
            throw ValidationException::withMessages(['amount' => 'El importe debe ser mayor que cero.']);
        }
        $occurredOn = CarbonImmutable::parse($validated['occurred_on'], 'Europe/Madrid')->startOfDay();
        if ($occurredOn->isAfter(CarbonImmutable::now('Europe/Madrid')->startOfDay())) {
            throw ValidationException::withMessages(['occurred_on' => 'El movimiento realizado no puede tener una fecha futura.']);
        }

        $source = $this->usableAccount($project, (int) $validated['financial_account_id'], [], 'financial_account_id');
        $destination = null;
        $category = null;
        $subcategory = null;
        $paidBy = null;
        $goal = null;

        if (in_array($plannedMovement->type, [MovementType::Transfer, MovementType::InvestmentContribution], true)) {
            if (empty($validated['destination_account_id'])) {
                throw ValidationException::withMessages(['destination_account_id' => 'Selecciona una cuenta de destino.']);
            }
            $destination = $this->usableAccount($project, (int) $validated['destination_account_id'], [], 'destination_account_id');
            if ($source->is($destination)) {
                throw ValidationException::withMessages(['destination_account_id' => 'Elige una cuenta de destino distinta.']);
            }
            if ($source->type === FinancialAccountType::CreditCard) {
                throw ValidationException::withMessages(['financial_account_id' => 'Una tarjeta de crédito no puede ser la cuenta de origen.']);
            }
            $type = ($source->type === FinancialAccountType::ExternalInvestment || $destination->type === FinancialAccountType::ExternalInvestment)
                ? MovementType::InvestmentContribution
                : MovementType::Transfer;
            $goal = $this->goal($project, $validated['savings_goal_id'] ?? null, $destination);
        } else {
            $type = $plannedMovement->type;
            $categoryType = $type === MovementType::Expense ? CategoryType::Expense : CategoryType::Income;
            $category = $this->mainCategory($project, (int) ($validated['category_id'] ?? 0), $categoryType);
            $subcategory = $this->subcategory($project, $validated['subcategory_id'] ?? null, $category);
            $this->ensureCompatibleAccount($source, $type);
            $paidBy = $this->activeMember($project, $validated['paid_by_user_id'] ?? $request->user()->id);
        }

        $this->ensureDateAfterInitial($occurredOn, $source, 'occurred_on');
        if ($destination !== null) {
            $this->ensureDateAfterInitial($occurredOn, $destination, 'occurred_on');
        }

        return [[
            'type' => $type,
            'amount_cents' => $amountCents,
            'occurred_on' => $occurredOn->toDateString(),
            'concept' => trim($validated['concept']),
            'category_id' => $category?->id,
            'subcategory_id' => $subcategory?->id,
            'financial_account_id' => $source->id,
            'destination_account_id' => $destination?->id,
            'paid_by_user_id' => $paidBy?->id,
            'notes' => $this->nullableTrim($validated['notes'] ?? null),
        ], $category, $subcategory, $goal];
    }

    private function mainCategory(Project $project, int $id, CategoryType $type, ?int $currentId = null): Category
    {
        $category = $project->categories()->whereKey($id)->whereNull('parent_id')->first();
        if ($category === null || $category->type !== $type || ($category->archived_at !== null && $category->id !== $currentId)) {
            throw ValidationException::withMessages(['category_id' => 'Selecciona una categoría principal activa del tipo correcto.']);
        }

        return $category;
    }

    private function subcategory(Project $project, mixed $id, Category $parent, ?int $currentId = null): ?Category
    {
        if ($id === null || $id === '') {
            return null;
        }
        $subcategory = $project->categories()->whereKey((int) $id)->first();
        if ($subcategory === null || $subcategory->parent_id !== $parent->id || $subcategory->type !== $parent->type || ($subcategory->archived_at !== null && $subcategory->id !== $currentId)) {
            throw ValidationException::withMessages(['subcategory_id' => 'Selecciona una subcategoría activa de la categoría indicada.']);
        }

        return $subcategory;
    }

    /** @param list<int> $allowedExisting */
    private function usableAccount(Project $project, int $id, array $allowedExisting, string $field): FinancialAccount
    {
        $account = $project->financialAccounts()->whereKey($id)->first();
        if ($account === null || ($account->archived_at !== null && ! in_array($account->id, $allowedExisting, true))) {
            throw ValidationException::withMessages([$field => 'Selecciona una cuenta activa del proyecto.']);
        }

        return $account;
    }

    private function ensureCompatibleAccount(FinancialAccount $account, MovementType $type, ?int $currentId = null): void
    {
        $allowedTypes = $type === MovementType::Income
            ? [FinancialAccountType::Checking, FinancialAccountType::Savings, FinancialAccountType::Cash]
            : [FinancialAccountType::Checking, FinancialAccountType::Savings, FinancialAccountType::Cash, FinancialAccountType::CreditCard];
        if (! in_array($account->type, $allowedTypes, true) || ($account->archived_at !== null && $account->id !== $currentId)) {
            throw ValidationException::withMessages(['financial_account_id' => 'Selecciona una cuenta compatible con el movimiento.']);
        }
    }

    private function activeMember(Project $project, mixed $id): User
    {
        $member = $project->activeMembers()->where('users.id', (int) $id)->first();
        if ($member === null) {
            throw ValidationException::withMessages(['paid_by_user_id' => 'Selecciona un miembro activo del proyecto.']);
        }

        return $member;
    }

    private function goal(
        Project $project,
        mixed $id,
        FinancialAccount $destination,
        ?int $currentId = null,
    ): ?SavingsGoal {
        if ($id === null || $id === '') {
            return null;
        }
        $goal = $project->savingsGoals()->whereKey((int) $id)->first();
        if ($goal === null || ($goal->archived_at !== null && $goal->id !== $currentId) || $goal->financial_account_id !== $destination->id) {
            throw ValidationException::withMessages(['savings_goal_id' => 'El objetivo debe estar activo y vinculado a la cuenta de destino.']);
        }

        return $goal;
    }

    private function ensureDateAfterInitial(CarbonImmutable $date, FinancialAccount $account, string $field): void
    {
        if ($date->toDateString() < $account->initial_balance_date->toDateString()) {
            throw ValidationException::withMessages([
                $field => 'La fecha no puede ser anterior al saldo inicial de '.$account->name.' ('.$account->initial_balance_date->format('d/m/Y').').',
            ]);
        }
    }

    private function possibleDuplicate(
        Project $project,
        Category $category,
        ?Category $subcategory,
        CarbonImmutable $date,
        int $amountCents,
    ): ?Movement {
        return $project->movements()
            ->whereNull('trashed_at')
            ->whereIn('type', [MovementType::Expense->value, MovementType::Income->value])
            ->where('category_id', $category->id)
            ->when($subcategory === null, fn ($query) => $query->whereNull('subcategory_id'))
            ->when($subcategory !== null, fn ($query) => $query->where('subcategory_id', $subcategory->id))
            ->whereDate('occurred_on', $date->toDateString())
            ->where('amount_cents', $amountCents)
            ->latest('id')
            ->first();
    }

    private function duplicateResponse(Movement $duplicate): RedirectResponse
    {
        return back()->withInput()->with('possible_duplicate', [
            'concept' => $duplicate->concept,
            'date' => $duplicate->occurred_on->format('d/m/Y'),
            'amount' => $duplicate->formattedAmount(),
        ]);
    }

    /** @return array<string, mixed> */
    private function formData(Project $project, ?PlannedMovement $current = null): array
    {
        return [
            'project' => $project,
            'plannedMovement' => $current,
            'categories' => $project->categories()->whereNull('parent_id')
                ->where(fn ($query) => $query->whereNull('archived_at')->when($current?->category_id, fn ($q, $id) => $q->orWhereKey($id)))
                ->with(['children' => fn ($query) => $query->where(fn ($children) => $children->whereNull('archived_at')->when($current?->subcategory_id, fn ($q, $id) => $q->orWhereKey($id)))])
                ->orderBy('type')->orderBy('position')->get(),
            'accounts' => $project->financialAccounts()
                ->where(fn ($query) => $query->whereNull('archived_at')->when($current !== null, fn ($q) => $q->orWhereIn('id', array_filter([$current->financial_account_id, $current->destination_account_id]))))
                ->orderBy('position')->get(),
            'members' => $project->activeMembers()->orderBy('name')->get(),
            'goals' => $project->savingsGoals()->with('account')
                ->where(fn ($query) => $query->whereNull('archived_at')->when($current?->savings_goal_id, fn ($q, $id) => $q->orWhereKey($id)))
                ->orderBy('name')->get(),
            'tags' => $project->tags()->where(fn ($query) => $query->whereNull('archived_at')->when($current !== null, fn ($tags) => $tags->orWhereHas('plannedMovements', fn ($plans) => $plans->whereKey($current->id))))->orderBy('name')->get(),
            'today' => CarbonImmutable::now('Europe/Madrid')->toDateString(),
        ];
    }

    /** @return list<int> */
    private function validatedTagIds(Request $request, Project $project, ?PlannedMovement $current = null): array
    {
        $ids = collect($request->input('tag_ids', []))->map(fn (mixed $id): int => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }
        $existingIds = $current?->tags()->pluck('tags.id')->all() ?? [];
        $allowedCount = $project->tags()->whereKey($ids->all())
            ->where(fn ($query) => $query->whereNull('archived_at')->when($existingIds !== [], fn ($tags) => $tags->orWhereIn('id', $existingIds)))
            ->count();
        if ($allowedCount !== $ids->count()) {
            throw ValidationException::withMessages(['tag_ids' => 'Selecciona únicamente etiquetas disponibles de este proyecto.']);
        }

        return $ids->all();
    }

    private function ensurePlan(Project $project, PlannedMovement $plannedMovement): void
    {
        abort_unless($plannedMovement->project_id === $project->id, 404);
    }

    private function ensurePending(PlannedMovement $plannedMovement): void
    {
        if ($plannedMovement->status !== PlannedMovementStatus::Pending) {
            throw ValidationException::withMessages(['planned_movement' => 'Solo se pueden modificar planificaciones pendientes.']);
        }
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
