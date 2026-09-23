@extends('layouts.app')

@section('title', 'Calendario de '.$project->name)

@section('content')
    @php
        $monthValue = $month->format('Y-m');
        $selectedValue = $selectedDay->toDateString();
        $hasFilters = collect($filters)->contains(fn ($value) => $value !== null);
        $monthRoute = fn ($date) => route('calendar.index', ['project' => $project, 'month' => $date->format('Y-m'), ...$filterQuery]);
        $dayRoute = fn ($date) => route('calendar.index', ['project' => $project, 'month' => $monthValue, 'day' => $date->toDateString(), ...$filterQuery]);
        $weekdays = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        $money = fn (int $cents) => number_format($cents / 100, 2, ',', '.').' €';
        $actualProgress = $summary['budget_cents'] > 0 ? max(0, min(100, (int) round(($summary['actual_expense_cents'] / $summary['budget_cents']) * 100))) : ($summary['actual_expense_cents'] > 0 ? 100 : 0);
        $forecastProgress = $summary['budget_cents'] > 0 ? max(0, min(100, (int) round(($summary['forecast_expense_cents'] / $summary['budget_cents']) * 100))) : ($summary['forecast_expense_cents'] > 0 ? 100 : 0);
    @endphp

    <header class="page-heading">
        <div class="page-heading__copy">
            <a class="back-link" href="{{ route('projects.show', $project) }}">← {{ $project->name }}</a>
            <p class="eyebrow">Planificación temporal</p>
            <h1 class="page-heading__title">Calendario financiero</h1>
            <p class="page-heading__intro">Consulta vencimientos, recurrencias y los movimientos que hayas elegido mostrar.</p>
        </div>
        @if($canManagePlans)
            <a class="button button--primary" href="{{ route('planned-movements.create', $project) }}">+ Nueva planificación</a>
        @endif
    </header>

    @include('projects._navigation', ['project' => $project])

    @if($project->isArchived())
        <div class="alert alert--info" role="note"><p class="alert__title">Proyecto archivado en modo de solo lectura.</p><p>Puedes consultar todo el historial del calendario, pero no modificar sus elementos.</p></div>
    @endif

    <nav class="month-switcher calendar-month-switcher" aria-label="Cambiar mes del calendario">
        <a class="button button--secondary button--small" href="{{ $monthRoute($month->subMonth()) }}">← Anterior</a>
        <div class="month-switcher__current">
            <span class="eyebrow">Calendario mensual</span>
            <strong>{{ ucfirst($month->isoFormat('MMMM [de] YYYY')) }}</strong>
        </div>
        <a class="button button--secondary button--small" href="{{ $monthRoute($month->addMonth()) }}">Siguiente →</a>
        <a class="calendar-month-switcher__today" href="{{ route('calendar.index', ['project' => $project, 'month' => $today->format('Y-m'), 'day' => $today->toDateString(), ...$filterQuery]) }}">Volver a hoy</a>
    </nav>

    <section class="calendar-summary" aria-labelledby="calendar-summary-title">
        <div class="calendar-summary__heading">
            <div>
                <p class="eyebrow">Real frente a estimado</p>
                <h2 id="calendar-summary-title">Previsión del mes</h2>
                <p>Los importes pendientes no modifican todavía las cuentas ni el presupuesto real.</p>
            </div>
            <div class="calendar-summary__budget"><span>Presupuesto mensual</span><strong>{{ $money($summary['budget_cents']) }}</strong><a href="{{ route('budgets.index', ['project' => $project, 'month' => $monthValue]) }}">Ver presupuesto</a></div>
        </div>

        <div class="calendar-summary__progress" role="img" aria-label="Gasto contabilizado: {{ $actualProgress }} % del presupuesto. Estimación con pendientes: {{ $forecastProgress }} %.">
            <span class="calendar-summary__progress-actual" style="width: {{ $actualProgress }}%"></span>
            <span class="calendar-summary__progress-pending" style="left: {{ $actualProgress }}%; width: {{ max(0, $forecastProgress - $actualProgress) }}%"></span>
        </div>
        <div class="calendar-summary__progress-labels">
            <span><i class="calendar-summary__key calendar-summary__key--actual" aria-hidden="true"></i>Contabilizado: {{ $money($summary['actual_expense_cents']) }}</span>
            <span><i class="calendar-summary__key calendar-summary__key--pending" aria-hidden="true"></i>Estimación final: {{ $money($summary['forecast_expense_cents']) }}</span>
        </div>

        <div class="calendar-summary__groups">
            <section class="calendar-summary-group calendar-summary-group--actual" aria-labelledby="calendar-actual-title">
                <div class="calendar-summary-group__heading"><span class="calendar-summary-group__icon" aria-hidden="true">✓</span><div><small>Datos contabilizados</small><h3 id="calendar-actual-title">Situación real</h3></div></div>
                <div class="calendar-summary-group__metrics">
                    <article class="calendar-summary-metric"><span>Realizados en calendario</span><strong>{{ $money($summary['calendar_expense_cents']) }}</strong><small>{{ $summary['calendar_expense_count'] }} {{ $summary['calendar_expense_count'] === 1 ? 'operación seleccionada o vinculada' : 'operaciones seleccionadas o vinculadas' }}</small></article>
                    <article class="calendar-summary-metric {{ $summary['actual_remaining_cents'] < 0 ? 'calendar-summary-metric--danger' : '' }}"><span>Disponible real</span><strong>{{ $money($summary['actual_remaining_cents']) }}</strong><small>Presupuesto menos todo el gasto neto contabilizado</small></article>
                </div>
            </section>

            <section class="calendar-summary-group calendar-summary-group--forecast" aria-labelledby="calendar-forecast-title">
                <div class="calendar-summary-group__heading"><span class="calendar-summary-group__icon" aria-hidden="true">◷</span><div><small>Datos no contabilizados</small><h3 id="calendar-forecast-title">Estimación al finalizar el mes</h3></div></div>
                <div class="calendar-summary-group__metrics">
                    <article class="calendar-summary-metric"><span>Gastos pendientes</span><strong>{{ $money($summary['pending_expense_cents']) }}</strong><small>{{ $summary['pending_expense_count'] }} {{ $summary['pending_expense_count'] === 1 ? 'previsión pendiente' : 'previsiones pendientes' }}</small></article>
                    <article class="calendar-summary-metric"><span>Gasto previsto total</span><strong>{{ $money($summary['forecast_expense_cents']) }}</strong><small>Gasto real más previsiones pendientes</small></article>
                    <article class="calendar-summary-metric calendar-summary-metric--income"><span>Ingresos previstos</span><strong>{{ $money($summary['pending_income_cents']) }}</strong><small>{{ $summary['pending_income_count'] }} {{ $summary['pending_income_count'] === 1 ? 'previsión pendiente' : 'previsiones pendientes' }} · no amplían el presupuesto</small></article>
                    <article class="calendar-summary-metric {{ $summary['forecast_remaining_cents'] < 0 ? 'calendar-summary-metric--danger' : 'calendar-summary-metric--highlight' }}"><span>Disponible estimado</span><strong>{{ $money($summary['forecast_remaining_cents']) }}</strong><small>Disponible real menos gastos pendientes</small></article>
                </div>
            </section>
        </div>
        <p class="calendar-summary__note">El disponible real incluye todos los gastos contabilizados del mes, aunque no estén marcados para aparecer en el calendario. Los filtros inferiores solo modifican los eventos mostrados.</p>
    </section>

    <form class="filter-panel calendar-filters" action="{{ route('calendar.index', $project) }}" method="get">
        <input type="hidden" name="month" value="{{ $monthValue }}">
        <div class="calendar-filters__grid">
            <label class="field"><span class="field__label">Estado</span><select class="field__control" name="status"><option value="">Todos</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>@endforeach</select></label>
            <label class="field"><span class="field__label">Tipo</span><select class="field__control" name="type"><option value="">Todos</option>@foreach($types as $type)<option value="{{ $type->value }}" @selected($filters['type'] === $type->value)>{{ $type->label() }}</option>@endforeach</select></label>
            <label class="field"><span class="field__label">Cuenta</span><select class="field__control" name="account"><option value="">Todas</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected($filters['account'] === $account->id)>{{ $account->name }}{{ $account->archived_at ? ' (archivada)' : '' }}</option>@endforeach</select></label>
            <label class="field"><span class="field__label">Categoría</span><select class="field__control" name="category"><option value="">Todas</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected($filters['category'] === $category->id)>{{ $category->name }}{{ $category->archived_at ? ' (archivada)' : '' }}</option>@endforeach</select></label>
            <label class="field"><span class="field__label">Miembro</span><select class="field__control" name="member"><option value="">Todos</option>@foreach($members as $member)<option value="{{ $member->id }}" @selected($filters['member'] === $member->id)>{{ $member->name }}</option>@endforeach</select></label>
            <button class="button button--secondary calendar-filters__submit" type="submit">Aplicar filtros</button>
        </div>
        <div class="filter-panel__footer">
            <p><strong>{{ $eventsCount }}</strong> {{ $eventsCount === 1 ? 'evento visible' : 'eventos visibles' }} en este mes.</p>
            @if($hasFilters)<a class="text-link" href="{{ route('calendar.index', ['project' => $project, 'month' => $monthValue]) }}">Limpiar filtros</a>@endif
        </div>
    </form>

    <div class="calendar-legend" aria-label="Leyenda de estados">
        @foreach($statuses as $status)<span class="calendar-legend__item"><i class="calendar-legend__dot calendar-legend__dot--{{ $status->value }}" aria-hidden="true"></i>{{ $status->label() }}</span>@endforeach
    </div>

    <section class="calendar-desktop" aria-label="Vista mensual y detalle del día">
        <div class="calendar-workspace">
            <div class="calendar-grid-wrap">
                <table class="calendar-grid">
                    <caption class="sr-only">Calendario de {{ $month->isoFormat('MMMM [de] YYYY') }}</caption>
                    <thead><tr>@foreach($weekdays as $weekday)<th scope="col">{{ $weekday }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach($weeks as $week)
                            <tr>
                                @foreach($week as $day)
                                    @php($dayEvents = $eventsByDay->get($day['date']->toDateString(), collect()))
                                    <td class="calendar-day {{ !$day['current_month'] ? 'calendar-day--outside' : '' }} {{ $day['today'] ? 'calendar-day--today' : '' }} {{ $selectedValue === $day['date']->toDateString() ? 'calendar-day--selected' : '' }}">
                                        @if($day['current_month'])
                                            <a class="calendar-day__number" href="{{ $dayRoute($day['date']) }}" aria-label="Ver {{ $day['date']->isoFormat('dddd D [de] MMMM') }}{{ $dayEvents->isNotEmpty() ? ', '.$dayEvents->count().' eventos' : ', sin eventos' }}" @if($selectedValue === $day['date']->toDateString()) aria-current="date" @endif>{{ $day['date']->day }}</a>
                                            <div class="calendar-day__events">
                                                @foreach($dayEvents->take(3) as $event)
                                                    <a class="calendar-chip calendar-chip--{{ $event['status'] }}" href="{{ $event['url'] }}" title="{{ $event['concept'] }} · {{ $event['amount_formatted'] }} · {{ $event['status_label'] }}">
                                                        <span class="calendar-chip__dot" aria-hidden="true"></span><span class="calendar-chip__concept">{{ $event['concept'] }}</span><strong>{{ $event['amount_formatted'] }}</strong>
                                                    </a>
                                                @endforeach
                                                @if($dayEvents->count() > 3)<a class="calendar-day__more" href="{{ $dayRoute($day['date']) }}">+ {{ $dayEvents->count() - 3 }} más</a>@endif
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <aside class="calendar-detail" aria-labelledby="calendar-detail-title">
                <div class="calendar-detail__heading">
                    <p class="eyebrow">Día seleccionado</p>
                    <h2 id="calendar-detail-title">{{ ucfirst($selectedDay->isoFormat('dddd D [de] MMMM')) }}</h2>
                    <span>{{ $selectedEvents->count() }} {{ $selectedEvents->count() === 1 ? 'evento' : 'eventos' }}</span>
                </div>
                @if($selectedEvents->isEmpty())
                    <div class="calendar-detail__empty"><span aria-hidden="true">○</span><p>No hay elementos de calendario para este día.</p></div>
                @else
                    <div class="calendar-detail__events">@foreach($selectedEvents as $event)@include('calendar._event-detail', ['event' => $event])@endforeach</div>
                @endif
            </aside>
        </div>
    </section>

    <section class="calendar-agenda" aria-labelledby="calendar-agenda-title">
        <div class="section-heading"><div><p class="eyebrow">Vista móvil</p><h2 class="section-heading__title" id="calendar-agenda-title">Agenda del mes</h2></div></div>
        @if($agendaDays->isEmpty())
            <div class="empty-state calendar-agenda__empty"><span class="empty-state__icon" aria-hidden="true">◷</span><h3>No hay eventos en este mes</h3><p>Prueba a limpiar los filtros o crea una planificación.</p></div>
        @else
            <div class="calendar-agenda__days">
                @foreach($agendaDays as $agendaDay)
                    <section class="calendar-agenda-day" aria-labelledby="agenda-day-{{ $agendaDay['date']->format('Ymd') }}">
                        <header class="calendar-agenda-day__heading"><time datetime="{{ $agendaDay['date']->toDateString() }}"><strong>{{ $agendaDay['date']->day }}</strong><span id="agenda-day-{{ $agendaDay['date']->format('Ymd') }}">{{ ucfirst($agendaDay['date']->isoFormat('dddd')) }}<small>{{ ucfirst($agendaDay['date']->isoFormat('MMMM')) }}</small></span></time><span>{{ $agendaDay['events']->count() }}</span></header>
                        <div class="calendar-agenda-day__events">@foreach($agendaDay['events'] as $event)@include('calendar._event-detail', ['event' => $event])@endforeach</div>
                    </section>
                @endforeach
            </div>
        @endif
    </section>
@endsection
