<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Calendar\BuildCalendarSummary;
use App\Actions\Calendar\BuildMonthlyCalendar;
use App\Enums\CalendarEventStatus;
use App\Enums\MovementType;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(
        Request $request,
        Project $project,
        BuildMonthlyCalendar $calendarBuilder,
        BuildCalendarSummary $summaryBuilder,
    ): View {
        $this->authorize('view', $project);
        $validated = $request->validate([
            'month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'day' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::enum(CalendarEventStatus::class)],
            'type' => ['nullable', Rule::enum(MovementType::class)],
            'account' => ['nullable', 'integer'],
            'category' => ['nullable', 'integer'],
            'member' => ['nullable', 'integer'],
        ]);

        $timezone = $project->timezone ?: 'Europe/Madrid';
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $month = CarbonImmutable::createFromFormat(
            'Y-m-d',
            ($validated['month'] ?? $today->format('Y-m')).'-01',
            $timezone,
        )->startOfMonth();

        $accounts = $project->financialAccounts()
            ->orderByRaw('archived_at is not null')
            ->orderBy('position')
            ->orderBy('name')
            ->get();
        $allCategories = $project->categories()
            ->with('parent')
            ->orderByRaw('archived_at is not null')
            ->orderBy('position')
            ->orderBy('name')
            ->get();
        $categories = $allCategories->whereNull('parent_id')->values();
        $members = $project->memberships()
            ->with('user')
            ->orderByRaw('removed_at is not null')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $filters = [
            'status' => $validated['status'] ?? null,
            'type' => $validated['type'] ?? null,
            'account' => isset($validated['account']) ? (int) $validated['account'] : null,
            'category' => isset($validated['category']) ? (int) $validated['category'] : null,
            'member' => isset($validated['member']) ? (int) $validated['member'] : null,
        ];

        $canRecordMovements = $request->user()->can('recordMovements', $project);
        $canManagePlans = $request->user()->can('managePlannedMovements', $project);
        $canManageRecurrences = $request->user()->can('manageRecurrences', $project);
        $calendar = $calendarBuilder->handle($project, $month, $today);
        $summary = $summaryBuilder->handle($project, $month, $calendar['events']);
        $events = collect($calendar['events'])
            ->filter(fn (array $event): bool => $this->passesFilters($event, $filters))
            ->map(fn (array $event): array => $this->presentEvent(
                $event,
                $project,
                $accounts->keyBy('id'),
                $allCategories->keyBy('id'),
                $members->keyBy('id'),
                $canRecordMovements,
                $canManagePlans,
                $canManageRecurrences,
            ))
            ->values();
        $eventsByDay = $events->groupBy('scheduled_on');
        $selectedDay = $this->selectedDay($validated['day'] ?? null, $month, $today);
        $filterQuery = array_filter($filters, fn (mixed $value): bool => $value !== null);

        return view('calendar.index', [
            'project' => $project,
            'month' => $month,
            'today' => $today,
            'selectedDay' => $selectedDay,
            'selectedEvents' => $eventsByDay->get($selectedDay->toDateString(), collect())->values(),
            'weeks' => $this->weeks($month, $today),
            'agendaDays' => $eventsByDay->map(fn (Collection $dayEvents, string $date): array => [
                'date' => CarbonImmutable::parse($date, $timezone),
                'events' => $dayEvents->values(),
            ])->values(),
            'eventsByDay' => $eventsByDay,
            'eventsCount' => $events->count(),
            'summary' => $summary,
            'filters' => $filters,
            'filterQuery' => $filterQuery,
            'accounts' => $accounts,
            'categories' => $categories,
            'members' => $members,
            'statuses' => CalendarEventStatus::cases(),
            'types' => MovementType::cases(),
            'canManagePlans' => $canManagePlans,
        ]);
    }

    /** @param array<string, mixed> $event @param array<string, mixed> $filters */
    private function passesFilters(array $event, array $filters): bool
    {
        if ($filters['status'] !== null && $event['status'] !== $filters['status']) {
            return false;
        }
        if ($filters['type'] !== null && $event['type'] !== $filters['type']) {
            return false;
        }
        if ($filters['account'] !== null
            && $event['financial_account_id'] !== $filters['account']
            && $event['destination_account_id'] !== $filters['account']) {
            return false;
        }
        if ($filters['category'] !== null && $event['category_id'] !== $filters['category']) {
            return false;
        }

        return $filters['member'] === null || $event['paid_by_user_id'] === $filters['member'];
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  Collection<int, mixed>  $accounts
     * @param  Collection<int, mixed>  $categories
     * @param  Collection<int, mixed>  $members
     * @return array<string, mixed>
     */
    private function presentEvent(
        array $event,
        Project $project,
        Collection $accounts,
        Collection $categories,
        Collection $members,
        bool $canRecordMovements,
        bool $canManagePlans,
        bool $canManageRecurrences,
    ): array {
        $type = MovementType::from($event['type']);
        $category = $event['category_id'] === null ? null : $categories->get($event['category_id']);
        $subcategory = $event['subcategory_id'] === null ? null : $categories->get($event['subcategory_id']);
        $account = $event['financial_account_id'] === null ? null : $accounts->get($event['financial_account_id']);
        $destination = $event['destination_account_id'] === null ? null : $accounts->get($event['destination_account_id']);
        $member = $event['paid_by_user_id'] === null ? null : $members->get($event['paid_by_user_id']);

        return [
            ...$event,
            'type_label' => $type->label(),
            'amount_formatted' => number_format($event['amount_cents'] / 100, 2, ',', '.').' €',
            'category_name' => $category?->name,
            'subcategory_name' => $subcategory?->name,
            'account_name' => $account?->name,
            'destination_account_name' => $destination?->name,
            'member_name' => $member?->name,
            'source_label' => match ($event['source']) {
                'planned_movement' => 'Planificación puntual',
                'recurrence' => 'Serie recurrente',
                default => 'Movimiento seleccionado',
            },
            'punctuality_label' => match ($event['punctuality']) {
                'early' => 'Anticipado',
                'on_time' => 'Puntual',
                'late' => 'Tardío',
                default => null,
            },
            'url' => $this->eventUrl(
                $event,
                $project,
                $canRecordMovements,
                $canManagePlans,
                $canManageRecurrences,
            ),
        ];
    }

    /** @param array<string, mixed> $event */
    private function eventUrl(
        array $event,
        Project $project,
        bool $canRecordMovements,
        bool $canManagePlans,
        bool $canManageRecurrences,
    ): string {
        if ($event['source'] === 'planned_movement') {
            if ($event['movement_id'] !== null && $canRecordMovements) {
                return route('movements.edit', [$project, $event['movement_id']]);
            }
            if ($canManagePlans && in_array($event['status'], ['planned', 'today', 'overdue'], true)) {
                return route('planned-movements.edit', [$project, $event['planned_movement_id']]);
            }

            return route('planned-movements.index', $project).'#planned-movement-'.$event['planned_movement_id'];
        }

        if ($event['source'] === 'recurrence') {
            if ($canManageRecurrences) {
                return route('recurrences.edit', [$project, $event['recurrence_template_id']]);
            }

            return route('recurrences.index', $project).'#recurrence-'.$event['recurrence_template_id'];
        }

        if ($canRecordMovements) {
            return route('movements.edit', [$project, $event['movement_id']]);
        }

        return route('movements.index', $project).'#movement-'.$event['movement_id'];
    }

    private function selectedDay(?string $requestedDay, CarbonImmutable $month, CarbonImmutable $today): CarbonImmutable
    {
        if ($requestedDay !== null) {
            $day = CarbonImmutable::createFromFormat('Y-m-d', $requestedDay, $month->timezone);
            if ($day->format('Y-m') === $month->format('Y-m')) {
                return $day->startOfDay();
            }
        }

        return $today->format('Y-m') === $month->format('Y-m') ? $today : $month;
    }

    /** @return list<list<array{date:CarbonImmutable, current_month:bool, today:bool}>> */
    private function weeks(CarbonImmutable $month, CarbonImmutable $today): array
    {
        $cursor = $month->startOfWeek(CarbonInterface::MONDAY);
        $lastDay = $month->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY)->startOfDay();
        $weeks = [];

        while ($cursor->lessThanOrEqualTo($lastDay)) {
            $week = [];
            for ($day = 0; $day < 7; $day++) {
                $week[] = [
                    'date' => $cursor,
                    'current_month' => $cursor->format('Y-m') === $month->format('Y-m'),
                    'today' => $cursor->isSameDay($today),
                ];
                $cursor = $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return $weeks;
    }
}
