<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Projects\CreateProject;
use App\Actions\Reports\BuildFinancialReport;
use App\Actions\Reports\BuildProjectMonthSummary;
use App\Enums\CategoryType;
use App\Enums\FinancialAccountType;
use App\Enums\MovementType;
use App\Models\FinancialAccount;
use App\Models\Movement;
use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

final class BenchmarkProjectScale extends Command
{
    protected $signature = 'smartwallet:benchmark-scale
        {--movements=100000 : Número de movimientos que se generarán, entre 1.000 y 100.000}';

    protected $description = 'Genera y mide un proyecto representativo únicamente en la base aislada de pruebas';

    /** @var list<array{label: string, elapsed_ms: float, database_ms: float, slowest_ms: float, queries: int, budget_ms: int, passed: bool}> */
    private array $results = [];

    public function handle(CreateProject $createProject, BuildFinancialReport $reports, BuildProjectMonthSummary $monthlySummary): int
    {
        $database = (string) DB::scalar('SELECT DATABASE()');
        if (! app()->environment('testing') || ! str_ends_with($database, '_test')) {
            $this->error('Medición cancelada: solo puede ejecutarse en testing y sobre una base terminada en _test.');

            return self::FAILURE;
        }

        $movementCount = (int) $this->option('movements');
        if ($movementCount < 1000 || $movementCount > 100000) {
            $this->error('El número de movimientos debe estar entre 1.000 y 100.000.');

            return self::FAILURE;
        }

        if (DB::table('users')->exists()) {
            $this->error('La base de medición debe estar vacía. Ejecuta migrate:fresh antes del banco de pruebas.');

            return self::FAILURE;
        }

        $this->info("Preparando un proyecto con {$movementCount} movimientos...");
        [$project, $accountIds, $categoryId, $tagId] = $this->createDataset($createProject, $movementCount);
        $month = CarbonImmutable::now('Europe/Madrid')->startOfMonth();
        $year = $month->year;

        DB::statement('ANALYZE TABLE movements');
        DB::statement('ANALYZE TABLE account_entries');
        DB::statement('ANALYZE TABLE movement_tag');

        Paginator::currentPageResolver(static fn (): int => 1);

        $this->measure('Panel mensual', 1000, function () use ($project, $month, $monthlySummary): void {
            $monthlySummary->handle($project, $month);
            $project->movements()->whereNull('trashed_at')->latest('occurred_on')->latest('id')->limit(6)->get();
        });

        $this->measure('Listado mensual paginado', 1200, function () use ($project, $month): void {
            $project->movements()
                ->with(['category', 'subcategory', 'account', 'destinationAccount', 'paidBy', 'originalMovement', 'tags'])
                ->withSum(['refunds as refunded_cents' => fn ($query) => $query->whereNull('trashed_at')], 'amount_cents')
                ->whereNull('trashed_at')
                ->whereBetween('occurred_on', [$month->toDateString(), $month->endOfMonth()->toDateString()])
                ->latest('occurred_on')->latest('id')->paginate(30);
        });

        $this->measure('Listado filtrado por categoría', 1200, function () use ($project, $month, $categoryId): void {
            $project->movements()
                ->whereNull('trashed_at')
                ->whereBetween('occurred_on', [$month->toDateString(), $month->endOfMonth()->toDateString()])
                ->where('category_id', $categoryId)
                ->latest('occurred_on')->latest('id')->paginate(30);
        });

        $this->measure('Listado filtrado por etiqueta', 1500, function () use ($project, $month, $tagId): void {
            $project->movements()
                ->whereNull('trashed_at')
                ->whereBetween('occurred_on', [$month->toDateString(), $month->endOfMonth()->toDateString()])
                ->whereHas('tags', fn ($tags) => $tags->whereKey($tagId))
                ->latest('occurred_on')->latest('id')->paginate(30);
        });

        $this->measure('Informe anual', 2500, fn () => $reports->year($project, $year));

        $this->measure('Comparación de cinco años', 8000, function () use ($project, $reports, $year): void {
            foreach (range(0, 4) as $offset) {
                $reports->year($project, $year - $offset, [], true, false);
            }
        });

        $this->measure('Saldos de cuentas', 1200, function () use ($project): void {
            $project->financialAccounts()
                ->withSum(['entries as entries_total' => fn ($query) => $query->whereDate('occurred_on', '<=', now('Europe/Madrid')->toDateString())], 'signed_amount_cents')
                ->get();
        });

        $exported = 0;
        $this->measure('Recorrido para CSV completo', 30000, function () use ($project, &$exported): void {
            Movement::query()
                ->where('project_id', $project->id)
                ->whereNull('trashed_at')
                ->with(['account', 'destinationAccount', 'paidBy', 'category', 'subcategory', 'tags'])
                ->orderBy('id')
                ->chunkById(500, function ($movements) use (&$exported): void {
                    $exported += $movements->count();
                });
        }, warm: false);

        if ($exported !== $movementCount) {
            $this->error("El recorrido CSV procesó {$exported} movimientos de {$movementCount}.");

            return self::FAILURE;
        }

        $this->table(
            ['Operación', 'Tiempo', 'SQL', 'Más lenta', 'Consultas', 'Objetivo', 'Resultado'],
            array_map(static fn (array $result): array => [
                $result['label'],
                number_format($result['elapsed_ms'], 1, ',', '.').' ms',
                number_format($result['database_ms'], 1, ',', '.').' ms',
                number_format($result['slowest_ms'], 1, ',', '.').' ms',
                $result['queries'],
                '<= '.number_format($result['budget_ms'], 0, ',', '.').' ms',
                $result['passed'] ? 'OK' : 'LENTO',
            ], $this->results),
        );

        $this->newLine();
        $plan = DB::selectOne(
            'EXPLAIN SELECT id FROM movements WHERE project_id = ? AND trashed_at IS NULL AND occurred_on BETWEEN ? AND ? ORDER BY occurred_on DESC, id DESC LIMIT 30',
            [$project->id, $month->toDateString(), $month->endOfMonth()->toDateString()],
        );
        $this->line('Índice del listado mensual: '.($plan->key ?? 'ninguno').' · filas estimadas: '.($plan->rows ?? '?'));
        $this->line('Cuentas comprobadas: '.implode(', ', $accountIds));
        $this->line('Base utilizada: '.$database);

        if (collect($this->results)->contains(fn (array $result): bool => ! $result['passed'])) {
            $this->error('Alguna operación ha superado el objetivo máximo definido.');

            return self::FAILURE;
        }

        $this->info('Banco de escala completado correctamente.');

        return self::SUCCESS;
    }

    /**
     * @return array{Project, list<int>, int, int}
     */
    private function createDataset(CreateProject $createProject, int $movementCount): array
    {
        $user = User::create([
            'name' => 'Usuario de rendimiento',
            'email' => 'rendimiento@smartwallet.test',
            'password' => 'contraseña de rendimiento local',
        ]);
        $today = CarbonImmutable::now('Europe/Madrid')->startOfDay();
        $project = $createProject->handle($user, [
            'name' => 'Proyecto de rendimiento',
            'description' => 'Datos temporales para validar la escala de SmartWallet.',
            'color' => '#147d68',
            'icon' => 'home',
            'account_name' => 'Cuenta principal',
            'account_type' => FinancialAccountType::Checking->value,
            'initial_balance' => '10000,00',
            'initial_balance_date' => $today->subYears(5)->startOfYear()->toDateString(),
            'monthly_budget' => '2500,00',
        ]);

        $checking = $project->financialAccounts()->firstOrFail();
        $savings = FinancialAccount::create([
            'project_id' => $project->id,
            'name' => 'Ahorro',
            'type' => FinancialAccountType::Savings,
            'initial_balance_cents' => 0,
            'initial_balance_date' => $today->subYears(5)->startOfYear(),
            'color' => '#315f87',
            'icon' => 'wallet',
            'position' => 1,
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);
        $investment = FinancialAccount::create([
            'project_id' => $project->id,
            'name' => 'Inversión externa',
            'type' => FinancialAccountType::ExternalInvestment,
            'initial_balance_cents' => 0,
            'initial_balance_date' => $today->subYears(5)->startOfYear(),
            'color' => '#8f6b32',
            'icon' => 'chart',
            'position' => 2,
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);
        $tag = Tag::create([
            'project_id' => $project->id,
            'name' => 'Habitual',
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);

        $expenseCategories = $project->categories()
            ->where('type', CategoryType::Expense->value)
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('id')
            ->limit(12)
            ->get();
        $incomeCategories = $project->categories()
            ->where('type', CategoryType::Income->value)
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('id')
            ->limit(4)
            ->get();
        $start = $today->subYears(4)->startOfYear();
        $dayCount = (int) $start->diffInDays($today) + 1;
        $dates = collect(range(0, $dayCount - 1))->map(fn (int $offset): string => $start->addDays($offset)->toDateString())->all();
        $nextMovementId = ((int) DB::table('movements')->max('id')) + 1;
        $timestamp = now()->toDateTimeString();

        $this->output->progressStart($movementCount);
        foreach (range(0, (int) ceil($movementCount / 1000) - 1) as $chunkNumber) {
            $offset = $chunkNumber * 1000;
            $size = min(1000, $movementCount - $offset);
            $movements = [];
            $entries = [];
            $movementTags = [];

            for ($chunkOffset = 0; $chunkOffset < $size; $chunkOffset++) {
                $index = $offset + $chunkOffset;
                $movementId = $nextMovementId + $index;
                $bucket = $index % 100;
                $amountCents = 100 + (($index * 37) % 50000);
                $occurredOn = $dates[$index % $dayCount];
                $type = match (true) {
                    $bucket < 70 => MovementType::Expense,
                    $bucket < 88 => MovementType::Income,
                    $bucket < 96 => MovementType::Transfer,
                    default => MovementType::InvestmentContribution,
                };
                $category = $type === MovementType::Expense
                    ? $expenseCategories[$index % $expenseCategories->count()]
                    : ($type === MovementType::Income ? $incomeCategories[$index % $incomeCategories->count()] : null);
                $subcategory = $category?->children->first();
                $destinationId = match ($type) {
                    MovementType::Transfer => $savings->id,
                    MovementType::InvestmentContribution => $investment->id,
                    default => null,
                };

                $movements[] = [
                    'id' => $movementId,
                    'project_id' => $project->id,
                    'type' => $type->value,
                    'amount_cents' => $amountCents,
                    'occurred_on' => $occurredOn,
                    'concept' => $type === MovementType::Expense && $index % 5 === 0 ? 'Compra de supermercado' : 'Movimiento de escala '.($index + 1),
                    'category_id' => $category?->id,
                    'subcategory_id' => $subcategory?->id,
                    'financial_account_id' => $checking->id,
                    'destination_account_id' => $destinationId,
                    'paid_by_user_id' => in_array($type, [MovementType::Expense, MovementType::Income], true) ? $user->id : null,
                    'original_movement_id' => null,
                    'notes' => null,
                    'recurrence_template_id' => null,
                    'recurrence_occurrence_id' => null,
                    'generated_automatically_at' => null,
                    'trashed_at' => null,
                    'purge_at' => null,
                    'created_by_user_id' => $user->id,
                    'updated_by_user_id' => $user->id,
                    'deleted_by_user_id' => null,
                    'restored_by_user_id' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];

                $signedAmount = $type === MovementType::Income ? $amountCents : -$amountCents;
                $entries[] = [
                    'movement_id' => $movementId,
                    'project_id' => $project->id,
                    'financial_account_id' => $checking->id,
                    'signed_amount_cents' => $signedAmount,
                    'occurred_on' => $occurredOn,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
                if ($destinationId !== null) {
                    $entries[] = [
                        'movement_id' => $movementId,
                        'project_id' => $project->id,
                        'financial_account_id' => $destinationId,
                        'signed_amount_cents' => $amountCents,
                        'occurred_on' => $occurredOn,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
                if ($index % 10 === 0) {
                    $movementTags[] = [
                        'movement_id' => $movementId,
                        'tag_id' => $tag->id,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
            }

            DB::transaction(function () use ($movements, $entries, $movementTags): void {
                DB::table('movements')->insert($movements);
                DB::table('account_entries')->insert($entries);
                if ($movementTags !== []) {
                    DB::table('movement_tag')->insert($movementTags);
                }
            });
            $this->output->progressAdvance($size);
        }
        $this->output->progressFinish();

        return [$project, [$checking->id, $savings->id, $investment->id], $expenseCategories->first()->id, $tag->id];
    }

    private function measure(string $label, int $budgetMs, callable $operation, bool $warm = true): void
    {
        if ($warm) {
            $operation();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $startedAt = hrtime(true);
        $operation();
        $elapsedMs = (hrtime(true) - $startedAt) / 1_000_000;
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        DB::flushQueryLog();

        $this->results[] = [
            'label' => $label,
            'elapsed_ms' => $elapsedMs,
            'database_ms' => (float) collect($queries)->sum('time'),
            'slowest_ms' => (float) collect($queries)->max('time'),
            'queries' => count($queries),
            'budget_ms' => $budgetMs,
            'passed' => $elapsedMs <= $budgetMs,
        ];
    }
}
