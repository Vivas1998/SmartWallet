@extends('layouts.app')

@section('title', 'Comparar periodos de '.$project->name)

@section('content')
    @php
        $money = fn (int $cents) => number_format($cents / 100, 2, ',', '.').' €';
        $sharedFilters = array_filter($filters, fn ($value) => $value !== null);
        $comparisonMax = max(1, (int) $comparisons->max(fn ($item) => max($item['budget_cents'], $item['expense_cents'], $item['income_cents'])));
        $reference = $comparisons->first();
        $expenseVariation = function (int $current) use ($reference, $money): string {
            $delta = $current - $reference['expense_cents'];
            if ($reference['expense_cents'] === 0) return ($delta >= 0 ? '+' : '').$money($delta).' · sin base porcentual';
            $percentage = round(($delta / abs($reference['expense_cents'])) * 100, 1);
            return ($delta >= 0 ? '+' : '').$money($delta).' · '.($percentage >= 0 ? '+' : '').number_format($percentage, 1, ',', '.').' %';
        };
        $comparisonCategories = $comparisons
            ->flatMap(fn ($item) => $item['categories'])
            ->groupBy(fn ($item) => (string) ($item['category_id'] ?? 'none'))
            ->map(function ($items) use ($comparisons) {
                $first = $items->first();
                $values = $comparisons->map(fn ($period) => (int) ($period['categories']->firstWhere('category_id', $first['category_id'])['expense_cents'] ?? 0));
                return ['category_id' => $first['category_id'], 'name' => $first['name'], 'values' => $values, 'total_cents' => (int) $values->sum()];
            })->sortByDesc('total_cents')->values();
    @endphp
    <header class="page-heading"><div class="page-heading__copy"><a class="back-link" href="{{ route('projects.show', $project) }}">← {{ $project->name }}</a><p class="eyebrow">Análisis financiero</p><h1 class="page-heading__title">Informes</h1><p class="page-heading__intro">Compara hasta cinco meses o años del mismo proyecto con las mismas reglas contables.</p></div></header>
    @include('projects._navigation', ['project' => $project])
    @include('reports._tabs')

    <form class="filter-panel comparison-form" action="{{ route('reports.compare', $project) }}" method="get" data-comparison-form>
        <div class="comparison-form__periods">
            <div class="field"><label class="field__label" for="comparison-mode">Tipo de periodo</label><select class="field__control" id="comparison-mode" name="mode" data-comparison-mode><option value="months" @selected($mode === 'months')>Meses</option><option value="years" @selected($mode === 'years')>Años completos</option></select></div>
            @for($index = 0; $index < 5; $index++)
                <div class="field"><label class="field__label" for="comparison-period-{{ $index }}">Periodo {{ $index + 1 }} {{ $index > 1 ? '(opcional)' : '' }}</label><input class="field__control" id="comparison-period-{{ $index }}" name="periods[]" value="{{ $periods->get($index) }}" type="{{ $mode === 'months' ? 'month' : 'number' }}" @if($mode === 'years') min="1900" max="2199" step="1" @endif data-comparison-period @required($index < 2)></div>
            @endfor
        </div>
        <div class="filter-panel__grid comparison-form__filters">@include('reports._filter_fields')<button class="button button--primary filter-panel__button" type="submit">Comparar periodos</button></div>
    </form>

    <div class="comparison-chart" aria-label="Comparación visual de presupuesto, gasto e ingresos">
        @foreach($comparisons as $item)
            @php($detail = $mode === 'months' ? ['month' => $item['key']] : ['from' => $item['key'].'-01-01', 'to' => $item['key'].'-12-31'])
            <article class="comparison-card"><div class="comparison-card__heading"><h2>{{ $item['label'] }}</h2>@if($item['planned'])<span class="badge badge--muted">Planificación</span>@endif</div><div class="comparison-card__bars"><div><span>Presupuesto</span><i class="comparison-card__track"><i class="comparison-card__bar comparison-card__bar--budget" style="--bar-width: {{ round(($item['budget_cents'] / $comparisonMax) * 100, 2) }}%"></i></i><strong>{{ $money($item['budget_cents']) }}</strong></div><div><span>Gasto neto</span><i class="comparison-card__track"><i class="comparison-card__bar comparison-card__bar--expense" style="--bar-width: {{ round(($item['expense_cents'] / $comparisonMax) * 100, 2) }}%"></i></i><strong>{{ $money($item['expense_cents']) }}</strong></div><div><span>Ingresos</span><i class="comparison-card__track"><i class="comparison-card__bar comparison-card__bar--income" style="--bar-width: {{ round(($item['income_cents'] / $comparisonMax) * 100, 2) }}%"></i></i><strong>{{ $money($item['income_cents']) }}</strong></div></div><p class="comparison-card__variation"><small>Variación del gasto frente a {{ $reference['label'] }}</small><strong>{{ $loop->first ? 'Periodo de referencia' : $expenseVariation($item['expense_cents']) }}</strong></p><a class="text-link" href="{{ route('movements.index', ['project' => $project, ...$detail, ...$sharedFilters]) }}">Ver movimientos del periodo</a></article>
        @endforeach
    </div>

    <div class="movement-table-wrap report-table-wrap"><table class="movement-table report-table"><thead><tr><th>Periodo</th><th class="movement-table__amount">Presupuesto</th><th class="movement-table__amount">Gasto</th><th class="movement-table__amount">Variación</th><th class="movement-table__amount">Ingresos</th><th class="movement-table__amount">Balance</th><th class="movement-table__amount">Saldo presupuesto</th><th class="movement-table__amount">Inversión aportada</th></tr></thead><tbody>@foreach($comparisons as $item)<tr><td data-label="Periodo"><strong>{{ $item['label'] }}</strong></td><td class="movement-table__amount" data-label="Presupuesto">{{ $money($item['budget_cents']) }}</td><td class="movement-table__amount" data-label="Gasto">{{ $money($item['expense_cents']) }}</td><td class="movement-table__amount" data-label="Variación">{{ $loop->first ? 'Referencia' : $expenseVariation($item['expense_cents']) }}</td><td class="movement-table__amount" data-label="Ingresos">{{ $money($item['income_cents']) }}</td><td class="movement-table__amount" data-label="Balance">{{ $money($item['balance_cents']) }}</td><td class="movement-table__amount" data-label="Saldo presupuesto">{{ $money($item['remaining_cents']) }}</td><td class="movement-table__amount" data-label="Inversión aportada">{{ $money($item['investment_contribution_cents']) }}</td></tr>@endforeach</tbody></table></div>

    <section class="content-section" aria-labelledby="comparison-categories-title">
        <div class="section-heading"><div><p class="eyebrow">Misma clasificación</p><h2 class="section-heading__title" id="comparison-categories-title">Categorías por periodo</h2></div></div>
        @if($comparisonCategories->isEmpty())
            <div class="compact-empty"><p>No hay gasto categorizado en los periodos seleccionados.</p></div>
        @else
            <div class="movement-table-wrap report-table-wrap"><table class="movement-table report-table"><thead><tr><th>Categoría</th>@foreach($comparisons as $item)<th class="movement-table__amount">{{ $item['label'] }}</th>@endforeach</tr></thead><tbody>@foreach($comparisonCategories as $category)<tr><td data-label="Categoría"><strong>{{ $category['name'] }}</strong></td>@foreach($comparisons as $index => $item)@php($categoryDetail = $mode === 'months' ? ['month' => $item['key']] : ['from' => $item['key'].'-01-01', 'to' => $item['key'].'-12-31'])<td class="movement-table__amount" data-label="{{ $item['label'] }}"><a class="text-link" href="{{ route('movements.index', ['project' => $project, ...$categoryDetail, ...$sharedFilters, 'category' => $category['category_id']]) }}">{{ $money($category['values'][$index]) }}</a></td>@endforeach</tr>@endforeach</tbody></table></div>
        @endif
    </section>
@endsection
