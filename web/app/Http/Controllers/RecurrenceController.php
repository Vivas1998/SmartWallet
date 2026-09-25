<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Audit\RecordProjectAudit;
use App\Actions\Recurrences\GenerateDueRecurrences;
use App\Enums\CategoryType;
use App\Enums\FinancialAccountType;
use App\Enums\MovementType;
use App\Enums\RecurrenceFrequency;
use App\Enums\RecurrenceOccurrenceStatus;
use App\Models\FinancialAccount;
use App\Models\Project;
use App\Models\RecurrenceOccurrence;
use App\Models\RecurrenceTemplate;
use App\Services\Recurrences\RecurrenceSchedule;
use App\Support\CustomFieldValues;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RecurrenceController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        return view('recurrences.index', [
            'project' => $project,
            'templates' => $project->recurrenceTemplates()
                ->with(['account', 'destinationAccount', 'category', 'savingsGoal', 'tags'])
                ->withCount('occurrences')
                ->orderByRaw('paused_at is not null')
                ->orderBy('next_occurrence_on')
                ->orderBy('concept')
                ->get(),
            'canManage' => $request->user()->can('manageRecurrences', $project),
        ]);
    }

    public function create(Project $project): View
    {
        $this->authorize('manageRecurrences', $project);

        return view('recurrences.form', $this->formData($project));
    }

    public function store(Request $request, Project $project, RecordProjectAudit $audit, GenerateDueRecurrences $generate, CustomFieldValues $customFields): RedirectResponse
    {
        $this->authorize('manageRecurrences', $project);
        $attributes = $this->validatedAttributes($request, $project);
        $tagIds = $this->validatedTagIds($request, $project);
        $customValues = $customFields->validate($request, $project, $attributes['type']);

        DB::transaction(function () use ($request, $project, $attributes, $tagIds, $customValues, $audit, $customFields): void {
            $template = RecurrenceTemplate::create([
                'project_id' => $project->id,
                ...$attributes,
                'created_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);
            $template->tags()->sync($tagIds);
            $customFields->sync($template, $customValues);
            $audit->handle($project, $request->user(), 'recurrence', $template->id, 'created', null, $template->auditSnapshot());
        });

        $result = $generate->handle();
        $suffix = $result['generated'] > 0 ? ' Se han creado las apariciones que ya correspondían.' : '';

        return redirect()->route('recurrences.index', $project)->with('status', 'Movimiento recurrente creado.'.$suffix);
    }

    public function edit(Project $project, RecurrenceTemplate $recurrence): View
    {
        $this->authorize('manageRecurrences', $project);
        $this->ensureTemplate($project, $recurrence);

        return view('recurrences.form', $this->formData($project, $recurrence));
    }

    public function update(Request $request, Project $project, RecurrenceTemplate $recurrence, RecordProjectAudit $audit, GenerateDueRecurrences $generate, CustomFieldValues $customFields): RedirectResponse
    {
        $this->authorize('manageRecurrences', $project);
        $this->ensureTemplate($project, $recurrence);
        $attributes = $this->validatedAttributes($request, $project, $recurrence);
        $tagIds = $this->validatedTagIds($request, $project, $recurrence);
        $customValues = $customFields->validate($request, $project, $attributes['type']);

        DB::transaction(function () use ($request, $project, $recurrence, $attributes, $tagIds, $customValues, $audit, $customFields): void {
            $before = $recurrence->auditSnapshot();
            $recurrence->update([...$attributes, 'updated_by_user_id' => $request->user()->id]);
            $recurrence->tags()->sync($tagIds);
            $customFields->sync($recurrence, $customValues);
            $audit->handle($project, $request->user(), 'recurrence', $recurrence->id, 'updated', $before, $recurrence->fresh()->auditSnapshot());
        });
        $generate->handle();

        return redirect()->route('recurrences.index', $project)->with('status', 'Se ha actualizado la serie futura. Las apariciones anteriores no han cambiado.');
    }

    public function pause(Request $request, Project $project, RecurrenceTemplate $recurrence, RecordProjectAudit $audit): RedirectResponse
    {
        $this->authorize('manageRecurrences', $project);
        $this->ensureTemplate($project, $recurrence);
        if ($recurrence->paused_at !== null || $recurrence->next_occurrence_on === null) {
            return back();
        }

        $before = $recurrence->auditSnapshot();
        $recurrence->update(['paused_at' => now(), 'updated_by_user_id' => $request->user()->id]);
        $audit->handle($project, $request->user(), 'recurrence', $recurrence->id, 'paused', $before, $recurrence->fresh()->auditSnapshot());

        return back()->with('status', 'Serie pausada. No se crearán movimientos mientras permanezca así.');
    }

    public function resume(Request $request, Project $project, RecurrenceTemplate $recurrence, RecordProjectAudit $audit, GenerateDueRecurrences $generate, RecurrenceSchedule $schedule): RedirectResponse
    {
        $this->authorize('manageRecurrences', $project);
        $this->ensureTemplate($project, $recurrence);
        if ($recurrence->paused_at === null || $recurrence->next_occurrence_on === null) {
            return back();
        }

        $today = CarbonImmutable::now('Europe/Madrid')->startOfDay();
        $omitted = 0;
        DB::transaction(function () use ($request, $project, $recurrence, $audit, $schedule, $today, &$omitted): void {
            $template = RecurrenceTemplate::query()->lockForUpdate()->findOrFail($recurrence->id);
            $before = $template->auditSnapshot();
            while ($template->next_occurrence_on !== null && $template->next_occurrence_on->toDateString() < $today->toDateString()) {
                RecurrenceOccurrence::firstOrCreate(
                    ['recurrence_template_id' => $template->id, 'scheduled_on' => $template->next_occurrence_on->toDateString()],
                    ['status' => RecurrenceOccurrenceStatus::Skipped, 'processed_at' => now()],
                );
                $next = $schedule->nextDate($template, CarbonImmutable::parse($template->next_occurrence_on));
                $template->next_occurrence_on = $template->ends_on !== null && $next->toDateString() > $template->ends_on->toDateString() ? null : $next;
                $omitted++;
            }
            $template->paused_at = null;
            $template->updated_by_user_id = $request->user()->id;
            $template->save();
            $audit->handle($project, $request->user(), 'recurrence', $template->id, 'resumed', $before, $template->fresh()->auditSnapshot());
        });
        $generate->handle();

        $detail = $omitted > 0 ? ' Se han omitido '.$omitted.' fecha(s) que transcurrieron durante la pausa.' : '';

        return back()->with('status', 'Serie reanudada.'.$detail);
    }

    public function skip(Request $request, Project $project, RecurrenceTemplate $recurrence, RecordProjectAudit $audit, RecurrenceSchedule $schedule): RedirectResponse
    {
        $this->authorize('manageRecurrences', $project);
        $this->ensureTemplate($project, $recurrence);
        if ($recurrence->next_occurrence_on === null) {
            return back()->withErrors(['recurrence' => 'Esta serie ya no tiene próximas apariciones.']);
        }

        DB::transaction(function () use ($request, $project, $recurrence, $audit, $schedule): void {
            $template = RecurrenceTemplate::query()->lockForUpdate()->findOrFail($recurrence->id);
            $before = $template->auditSnapshot();
            $scheduled = CarbonImmutable::parse($template->next_occurrence_on);
            RecurrenceOccurrence::firstOrCreate(
                ['recurrence_template_id' => $template->id, 'scheduled_on' => $scheduled->toDateString()],
                ['status' => RecurrenceOccurrenceStatus::Skipped, 'processed_at' => now()],
            );
            $next = $schedule->nextDate($template, $scheduled);
            $template->update([
                'next_occurrence_on' => $template->ends_on !== null && $next->toDateString() > $template->ends_on->toDateString() ? null : $next,
                'updated_by_user_id' => $request->user()->id,
            ]);
            $audit->handle($project, $request->user(), 'recurrence', $template->id, 'next_skipped', $before, $template->fresh()->auditSnapshot());
        });

        return back()->with('status', 'Se ha omitido únicamente la próxima aparición.');
    }

    /** @return array<string, mixed> */
    private function validatedAttributes(Request $request, Project $project, ?RecurrenceTemplate $current = null): array
    {
        $validated = $request->validate([
            'movement_kind' => ['required', Rule::in(['expense', 'income', 'transfer'])],
            'amount' => ['required', 'regex:/^(?:(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?|\d+(?:\.\d{1,2})?)$/'],
            'concept' => ['required', 'string', 'max:180'],
            'category_id' => ['nullable', 'integer'],
            'subcategory_id' => ['nullable', 'integer'],
            'financial_account_id' => ['required', 'integer'],
            'destination_account_id' => ['nullable', 'integer', 'different:financial_account_id'],
            'paid_by_user_id' => ['nullable', 'integer'],
            'savings_goal_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'frequency' => ['required', Rule::enum(RecurrenceFrequency::class)],
            'start_on' => ['required', 'date'],
            'next_occurrence_on' => ['required', 'date', 'after_or_equal:start_on'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:next_occurrence_on'],
            'tag_ids' => ['nullable', 'array', 'max:20'],
            'tag_ids.*' => ['integer', 'distinct'],
        ]);
        $amountCents = Money::toCents($validated['amount']);
        if ($amountCents <= 0) {
            throw ValidationException::withMessages(['amount' => 'El importe debe ser mayor que cero.']);
        }

        $kind = $validated['movement_kind'];
        $account = $this->activeAccount($project, (int) $validated['financial_account_id'], $current?->financial_account_id);
        $destination = null;
        $category = null;
        $subcategory = null;
        $paidById = null;
        $goal = null;
        $type = MovementType::from($kind);

        if ($kind === 'transfer') {
            if (empty($validated['destination_account_id'])) {
                throw ValidationException::withMessages(['destination_account_id' => 'Selecciona una cuenta de destino.']);
            }
            $destination = $this->activeAccount($project, (int) $validated['destination_account_id'], $current?->destination_account_id);
            if ($account->is($destination)) {
                throw ValidationException::withMessages(['destination_account_id' => 'Elige una cuenta de destino distinta.']);
            }
            if ($account->type === FinancialAccountType::CreditCard) {
                throw ValidationException::withMessages(['financial_account_id' => 'Una tarjeta de crédito no puede ser la cuenta de origen.']);
            }
            $type = ($account->type === FinancialAccountType::ExternalInvestment || $destination->type === FinancialAccountType::ExternalInvestment)
                ? MovementType::InvestmentContribution
                : MovementType::Transfer;
            if (! empty($validated['savings_goal_id'])) {
                $goal = $project->savingsGoals()->whereKey((int) $validated['savings_goal_id'])->whereNull('archived_at')->first();
                if ($goal === null || $goal->financial_account_id !== $destination->id) {
                    throw ValidationException::withMessages(['savings_goal_id' => 'El objetivo debe estar activo y vinculado a la cuenta de destino.']);
                }
            }
        } else {
            $categoryType = $kind === 'expense' ? CategoryType::Expense : CategoryType::Income;
            $category = $project->categories()->whereKey((int) ($validated['category_id'] ?? 0))->whereNull('parent_id')->first();
            if ($category === null || $category->type !== $categoryType || ($category->archived_at !== null && $category->id !== $current?->category_id)) {
                throw ValidationException::withMessages(['category_id' => 'Selecciona una categoría principal activa del tipo correcto.']);
            }
            if (! empty($validated['subcategory_id'])) {
                $subcategory = $project->categories()->whereKey((int) $validated['subcategory_id'])->first();
                if ($subcategory === null || $subcategory->parent_id !== $category->id || ($subcategory->archived_at !== null && $subcategory->id !== $current?->subcategory_id)) {
                    throw ValidationException::withMessages(['subcategory_id' => 'Selecciona una subcategoría activa de la categoría indicada.']);
                }
            }
            if ($kind === 'income' && in_array($account->type, [FinancialAccountType::CreditCard, FinancialAccountType::ExternalInvestment], true)) {
                throw ValidationException::withMessages(['financial_account_id' => 'Selecciona una cuenta compatible con ingresos.']);
            }
            if ($kind === 'expense' && $account->type === FinancialAccountType::ExternalInvestment) {
                throw ValidationException::withMessages(['financial_account_id' => 'Una inversión externa no admite gastos.']);
            }
            $paidBy = $project->activeMembers()->where('users.id', (int) ($validated['paid_by_user_id'] ?? $request->user()->id))->first();
            if ($paidBy === null) {
                throw ValidationException::withMessages(['paid_by_user_id' => 'Selecciona un miembro activo del proyecto.']);
            }
            $paidById = $paidBy->id;
        }

        $start = CarbonImmutable::parse($validated['start_on'], 'Europe/Madrid');
        foreach (array_filter([$account, $destination]) as $usedAccount) {
            if ($start->toDateString() < $usedAccount->initial_balance_date->toDateString()) {
                throw ValidationException::withMessages(['start_on' => 'La serie no puede empezar antes del saldo inicial de '.$usedAccount->name.'.']);
            }
        }

        return [
            'type' => $type,
            'amount_cents' => $amountCents,
            'concept' => trim($validated['concept']),
            'category_id' => $category?->id,
            'subcategory_id' => $subcategory?->id,
            'financial_account_id' => $account->id,
            'destination_account_id' => $destination?->id,
            'paid_by_user_id' => $paidById,
            'savings_goal_id' => $goal?->id,
            'notes' => ($notes = trim((string) ($validated['notes'] ?? ''))) === '' ? null : $notes,
            'frequency' => $validated['frequency'],
            'start_on' => $start->toDateString(),
            'anchor_day' => $start->day,
            'next_occurrence_on' => $validated['next_occurrence_on'],
            'ends_on' => $validated['ends_on'] ?? null,
        ];
    }

    private function activeAccount(Project $project, int $id, ?int $currentId): FinancialAccount
    {
        $account = $project->financialAccounts()->whereKey($id)->first();
        if ($account === null || ($account->archived_at !== null && $account->id !== $currentId)) {
            throw ValidationException::withMessages(['financial_account_id' => 'Selecciona una cuenta activa del proyecto.']);
        }

        return $account;
    }

    /** @return array<string, mixed> */
    private function formData(Project $project, ?RecurrenceTemplate $recurrence = null): array
    {
        return [
            'project' => $project,
            'recurrence' => $recurrence,
            'frequencies' => RecurrenceFrequency::cases(),
            'categories' => $project->categories()->whereNull('parent_id')->whereNull('archived_at')->with(['children' => fn ($query) => $query->whereNull('archived_at')])->orderBy('position')->get(),
            'accounts' => $project->financialAccounts()->whereNull('archived_at')->orderBy('position')->get(),
            'members' => $project->activeMembers()->orderBy('name')->get(),
            'goals' => $project->savingsGoals()->with('account')->whereNull('archived_at')->orderBy('name')->get(),
            'tags' => $project->tags()->where(fn ($query) => $query->whereNull('archived_at')->when($recurrence !== null, fn ($tags) => $tags->orWhereHas('recurrenceTemplates', fn ($templates) => $templates->whereKey($recurrence->id))))->orderBy('name')->get(),
            'customFieldDefinitions' => app(CustomFieldValues::class)->definitionsForForm($project, $recurrence),
            'customFieldValues' => $recurrence?->customFieldValues()->with('definition')->get() ?? collect(),
            'today' => CarbonImmutable::now('Europe/Madrid')->toDateString(),
        ];
    }

    /** @return list<int> */
    private function validatedTagIds(Request $request, Project $project, ?RecurrenceTemplate $current = null): array
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

    private function ensureTemplate(Project $project, RecurrenceTemplate $recurrence): void
    {
        abort_unless($recurrence->project_id === $project->id, 404);
    }
}
