@extends('layouts.app')

@section('title', 'Informe anual '.$year.' de '.$project->name)

@section('content')
    @php
        $money = fn (int $cents) => number_format($cents / 100, 2, ',', '.').' €';
        $sharedFilters = array_filter($filters, fn ($value) => $value !== null);
        $detailBase = ['from' => $year.'-01-01', 'to' => $year.'-12-31', ...$sharedFilters];
        $chartMax = max(1, (int) $report['months']->max(fn ($item) => max($item['expense_cents'], $item['income_cents'])));
        $expensePoints = $report['months']->values()->map(fn ($item, $index) => (30 + ($index * (600 / 11))).','.round(190 - (($item['expense_cents'] / $chartMax) * 150), 2))->implode(' ');
        $incomePoints = $report['months']->values()->map(fn ($item, $index) => (30 + ($index * (600 / 11))).','.round(190 - (($item['income_cents'] / $chartMax) * 150), 2))->implode(' ');
    @endphp
    <header class="page-heading"><div class="page-heading__copy"><a class="back-link" href="{{ route('projects.show', $project) }}">← {{ $project->name }}</a><p class="eyebrow">Análisis financiero</p><h1 class="page-heading__title">Informes</h1><p class="page-heading__intro">Revisa el año natural sin borrar ni reiniciar el historial al cambiar de ejercicio.</p></div></header>
    @include('projects._navigation', ['project' => $project])
    @include('reports._tabs')

    <nav class="month-switcher report-period-switcher" aria-label="Cambiar año del informe"><a class="button button--secondary button--small" href="{{ route('reports.annual', ['project' => $project, 'year' => $year - 1, ...$sharedFilters]) }}">← {{ $year - 1 }}</a><div class="month-switcher__current"><span class="eyebrow">Año natural</span><strong>{{ $year }}</strong></div><a class="button button--secondary button--small" href="{{ route('reports.annual', ['project' => $project, 'year' => $year + 1, ...$sharedFilters]) }}">{{ $year + 1 }} →</a></nav>
    <form class="filter-panel report-filter" action="{{ route('reports.annual', $project) }}" method="get"><input type="hidden" name="year" value="{{ $year }}"><div class="filter-panel__grid">@include('reports._filter_fields')<button class="button button--secondary filter-panel__button" type="submit">Aplicar filtros</button></div></form>

    @if($report['months']->contains('planned', true))<div class="alert alert--info" role="note"><p class="alert__title">Resultados reales y planificación están separados.</p><p>Los meses futuros muestran presupuesto previsto, pero no se incluyen en el saldo presupuestario real acumulado.</p></div>@endif
    @include('reports._summary', ['budgetCaption' => $report['actual_budget_cents'] === $report['budget_cents'] ? 'presupuesto anual' : 'plan anual; '.$money($report['actual_budget_cents']).' transcurrido', 'remainingCaption' => 'sobre los meses transcurridos'])

    <section class="content-section" aria-labelledby="annual-evolution-title">
        <div class="section-heading"><div><p class="eyebrow">Evolución</p><h2 class="section-heading__title" id="annual-evolution-title">Ingresos y gasto por mes</h2></div></div>
        <div class="line-chart"><svg viewBox="0 0 660 230" role="img" aria-labelledby="annual-chart-title annual-chart-desc"><title id="annual-chart-title">Evolución mensual de {{ $year }}</title><desc id="annual-chart-desc">Línea verde para ingresos y línea roja para gasto neto. La tabla posterior contiene los valores exactos.</desc><line x1="30" y1="190" x2="630" y2="190" class="line-chart__axis"/><polyline points="{{ $expensePoints }}" class="line-chart__line line-chart__line--expense"/><polyline points="{{ $incomePoints }}" class="line-chart__line line-chart__line--income"/>@foreach($report['months']->values() as $index => $item)<text x="{{ 30 + ($index * (600 / 11)) }}" y="215" text-anchor="middle">{{ mb_substr($item['label'], 0, 3) }}</text>@endforeach</svg><div class="line-chart__legend"><span><i class="line-chart__key line-chart__key--income"></i>Ingresos</span><span><i class="line-chart__key line-chart__key--expense"></i>Gasto neto</span></div></div>
        <div class="movement-table-wrap report-table-wrap"><table class="movement-table report-table"><thead><tr><th>Mes</th><th class="movement-table__amount">Presupuesto</th><th class="movement-table__amount">Gasto neto</th><th class="movement-table__amount">Ingresos</th><th class="movement-table__amount">Balance</th><th>Estado</th></tr></thead><tbody>@foreach($report['months'] as $item)<tr><td data-label="Mes"><a class="text-link" href="{{ route('movements.index', ['project' => $project, 'month' => $item['key'], ...$sharedFilters]) }}">{{ $item['label'] }}</a></td><td class="movement-table__amount" data-label="Presupuesto">{{ $money($item['budget_cents']) }}</td><td class="movement-table__amount" data-label="Gasto neto">{{ $money($item['expense_cents']) }}</td><td class="movement-table__amount" data-label="Ingresos">{{ $money($item['income_cents']) }}</td><td class="movement-table__amount" data-label="Balance">{{ $money($item['balance_cents']) }}</td><td data-label="Estado"><span class="badge {{ $item['planned'] ? 'badge--muted' : '' }}">{{ $item['planned'] ? 'Planificación' : 'Real' }}</span></td></tr>@endforeach</tbody></table></div>
    </section>

    @if($report['months']->contains(fn ($item) => $item['investment_contribution_cents'] !== 0 || $item['investment_withdrawal_cents'] !== 0 || $item['savings_contribution_cents'] !== 0 || $item['savings_withdrawal_cents'] !== 0))
        <section class="content-section" aria-labelledby="annual-special-evolution-title">
            <div class="section-heading"><div><p class="eyebrow">Flujos independientes</p><h2 class="section-heading__title" id="annual-special-evolution-title">Evolución de ahorro e inversión</h2></div></div>
            <div class="movement-table-wrap report-table-wrap"><table class="movement-table report-table"><thead><tr><th>Mes</th><th class="movement-table__amount">Ahorro aportado</th><th class="movement-table__amount">Ahorro retirado</th><th class="movement-table__amount">Inversión aportada</th><th class="movement-table__amount">Inversión retirada</th></tr></thead><tbody>@foreach($report['months'] as $item)<tr><td data-label="Mes"><a class="text-link" href="{{ route('movements.index', ['project' => $project, 'month' => $item['key'], ...$sharedFilters]) }}">{{ $item['label'] }}</a></td><td class="movement-table__amount" data-label="Ahorro aportado">{{ $money($item['savings_contribution_cents']) }}</td><td class="movement-table__amount" data-label="Ahorro retirado">{{ $money($item['savings_withdrawal_cents']) }}</td><td class="movement-table__amount" data-label="Inversión aportada">{{ $money($item['investment_contribution_cents']) }}</td><td class="movement-table__amount" data-label="Inversión retirada">{{ $money($item['investment_withdrawal_cents']) }}</td></tr>@endforeach</tbody></table></div>
        </section>
    @endif

    @include('reports._categories', ['detailBase' => $detailBase])
    @include('reports._special_operations')
@endsection
