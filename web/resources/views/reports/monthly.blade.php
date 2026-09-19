@extends('layouts.app')

@section('title', 'Informe mensual de '.$project->name)

@section('content')
    @php
        $money = fn (int $cents) => number_format($cents / 100, 2, ',', '.').' €';
        $sharedFilters = array_filter($filters, fn ($value) => $value !== null);
        $detailBase = ['month' => $month->format('Y-m'), ...$sharedFilters];
        $difference = function (int $current, int $reference) use ($money): string {
            $delta = $current - $reference;
            if ($reference === 0) return ($delta >= 0 ? '+' : '').$money($delta).' · sin base porcentual';
            $percentage = round(($delta / abs($reference)) * 100, 1);
            return ($delta >= 0 ? '+' : '').$money($delta).' · '.($percentage >= 0 ? '+' : '').number_format($percentage, 1, ',', '.').' %';
        };
    @endphp
    <header class="page-heading"><div class="page-heading__copy"><a class="back-link" href="{{ route('projects.show', $project) }}">← {{ $project->name }}</a><p class="eyebrow">Análisis financiero</p><h1 class="page-heading__title">Informes</h1><p class="page-heading__intro">Comprende dónde se va el dinero y abre el detalle que forma cada cifra.</p></div></header>
    @include('projects._navigation', ['project' => $project])
    @include('reports._tabs')

    <nav class="month-switcher report-period-switcher" aria-label="Cambiar mes del informe">
        <a class="button button--secondary button--small" href="{{ route('reports.monthly', ['project' => $project, 'month' => $month->subMonth()->format('Y-m'), ...$sharedFilters]) }}">← Anterior</a>
        <div class="month-switcher__current"><span class="eyebrow">Informe mensual</span><strong>{{ $report['label'] }}</strong></div>
        <a class="button button--secondary button--small" href="{{ route('reports.monthly', ['project' => $project, 'month' => $month->addMonth()->format('Y-m'), ...$sharedFilters]) }}">Siguiente →</a>
    </nav>

    <form class="filter-panel report-filter" action="{{ route('reports.monthly', $project) }}" method="get"><input type="hidden" name="month" value="{{ $month->format('Y-m') }}"><div class="filter-panel__grid">@include('reports._filter_fields')<button class="button button--secondary filter-panel__button" type="submit">Aplicar filtros</button></div></form>

    @include('reports._summary', ['budgetCaption' => 'presupuesto completo del mes', 'remainingCaption' => 'saldo global del mes'])

    <section class="content-section" aria-labelledby="automatic-comparison-title">
        <div class="section-heading"><div><p class="eyebrow">Cambio automático</p><h2 class="section-heading__title" id="automatic-comparison-title">Comparación del gasto neto</h2></div><a class="text-link" href="{{ route('reports.compare', ['project' => $project, 'mode' => 'months', 'periods' => [$report['key'], $previous['key'], $lastYear['key']], ...$sharedFilters]) }}">Abrir comparador</a></div>
        <div class="period-comparisons">
            <a class="period-comparison" href="{{ route('movements.index', ['project' => $project, 'month' => $previous['key'], ...$sharedFilters]) }}"><small>Frente a {{ $previous['label'] }}</small><strong>{{ $difference($report['expense_cents'], $previous['expense_cents']) }}</strong><span>Antes: {{ $money($previous['expense_cents']) }}</span></a>
            <a class="period-comparison" href="{{ route('movements.index', ['project' => $project, 'month' => $lastYear['key'], ...$sharedFilters]) }}"><small>Frente a {{ $lastYear['label'] }}</small><strong>{{ $difference($report['expense_cents'], $lastYear['expense_cents']) }}</strong><span>Antes: {{ $money($lastYear['expense_cents']) }}</span></a>
        </div>
    </section>

    @include('reports._categories', ['detailBase' => $detailBase])
    @include('reports._special_operations')
@endsection
