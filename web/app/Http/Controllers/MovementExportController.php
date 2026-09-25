<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CustomFields\ApplyCustomFieldFilters;
use App\Enums\MovementType;
use App\Models\Movement;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MovementExportController extends Controller
{
    public function export(Request $request, Project $project, ApplyCustomFieldFilters $customFieldFilters): StreamedResponse
    {
        $this->authorize('view', $project);
        $validated = $request->validate([
            'scope' => ['required', Rule::in(['filtered', 'all'])],
            'month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'type' => ['nullable', Rule::enum(MovementType::class)],
            'account' => ['nullable', 'integer'],
            'category' => ['nullable', 'integer'],
            'member' => ['nullable', 'integer'],
            'tag' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:180'],
            'from' => ['nullable', 'date', 'required_with:to'],
            'to' => ['nullable', 'date', 'required_with:from', 'after_or_equal:from'],
            'custom_filters' => ['nullable', 'array'],
        ]);

        $query = $this->baseQuery($project)->whereNull('trashed_at');
        $suffix = 'completo';
        if ($validated['scope'] === 'filtered') {
            $month = CarbonImmutable::createFromFormat('Y-m-d', ($validated['month'] ?? now('Europe/Madrid')->format('Y-m')).'-01', 'Europe/Madrid')->startOfMonth();
            $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from'], 'Europe/Madrid') : $month;
            $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to'], 'Europe/Madrid') : $month->endOfMonth();
            $query->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()]);
            $this->applyFilters($query, $validated);
            $customFieldFilters->handle($request, $project, $query);
            $suffix = isset($validated['from']) ? $from->format('Y-m-d').'-'.$to->format('Y-m-d').'-filtrado' : $month->format('Y-m').'-filtrado';
        }

        return $this->download($query, $project, $suffix, false);
    }

    public function trash(Project $project): StreamedResponse
    {
        $this->authorize('exportTrash', $project);

        return $this->download(
            $this->baseQuery($project)->whereNotNull('trashed_at'),
            $project,
            'papelera',
            true,
        );
    }

    private function baseQuery(Project $project): Builder
    {
        return Movement::query()->where('project_id', $project->id)->with([
            'account', 'destinationAccount', 'paidBy', 'category', 'subcategory', 'tags', 'customFieldValues.definition',
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when(! empty($filters['type']), fn (Builder $builder) => $builder->where('type', $filters['type']))
            ->when(! empty($filters['account']), fn (Builder $builder) => $builder->where(fn ($accounts) => $accounts
                ->where('financial_account_id', (int) $filters['account'])
                ->orWhere('destination_account_id', (int) $filters['account'])))
            ->when(! empty($filters['category']), fn (Builder $builder) => $builder->where('category_id', (int) $filters['category']))
            ->when(! empty($filters['member']), fn (Builder $builder) => $builder->where('paid_by_user_id', (int) $filters['member']))
            ->when(! empty($filters['tag']), fn (Builder $builder) => $builder->whereHas('tags', fn ($tags) => $tags->whereKey((int) $filters['tag'])))
            ->when(! empty($filters['search']), fn (Builder $builder) => $builder->where('concept', 'like', '%'.trim((string) $filters['search']).'%'));
    }

    private function download(Builder $query, Project $project, string $suffix, bool $trash): StreamedResponse
    {
        $filename = 'smartwallet-'.Str::slug($project->name).'-movimientos-'.$suffix.'.csv';
        $customFieldDefinitions = $project->customFieldDefinitions()->orderBy('position')->orderBy('name')->get();

        return response()->streamDownload(function () use ($query, $project, $trash, $customFieldDefinitions): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                return;
            }
            fwrite($output, "\xEF\xBB\xBF");
            $headers = ['Proyecto', 'Tipo', 'Cuenta de origen', 'Cuenta de destino', 'Pagado o recibido por', 'Concepto', 'Importe', 'Fecha', 'Categoría', 'Subcategoría', 'Etiquetas', 'Notas'];
            foreach ($customFieldDefinitions as $definition) {
                $headers[] = 'Campo: '.$definition->name;
            }
            if ($trash) {
                array_push($headers, 'Estado', 'Eliminación definitiva');
            }
            $this->putRow($output, $headers);

            $query->orderBy('id')->chunkById(500, function ($movements) use ($output, $project, $trash, $customFieldDefinitions): void {
                foreach ($movements as $movement) {
                    $customValues = $movement->customFieldValues->keyBy('custom_field_definition_id');
                    $row = [
                        $project->name,
                        $movement->type->label(),
                        $movement->account?->name ?? '',
                        $movement->destinationAccount?->name ?? '',
                        $movement->paidBy?->name ?? '',
                        $movement->concept,
                        number_format($movement->amount_cents / 100, 2, ',', ''),
                        $movement->occurred_on->format('d/m/Y'),
                        $movement->category?->name ?? '',
                        $movement->subcategory?->name ?? '',
                        $movement->tags->sortBy('name')->pluck('name')->implode(', '),
                        $movement->notes ?? '',
                    ];
                    foreach ($customFieldDefinitions as $definition) {
                        $row[] = $customValues->get($definition->id)?->displayValue() ?? '';
                    }
                    if ($trash) {
                        array_push($row, 'Papelera', $movement->purge_at?->timezone('Europe/Madrid')->format('d/m/Y H:i') ?? '');
                    }
                    $this->putRow($output, $row);
                }
            });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @param resource $output @param list<string> $row */
    private function putRow($output, array $row): void
    {
        fputcsv($output, array_map([$this, 'safeCell'], $row), ';', '"', '\\', "\r\n");
    }

    private function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/u', $value) === 1 ? "'".$value : $value;
    }
}
