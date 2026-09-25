<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Audit\RecordProjectAudit;
use App\Actions\Budgets\BuildMonthlyClosure;
use App\Actions\CustomFields\ApplyCustomFieldFilters;
use App\Actions\Movements\RebuildMovementEntries;
use App\Actions\SavingsGoals\SyncGoalAllocation;
use App\Enums\CategoryType;
use App\Enums\FinancialAccountType;
use App\Enums\GoalAllocationDirection;
use App\Enums\MovementType;
use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\Movement;
use App\Models\Project;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Support\CustomFieldValues;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MovementController extends Controller
{
    public function index(Request $request, Project $project, ApplyCustomFieldFilters $customFieldFilters): View
    {
        $this->authorize('view', $project);
        $request->validate([
            'from' => ['nullable', 'date', 'required_with:to'],
            'to' => ['nullable', 'date', 'required_with:from', 'after_or_equal:from'],
        ]);
        $month = $this->monthFrom($request->query('month'));
        $customRange = $request->filled('from') && $request->filled('to');
        $rangeStart = $customRange ? CarbonImmutable::parse($request->query('from'), 'Europe/Madrid')->startOfDay() : $month->startOfMonth();
        $rangeEnd = $customRange ? CarbonImmutable::parse($request->query('to'), 'Europe/Madrid')->startOfDay() : $month->endOfMonth();
        $typeValues = array_map(fn (MovementType $type): string => $type->value, MovementType::cases());

        $movementQuery = $project->movements()->getQuery()
            ->with(['category', 'subcategory', 'account', 'destinationAccount', 'paidBy', 'originalMovement', 'plannedMovement', 'tags', 'customFieldValues.definition'])
            ->withSum(['refunds as refunded_cents' => fn ($query) => $query->whereNull('trashed_at')], 'amount_cents')
            ->whereNull('trashed_at')
            ->whereBetween('occurred_on', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->when(in_array($request->query('type'), $typeValues, true), fn ($query) => $query->where('type', $request->query('type')))
            ->when($request->filled('account'), fn ($query) => $query->where(fn ($accounts) => $accounts
                ->where('financial_account_id', $request->integer('account'))
                ->orWhere('destination_account_id', $request->integer('account'))))
            ->when($request->filled('category'), fn ($query) => $query->where('category_id', $request->integer('category')))
            ->when($request->filled('member'), fn ($query) => $query->where('paid_by_user_id', $request->integer('member')))
            ->when($request->filled('tag'), fn ($query) => $query->whereHas('tags', fn ($tags) => $tags->whereKey($request->integer('tag'))))
            ->when($request->filled('search'), fn ($query) => $query->where('concept', 'like', '%'.trim((string) $request->query('search')).'%'));
        $customFieldFilters->handle($request, $project, $movementQuery);
        $movements = $movementQuery->latest('occurred_on')->latest('id')->paginate(30)->withQueryString();

        return view('movements.index', [
            'project' => $project,
            'month' => $month,
            'customRange' => $customRange,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'movements' => $movements,
            'categories' => $project->categories()->whereNull('parent_id')->orderByRaw('archived_at is not null')->orderBy('position')->get(),
            'accounts' => $project->financialAccounts()->orderByRaw('archived_at is not null')->orderBy('position')->get(),
            'members' => $project->activeMembers()->orderBy('name')->get(),
            'tags' => $project->tags()->orderByRaw('archived_at is not null')->orderBy('name')->get(),
            'customFieldDefinitions' => $project->customFieldDefinitions()->orderByRaw('archived_at is not null')->orderBy('position')->get(),
        ]);
    }

    public function create(Request $request, Project $project): View
    {
        $this->authorize('recordMovements', $project);

        return view('movements.create', array_merge([
            'project' => $project,
            'today' => CarbonImmutable::now('Europe/Madrid')->toDateString(),
            'possibleDuplicate' => $request->session()->get('possible_duplicate'),
        ], $this->selectorData($project)));
    }

    public function store(Request $request, Project $project, RebuildMovementEntries $entries, RecordProjectAudit $audit, CustomFieldValues $customFields): RedirectResponse
    {
        $this->authorize('recordMovements', $project);
        [$validated, $type, $amountCents, $occurredOn, $category, $subcategory, $account, $paidBy] = $this->validatedStandardMovement($request, $project);
        $tagIds = $this->validatedTagIds($request, $project);
        $customValues = $customFields->validate($request, $project, $type);
        $duplicate = $this->possibleDuplicate($project, $category, $subcategory, $occurredOn, $amountCents);

        if ($duplicate !== null && ! $request->boolean('allow_duplicate')) {
            return $this->duplicateResponse($duplicate);
        }

        DB::transaction(function () use ($project, $request, $validated, $type, $amountCents, $occurredOn, $category, $subcategory, $account, $paidBy, $tagIds, $customValues, $entries, $audit, $customFields): void {
            $movement = Movement::create([
                'project_id' => $project->id,
                'type' => $type,
                'amount_cents' => $amountCents,
                'occurred_on' => $occurredOn->toDateString(),
                'concept' => trim($validated['concept']),
                'category_id' => $category->id,
                'subcategory_id' => $subcategory?->id,
                'financial_account_id' => $account->id,
                'paid_by_user_id' => $paidBy->id,
                'notes' => $this->nullableTrim($validated['notes'] ?? null),
                'show_in_calendar' => $request->boolean('show_in_calendar'),
                'created_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);
            $movement->tags()->sync($tagIds);
            $customFields->sync($movement, $customValues);
            $entries->handle($movement);
            $audit->handle($project, $request->user(), 'movement', $movement->id, 'created', null, $movement->auditSnapshot());
        });

        return redirect()->route('movements.index', ['project' => $project, 'month' => $occurredOn->format('Y-m')])
            ->with('status', $type === MovementType::Expense ? 'Gasto registrado correctamente.' : 'Ingreso registrado correctamente.');
    }

    public function createTransfer(Project $project, CustomFieldValues $customFields): View
    {
        $this->authorize('recordMovements', $project);

        return view('movements.transfer', [
            'project' => $project,
            'movement' => null,
            'accounts' => $project->financialAccounts()->whereNull('archived_at')->orderBy('position')->get(),
            'today' => CarbonImmutable::now('Europe/Madrid')->toDateString(),
            'goal' => null,
            'goalDirection' => null,
            'tags' => $project->tags()->whereNull('archived_at')->orderBy('name')->get(),
            'customFieldDefinitions' => $customFields->definitionsForForm($project),
            'customFieldValues' => collect(),
        ]);
    }

    public function storeTransfer(Request $request, Project $project, RebuildMovementEntries $entries, SyncGoalAllocation $goalAllocation, RecordProjectAudit $audit, CustomFieldValues $customFields): RedirectResponse
    {
        $this->authorize('recordMovements', $project);
        [$validated, $amountCents, $occurredOn, $source, $destination, $type, $goal, $goalDirection] = $this->validatedTransfer($request, $project);
        $tagIds = $this->validatedTagIds($request, $project);
        $customValues = $customFields->validate($request, $project, $type);

        DB::transaction(function () use ($request, $project, $validated, $amountCents, $occurredOn, $source, $destination, $type, $goal, $goalDirection, $tagIds, $customValues, $entries, $goalAllocation, $audit, $customFields): void {
            $movement = Movement::create([
                'project_id' => $project->id,
                'type' => $type,
                'amount_cents' => $amountCents,
                'occurred_on' => $occurredOn->toDateString(),
                'concept' => trim($validated['concept']),
                'financial_account_id' => $source->id,
                'destination_account_id' => $destination->id,
                'notes' => $this->nullableTrim($validated['notes'] ?? null),
                'show_in_calendar' => $request->boolean('show_in_calendar'),
                'created_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);
            $movement->tags()->sync($tagIds);
            $customFields->sync($movement, $customValues);
            $entries->handle($movement);
            $goalAllocation->handle($movement, $goal, $goalDirection);
            $audit->handle($project, $request->user(), 'movement', $movement->id, 'created', null, $movement->auditSnapshot());
        });

        return redirect()->route('movements.index', ['project' => $project, 'month' => $occurredOn->format('Y-m')])
            ->with('status', $type === MovementType::InvestmentContribution ? 'Movimiento de inversión registrado sin alterar el presupuesto.' : 'Transferencia registrada sin contar como ingreso ni gasto.');
    }

    public function createRefund(Project $project, Movement $movement, CustomFieldValues $customFields): View
    {
        $this->authorize('recordMovements', $project);
        $this->ensureMovement($project, $movement);
        $this->ensureRefundableExpense($movement);

        return view('movements.refund', [
            'project' => $project,
            'movement' => null,
            'original' => $movement->load(['account', 'category', 'subcategory', 'tags']),
            'refundedCents' => (int) $movement->refunds()->whereNull('trashed_at')->sum('amount_cents'),
            'today' => CarbonImmutable::now('Europe/Madrid')->toDateString(),
            'tags' => $project->tags()->whereNull('archived_at')->orderBy('name')->get(),
            'customFieldDefinitions' => $customFields->definitionsForForm($project),
            'customFieldValues' => collect(),
        ]);
    }

    public function storeRefund(Request $request, Project $project, Movement $movement, RebuildMovementEntries $entries, RecordProjectAudit $audit, CustomFieldValues $customFields): RedirectResponse
    {
        $this->authorize('recordMovements', $project);
        $this->ensureMovement($project, $movement);
        $this->ensureRefundableExpense($movement);
        [$validated, $amountCents, $occurredOn] = $this->validatedRefund($request, $movement);
        $tagIds = $this->validatedTagIds($request, $project);
        $customValues = $customFields->validate($request, $project, MovementType::Refund);

        DB::transaction(function () use ($request, $project, $movement, $validated, $amountCents, $occurredOn, $tagIds, $customValues, $entries, $audit, $customFields): void {
            $refund = Movement::create([
                'project_id' => $project->id,
                'type' => MovementType::Refund,
                'amount_cents' => $amountCents,
                'occurred_on' => $occurredOn->toDateString(),
                'concept' => trim($validated['concept']),
                'category_id' => $movement->category_id,
                'subcategory_id' => $movement->subcategory_id,
                'financial_account_id' => $movement->financial_account_id,
                'paid_by_user_id' => $movement->paid_by_user_id,
                'original_movement_id' => $movement->id,
                'notes' => $this->nullableTrim($validated['notes'] ?? null),
                'show_in_calendar' => $request->boolean('show_in_calendar'),
                'created_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);
            $refund->tags()->sync($tagIds);
            $customFields->sync($refund, $customValues);
            $entries->handle($refund);
            $audit->handle($project, $request->user(), 'movement', $refund->id, 'created', null, $refund->auditSnapshot());
        });

        return redirect()->route('movements.index', ['project' => $project, 'month' => $occurredOn->format('Y-m')])
            ->with('status', 'Devolución registrada. El gasto neto y el presupuesto se han actualizado.');
    }

    public function edit(Request $request, Project $project, Movement $movement, CustomFieldValues $customFields): View
    {
        $this->authorize('recordMovements', $project);
        $this->ensureActiveMovement($project, $movement);

        if (in_array($movement->type, [MovementType::Transfer, MovementType::InvestmentContribution], true)) {
            $movement->load(['goalAllocation.savingsGoal.account', 'leftoverAllocation', 'tags']);

            return view('movements.transfer', [
                'project' => $project,
                'movement' => $movement,
                'accounts' => $project->financialAccounts()->where(fn ($query) => $query->whereNull('archived_at')->orWhereIn('id', [$movement->financial_account_id, $movement->destination_account_id]))->orderBy('position')->get(),
                'today' => CarbonImmutable::now('Europe/Madrid')->toDateString(),
                'goal' => $movement->goalAllocation?->savingsGoal,
                'goalDirection' => $movement->goalAllocation?->direction,
                'tags' => $project->tags()->where(fn ($query) => $query->whereNull('archived_at')->orWhereHas('movements', fn ($movements) => $movements->whereKey($movement->id)))->orderBy('name')->get(),
                'customFieldDefinitions' => $customFields->definitionsForForm($project, $movement),
                'customFieldValues' => $movement->customFieldValues()->with('definition')->get(),
            ]);
        }
        if ($movement->type === MovementType::Refund) {
            return view('movements.refund', [
                'project' => $project,
                'movement' => $movement,
                'original' => $movement->originalMovement()->with(['account', 'category', 'subcategory'])->firstOrFail(),
                'refundedCents' => 0,
                'today' => CarbonImmutable::now('Europe/Madrid')->toDateString(),
                'tags' => $project->tags()->where(fn ($query) => $query->whereNull('archived_at')->orWhereHas('movements', fn ($movements) => $movements->whereKey($movement->id)))->orderBy('name')->get(),
                'customFieldDefinitions' => $customFields->definitionsForForm($project, $movement),
                'customFieldValues' => $movement->customFieldValues()->with('definition')->get(),
            ]);
        }

        return view('movements.edit', array_merge([
            'project' => $project,
            'movement' => $movement,
            'today' => CarbonImmutable::now('Europe/Madrid')->toDateString(),
            'possibleDuplicate' => $request->session()->get('possible_duplicate'),
        ], $this->selectorData($project, $movement, $customFields)));
    }

    public function update(Request $request, Project $project, Movement $movement, RebuildMovementEntries $entries, SyncGoalAllocation $goalAllocation, RecordProjectAudit $audit, BuildMonthlyClosure $closures, CustomFieldValues $customFields): RedirectResponse
    {
        $this->authorize('recordMovements', $project);
        $this->ensureActiveMovement($project, $movement);

        if (in_array($movement->type, [MovementType::Transfer, MovementType::InvestmentContribution], true)) {
            return $this->updateTransfer($request, $project, $movement, $entries, $goalAllocation, $audit, $closures, $customFields);
        }
        if ($movement->type === MovementType::Refund) {
            return $this->updateRefund($request, $project, $movement, $entries, $audit, $customFields);
        }

        [$validated, $type, $amountCents, $occurredOn, $category, $subcategory, $account, $paidBy] = $this->validatedStandardMovement($request, $project, $movement);
        $tagIds = $this->validatedTagIds($request, $project, $movement);
        $customValues = $customFields->validate($request, $project, $type);
        $duplicate = $this->possibleDuplicate($project, $category, $subcategory, $occurredOn, $amountCents, $movement);
        if ($duplicate !== null && ! $request->boolean('allow_duplicate')) {
            return $this->duplicateResponse($duplicate);
        }

        $refundedCents = (int) $movement->refunds()->whereNull('trashed_at')->sum('amount_cents');
        if ($refundedCents > $amountCents) {
            throw ValidationException::withMessages(['amount' => 'El gasto no puede quedar por debajo de sus devoluciones activas ('.number_format($refundedCents / 100, 2, ',', '.').' €).']);
        }
        $firstRefundDate = $movement->refunds()->whereNull('trashed_at')->min('occurred_on');
        if ($firstRefundDate !== null && $occurredOn->toDateString() > $firstRefundDate) {
            throw ValidationException::withMessages(['occurred_on' => 'La fecha del gasto no puede ser posterior a una devolución vinculada.']);
        }

        DB::transaction(function () use ($request, $project, $movement, $validated, $type, $amountCents, $occurredOn, $category, $subcategory, $account, $paidBy, $tagIds, $customValues, $entries, $audit, $customFields): void {
            $before = $movement->auditSnapshot();
            $movement->update([
                'type' => $type,
                'amount_cents' => $amountCents,
                'occurred_on' => $occurredOn->toDateString(),
                'concept' => trim($validated['concept']),
                'category_id' => $category->id,
                'subcategory_id' => $subcategory?->id,
                'financial_account_id' => $account->id,
                'paid_by_user_id' => $paidBy->id,
                'notes' => $this->nullableTrim($validated['notes'] ?? null),
                'show_in_calendar' => $request->boolean('show_in_calendar'),
                'updated_by_user_id' => $request->user()->id,
            ]);
            $movement->tags()->sync($tagIds);
            $customFields->sync($movement, $customValues);
            $entries->handle($movement->fresh());
            $audit->handle($project, $request->user(), 'movement', $movement->id, 'updated', $before, $movement->fresh()->auditSnapshot());

            foreach ($movement->refunds()->whereNull('trashed_at')->get() as $refund) {
                $refundBefore = $refund->auditSnapshot();
                $refund->update([
                    'category_id' => $category->id,
                    'subcategory_id' => $subcategory?->id,
                    'financial_account_id' => $account->id,
                    'paid_by_user_id' => $paidBy->id,
                    'updated_by_user_id' => $request->user()->id,
                ]);
                $entries->handle($refund->fresh());
                $audit->handle($project, $request->user(), 'movement', $refund->id, 'updated_from_original', $refundBefore, $refund->fresh()->auditSnapshot());
            }
        });

        return redirect()->route('movements.index', ['project' => $project, 'month' => $occurredOn->format('Y-m')])->with('status', 'Movimiento actualizado. Saldos y presupuesto recalculados.');
    }

    public function destroy(Request $request, Project $project, Movement $movement, RebuildMovementEntries $entries, RecordProjectAudit $audit): RedirectResponse
    {
        $this->authorize('recordMovements', $project);
        $this->ensureActiveMovement($project, $movement);
        if ($movement->refunds()->whereNull('trashed_at')->exists()) {
            throw ValidationException::withMessages(['movement' => 'Envía primero a la papelera las devoluciones vinculadas a este gasto.']);
        }

        DB::transaction(function () use ($request, $project, $movement, $entries, $audit): void {
            $before = $movement->auditSnapshot();
            $movement->update([
                'trashed_at' => now(),
                'purge_at' => now()->addDays(30),
                'deleted_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);
            $entries->handle($movement->fresh());
            $audit->handle($project, $request->user(), 'movement', $movement->id, 'trashed', $before, $movement->fresh()->auditSnapshot());
        });

        return back()->with('status', 'Movimiento enviado a la papelera durante 30 días.');
    }

    public function trash(Project $project): View
    {
        $this->authorize('view', $project);

        return view('movements.trash', [
            'project' => $project,
            'movements' => $project->movements()->with(['account', 'destinationAccount', 'category', 'deletedBy', 'tags'])
                ->whereNotNull('trashed_at')->orderBy('purge_at')->paginate(30),
        ]);
    }

    public function restore(Request $request, Project $project, Movement $movement, RebuildMovementEntries $entries, RecordProjectAudit $audit): RedirectResponse
    {
        $this->authorize('recordMovements', $project);
        $this->ensureMovement($project, $movement);
        if ($movement->trashed_at === null) {
            return back();
        }
        if ($movement->purge_at === null || $movement->purge_at->isPast()) {
            throw ValidationException::withMessages(['movement' => 'El plazo de restauración de este movimiento ya ha terminado.']);
        }
        if ($movement->type === MovementType::Refund && ($movement->originalMovement === null || $movement->originalMovement->trashed_at !== null)) {
            throw ValidationException::withMessages(['movement' => 'Restaura primero el gasto original de esta devolución.']);
        }

        DB::transaction(function () use ($request, $project, $movement, $entries, $audit): void {
            $before = $movement->auditSnapshot();
            $movement->update([
                'trashed_at' => null,
                'purge_at' => null,
                'restored_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);
            $entries->handle($movement->fresh());
            $audit->handle($project, $request->user(), 'movement', $movement->id, 'restored', $before, $movement->fresh()->auditSnapshot());
        });

        return back()->with('status', 'Movimiento restaurado y saldos recalculados.');
    }

    private function updateTransfer(Request $request, Project $project, Movement $movement, RebuildMovementEntries $entries, SyncGoalAllocation $goalAllocation, RecordProjectAudit $audit, BuildMonthlyClosure $closures, CustomFieldValues $customFields): RedirectResponse
    {
        [$validated, $amountCents, $occurredOn, $source, $destination, $type, $goal, $goalDirection] = $this->validatedTransfer($request, $project, $movement, $closures);
        $leftoverMonth = $movement->leftoverAllocation?->budget_month;
        $tagIds = $this->validatedTagIds($request, $project, $movement);
        $customValues = $customFields->validate($request, $project, $type);
        DB::transaction(function () use ($request, $project, $movement, $validated, $amountCents, $occurredOn, $source, $destination, $type, $goal, $goalDirection, $tagIds, $customValues, $entries, $goalAllocation, $audit, $customFields): void {
            $before = $movement->auditSnapshot();
            $movement->update([
                'type' => $type,
                'amount_cents' => $amountCents,
                'occurred_on' => $occurredOn->toDateString(),
                'concept' => trim($validated['concept']),
                'financial_account_id' => $source->id,
                'destination_account_id' => $destination->id,
                'notes' => $this->nullableTrim($validated['notes'] ?? null),
                'show_in_calendar' => $request->boolean('show_in_calendar'),
                'updated_by_user_id' => $request->user()->id,
            ]);
            $movement->tags()->sync($tagIds);
            $customFields->sync($movement, $customValues);
            $entries->handle($movement->fresh());
            $goalAllocation->handle($movement, $goal, $goalDirection);
            $audit->handle($project, $request->user(), 'movement', $movement->id, 'updated', $before, $movement->fresh()->auditSnapshot());
        });

        if ($leftoverMonth !== null) {
            return redirect()->route('budgets.closure', ['project' => $project, 'month' => $leftoverMonth->format('Y-m')])
                ->with('status', 'Asignación del sobrante actualizada y saldos recalculados.');
        }

        return redirect()->route('movements.index', ['project' => $project, 'month' => $occurredOn->format('Y-m')])->with('status', 'Transferencia actualizada y ambos saldos recalculados.');
    }

    private function updateRefund(Request $request, Project $project, Movement $movement, RebuildMovementEntries $entries, RecordProjectAudit $audit, CustomFieldValues $customFields): RedirectResponse
    {
        $original = $movement->originalMovement;
        abort_if($original === null, 404);
        [$validated, $amountCents, $occurredOn] = $this->validatedRefund($request, $original, $movement);
        $tagIds = $this->validatedTagIds($request, $project, $movement);
        $customValues = $customFields->validate($request, $project, MovementType::Refund);

        DB::transaction(function () use ($request, $project, $movement, $validated, $amountCents, $occurredOn, $tagIds, $customValues, $entries, $audit, $customFields): void {
            $before = $movement->auditSnapshot();
            $movement->update([
                'amount_cents' => $amountCents,
                'occurred_on' => $occurredOn->toDateString(),
                'concept' => trim($validated['concept']),
                'notes' => $this->nullableTrim($validated['notes'] ?? null),
                'show_in_calendar' => $request->boolean('show_in_calendar'),
                'updated_by_user_id' => $request->user()->id,
            ]);
            $movement->tags()->sync($tagIds);
            $customFields->sync($movement, $customValues);
            $entries->handle($movement->fresh());
            $audit->handle($project, $request->user(), 'movement', $movement->id, 'updated', $before, $movement->fresh()->auditSnapshot());
        });

        return redirect()->route('movements.index', ['project' => $project, 'month' => $occurredOn->format('Y-m')])->with('status', 'Devolución actualizada y gasto neto recalculado.');
    }

    /** @return array{array<string,mixed>, MovementType, int, CarbonImmutable, Category, ?Category, FinancialAccount, User} */
    private function validatedStandardMovement(Request $request, Project $project, ?Movement $current = null): array
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in([MovementType::Expense->value, MovementType::Income->value])],
            'amount' => ['required', 'regex:/^(?:(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?|\d+(?:\.\d{1,2})?)$/'],
            'occurred_on' => ['required', 'date'],
            'concept' => ['required', 'string', 'max:180'],
            'category_id' => ['required', 'integer'],
            'subcategory_id' => ['nullable', 'integer'],
            'financial_account_id' => ['required', 'integer'],
            'paid_by_user_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'show_in_calendar' => ['nullable', 'boolean'],
            'allow_duplicate' => ['nullable', 'boolean'],
            'tag_ids' => ['nullable', 'array', 'max:20'],
            'tag_ids.*' => ['integer', 'distinct'],
        ], ['amount.regex' => 'Introduce un importe positivo válido con un máximo de dos decimales.']);

        $type = MovementType::from($validated['type']);
        if ($current !== null && $type !== $current->type) {
            throw ValidationException::withMessages(['type' => 'Para cambiar entre gasto e ingreso crea un movimiento nuevo.']);
        }
        $amountCents = Money::toCents($validated['amount']);
        $occurredOn = $this->pastOrToday($validated['occurred_on']);
        if ($amountCents <= 0) {
            throw ValidationException::withMessages(['amount' => 'El importe debe ser mayor que cero.']);
        }

        $categoryType = $type === MovementType::Expense ? CategoryType::Expense : CategoryType::Income;
        $category = $this->mainCategory($project, (int) $validated['category_id'], $categoryType, $current?->category_id);
        $subcategory = $this->subcategory($project, $validated['subcategory_id'] ?? null, $category, $current?->subcategory_id);
        $account = $this->account($project, (int) $validated['financial_account_id'], $type, $current?->financial_account_id);
        $paidBy = $this->activeMember($project, $validated['paid_by_user_id'] ?? $request->user()->id);
        $this->ensureDateAfterInitial($occurredOn, $account);

        return [$validated, $type, $amountCents, $occurredOn, $category, $subcategory, $account, $paidBy];
    }

    /** @return array{array<string,mixed>, int, CarbonImmutable, FinancialAccount, FinancialAccount, MovementType, ?SavingsGoal, ?GoalAllocationDirection} */
    private function validatedTransfer(Request $request, Project $project, ?Movement $current = null, ?BuildMonthlyClosure $closures = null): array
    {
        $validated = $request->validate([
            'amount' => ['required', 'regex:/^(?:(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?|\d+(?:\.\d{1,2})?)$/'],
            'occurred_on' => ['required', 'date'],
            'concept' => ['required', 'string', 'max:180'],
            'financial_account_id' => ['required', 'integer'],
            'destination_account_id' => ['required', 'integer', 'different:financial_account_id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'show_in_calendar' => ['nullable', 'boolean'],
            'savings_goal_id' => ['nullable', 'integer'],
            'goal_direction' => ['nullable', Rule::enum(GoalAllocationDirection::class)],
            'tag_ids' => ['nullable', 'array', 'max:20'],
            'tag_ids.*' => ['integer', 'distinct'],
        ], ['destination_account_id.different' => 'Elige una cuenta de destino distinta.']);
        $amountCents = Money::toCents($validated['amount']);
        if ($amountCents <= 0) {
            throw ValidationException::withMessages(['amount' => 'El importe debe ser mayor que cero.']);
        }
        $occurredOn = $this->pastOrToday($validated['occurred_on']);
        $allowedExisting = array_values(array_filter([$current?->financial_account_id, $current?->destination_account_id]));
        $source = $this->transferAccount($project, (int) $validated['financial_account_id'], $allowedExisting, 'financial_account_id');
        $destination = $this->transferAccount($project, (int) $validated['destination_account_id'], $allowedExisting, 'destination_account_id');
        if ($source->type === FinancialAccountType::CreditCard) {
            throw ValidationException::withMessages(['financial_account_id' => 'Una tarjeta de crédito no puede ser cuenta de origen. Págala eligiéndola como destino.']);
        }
        $this->ensureDateAfterInitial($occurredOn, $source);
        $this->ensureDateAfterInitial($occurredOn, $destination, 'destination_account_id');
        $type = ($source->type === FinancialAccountType::ExternalInvestment || $destination->type === FinancialAccountType::ExternalInvestment)
            ? MovementType::InvestmentContribution
            : MovementType::Transfer;

        $goal = null;
        $goalDirection = null;
        if (! empty($validated['savings_goal_id'])) {
            $goal = $project->savingsGoals()->whereKey((int) $validated['savings_goal_id'])->first();
            $existingGoalId = $current?->goalAllocation?->savings_goal_id;
            if ($goal === null || ($goal->archived_at !== null && $goal->id !== $existingGoalId)) {
                throw ValidationException::withMessages(['savings_goal_id' => 'Selecciona un objetivo de ahorro activo del proyecto.']);
            }
            $goalDirection = GoalAllocationDirection::tryFrom((string) ($validated['goal_direction'] ?? ''));
            if ($goalDirection === null) {
                throw ValidationException::withMessages(['goal_direction' => 'Indica si es una aportación o una retirada.']);
            }
            if ($goalDirection === GoalAllocationDirection::Contribution && $destination->id !== $goal->financial_account_id) {
                throw ValidationException::withMessages(['destination_account_id' => 'La aportación debe llegar a la cuenta vinculada al objetivo.']);
            }
            if ($goalDirection === GoalAllocationDirection::Withdrawal && $source->id !== $goal->financial_account_id) {
                throw ValidationException::withMessages(['financial_account_id' => 'La retirada debe salir de la cuenta vinculada al objetivo.']);
            }
        }

        $leftover = $current?->leftoverAllocation;
        if ($leftover !== null) {
            if (! in_array($source->type, [FinancialAccountType::Checking, FinancialAccountType::Savings, FinancialAccountType::Cash], true)) {
                throw ValidationException::withMessages(['financial_account_id' => 'El sobrante debe salir de una cuenta corriente, de ahorro o de efectivo.']);
            }
            if (! in_array($destination->type, [FinancialAccountType::Savings, FinancialAccountType::ExternalInvestment], true)) {
                throw ValidationException::withMessages(['destination_account_id' => 'El sobrante solo puede destinarse a ahorro o inversión externa.']);
            }
            if ($occurredOn->isBefore($leftover->budget_month->endOfMonth()->startOfDay())) {
                throw ValidationException::withMessages(['occurred_on' => 'La transferencia no puede ser anterior al final del mes cerrado.']);
            }
            $closure = ($closures ?? app(BuildMonthlyClosure::class))->handle($project, $leftover->budget_month->toImmutable(), $current->id);
            if ($amountCents > $closure['available_to_allocate_cents']) {
                throw ValidationException::withMessages(['amount' => 'El importe supera el sobrante disponible para este cierre.']);
            }
            $currentEntryCents = (int) ($current->entries()->where('financial_account_id', $source->id)->value('signed_amount_cents') ?? 0);
            $sourceBalanceBeforeTransfer = $source->currentBalanceCents() - $currentEntryCents;
            if ($sourceBalanceBeforeTransfer < $amountCents) {
                throw ValidationException::withMessages(['financial_account_id' => 'La cuenta de origen no tiene saldo suficiente para esta transferencia.']);
            }
        }

        return [$validated, $amountCents, $occurredOn, $source, $destination, $type, $goal, $goalDirection];
    }

    /** @return array{array<string,mixed>, int, CarbonImmutable} */
    private function validatedRefund(Request $request, Movement $original, ?Movement $current = null): array
    {
        $validated = $request->validate([
            'amount' => ['required', 'regex:/^(?:(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?|\d+(?:\.\d{1,2})?)$/'],
            'occurred_on' => ['required', 'date'],
            'concept' => ['required', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'show_in_calendar' => ['nullable', 'boolean'],
            'tag_ids' => ['nullable', 'array', 'max:20'],
            'tag_ids.*' => ['integer', 'distinct'],
        ]);
        $amountCents = Money::toCents($validated['amount']);
        $occurredOn = $this->pastOrToday($validated['occurred_on']);
        if ($amountCents <= 0) {
            throw ValidationException::withMessages(['amount' => 'El importe debe ser mayor que cero.']);
        }
        if ($occurredOn->toDateString() < $original->occurred_on->toDateString()) {
            throw ValidationException::withMessages(['occurred_on' => 'La devolución no puede ser anterior al gasto original.']);
        }
        $otherRefunded = (int) $original->refunds()->whereNull('trashed_at')->when($current !== null, fn ($query) => $query->whereKeyNot($current->id))->sum('amount_cents');
        if ($otherRefunded + $amountCents > $original->amount_cents) {
            throw ValidationException::withMessages(['amount' => 'Las devoluciones no pueden superar el importe pendiente del gasto.']);
        }

        return [$validated, $amountCents, $occurredOn];
    }

    private function mainCategory(Project $project, int $categoryId, CategoryType $type, ?int $currentId = null): Category
    {
        $category = $project->categories()->whereKey($categoryId)->whereNull('parent_id')->first();
        if ($category === null || $category->type !== $type || ($category->archived_at !== null && $category->id !== $currentId)) {
            throw ValidationException::withMessages(['category_id' => 'Selecciona una categoría principal activa del tipo correcto.']);
        }

        return $category;
    }

    private function subcategory(Project $project, mixed $subcategoryId, Category $parent, ?int $currentId = null): ?Category
    {
        if ($subcategoryId === null || $subcategoryId === '') {
            return null;
        }
        $subcategory = $project->categories()->whereKey((int) $subcategoryId)->first();
        if ($subcategory === null || $subcategory->parent_id !== $parent->id || $subcategory->type !== $parent->type || ($subcategory->archived_at !== null && $subcategory->id !== $currentId)) {
            throw ValidationException::withMessages(['subcategory_id' => 'Selecciona una subcategoría activa de la categoría indicada.']);
        }

        return $subcategory;
    }

    private function account(Project $project, int $accountId, MovementType $type, ?int $currentId = null): FinancialAccount
    {
        $account = $project->financialAccounts()->whereKey($accountId)->first();
        $allowedTypes = $type === MovementType::Income
            ? [FinancialAccountType::Checking, FinancialAccountType::Savings, FinancialAccountType::Cash]
            : [FinancialAccountType::Checking, FinancialAccountType::Savings, FinancialAccountType::Cash, FinancialAccountType::CreditCard];
        if ($account === null || ! in_array($account->type, $allowedTypes, true) || ($account->archived_at !== null && $account->id !== $currentId)) {
            throw ValidationException::withMessages(['financial_account_id' => 'Selecciona una cuenta activa compatible con el movimiento.']);
        }

        return $account;
    }

    /** @param list<int> $allowedExisting */
    private function transferAccount(Project $project, int $accountId, array $allowedExisting, string $field): FinancialAccount
    {
        $account = $project->financialAccounts()->whereKey($accountId)->first();
        if ($account === null || ($account->archived_at !== null && ! in_array($account->id, $allowedExisting, true))) {
            throw ValidationException::withMessages([$field => 'Selecciona una cuenta activa del proyecto.']);
        }

        return $account;
    }

    private function activeMember(Project $project, mixed $userId): User
    {
        $member = $project->activeMembers()->where('users.id', (int) $userId)->first();
        if ($member === null) {
            throw ValidationException::withMessages(['paid_by_user_id' => 'Selecciona un miembro activo del proyecto.']);
        }

        return $member;
    }

    private function possibleDuplicate(Project $project, Category $category, ?Category $subcategory, CarbonImmutable $date, int $amountCents, ?Movement $current = null): ?Movement
    {
        return $project->movements()->whereNull('trashed_at')->whereIn('type', [MovementType::Expense->value, MovementType::Income->value])
            ->where('category_id', $category->id)
            ->when($subcategory === null, fn ($query) => $query->whereNull('subcategory_id'))
            ->when($subcategory !== null, fn ($query) => $query->where('subcategory_id', $subcategory->id))
            ->whereDate('occurred_on', $date->toDateString())->where('amount_cents', $amountCents)
            ->when($current !== null, fn ($query) => $query->whereKeyNot($current->id))->latest('id')->first();
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
    private function selectorData(Project $project, ?Movement $current = null, ?CustomFieldValues $customFields = null): array
    {
        return [
            'categories' => $project->categories()->whereNull('parent_id')
                ->where(fn ($query) => $query->whereNull('archived_at')->when($current?->category_id, fn ($q, $id) => $q->orWhereKey($id)))
                ->with(['children' => fn ($query) => $query->where(fn ($children) => $children->whereNull('archived_at')->when($current?->subcategory_id, fn ($q, $id) => $q->orWhereKey($id)))])
                ->orderBy('type')->orderBy('position')->get(),
            'accounts' => $project->financialAccounts()->where('type', '!=', FinancialAccountType::ExternalInvestment->value)
                ->where(fn ($query) => $query->whereNull('archived_at')->when($current?->financial_account_id, fn ($q, $id) => $q->orWhereKey($id)))
                ->orderBy('position')->get(),
            'members' => $project->activeMembers()->orderBy('name')->get(),
            'tags' => $project->tags()->where(fn ($query) => $query->whereNull('archived_at')->when($current !== null, fn ($tags) => $tags->orWhereHas('movements', fn ($movements) => $movements->whereKey($current->id))))->orderBy('name')->get(),
            'customFieldDefinitions' => ($customFields ?? app(CustomFieldValues::class))->definitionsForForm($project, $current),
            'customFieldValues' => $current?->customFieldValues()->with('definition')->get() ?? collect(),
        ];
    }

    /** @return list<int> */
    private function validatedTagIds(Request $request, Project $project, ?Movement $current = null): array
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

    private function pastOrToday(string $value): CarbonImmutable
    {
        $date = CarbonImmutable::parse($value, 'Europe/Madrid')->startOfDay();
        if ($date->isAfter(CarbonImmutable::now('Europe/Madrid')->startOfDay())) {
            throw ValidationException::withMessages(['occurred_on' => 'Los movimientos manuales no pueden tener una fecha futura.']);
        }

        return $date;
    }

    private function ensureDateAfterInitial(CarbonImmutable $date, FinancialAccount $account, string $field = 'occurred_on'): void
    {
        if ($date->toDateString() < $account->initial_balance_date->toDateString()) {
            throw ValidationException::withMessages([$field => 'La fecha no puede ser anterior al saldo inicial de '.$account->name.' ('.$account->initial_balance_date->format('d/m/Y').').']);
        }
    }

    private function ensureMovement(Project $project, Movement $movement): void
    {
        abort_unless($movement->project_id === $project->id, 404);
    }

    private function ensureActiveMovement(Project $project, Movement $movement): void
    {
        $this->ensureMovement($project, $movement);
        abort_if($movement->trashed_at !== null, 404);
    }

    private function ensureRefundableExpense(Movement $movement): void
    {
        abort_unless($movement->type === MovementType::Expense && $movement->trashed_at === null, 404);
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function monthFrom(mixed $value): CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) !== 1) {
            return CarbonImmutable::now('Europe/Madrid')->startOfMonth();
        }

        return CarbonImmutable::parse($value.'-01', 'Europe/Madrid')->startOfMonth();
    }
}
