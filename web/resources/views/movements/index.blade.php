@extends('layouts.app')

@section('title', 'Movimientos de '.$project->name)

@section('content')
    @php($formatMoney = fn (int $cents) => number_format($cents / 100, 2, ',', '.').' €')
    <header class="page-heading">
        <div class="page-heading__copy">
            <a class="back-link" href="{{ route('projects.show', $project) }}">← {{ $project->name }}</a>
            <p class="eyebrow">Registro financiero</p>
            <h1 class="page-heading__title">Movimientos</h1>
            <p class="page-heading__intro">Consulta gastos, ingresos, transferencias y devoluciones sin mezclar proyectos.</p>
        </div>
        <div class="button-group"><a class="button button--quiet" href="{{ route('planned-movements.index', $project) }}">Planificaciones</a>@can('recordMovements', $project)<a class="button button--secondary" href="{{ route('movements.transfer.create', $project) }}">↔ Transferir</a><a class="button button--primary" href="{{ route('movements.create', $project) }}">+ Añadir gasto o ingreso</a>@endcan<a class="button button--quiet" href="{{ route('movements.trash', $project) }}">Papelera</a></div>
    </header>

    @include('projects._navigation', ['project' => $project])

    @if($customRange)
        <div class="range-heading"><div><span class="eyebrow">Periodo personalizado</span><strong>{{ $rangeStart->format('d/m/Y') }} — {{ $rangeEnd->format('d/m/Y') }}</strong></div><a class="button button--secondary button--small" href="{{ route('movements.index', ['project' => $project, 'month' => $month->format('Y-m')]) }}">Volver al mes</a></div>
    @else
        <nav class="month-switcher" aria-label="Cambiar mes">
            <a class="button button--secondary button--small" href="{{ route('movements.index', array_merge(request()->except(['page', 'from', 'to']), ['project' => $project, 'month' => $month->subMonth()->format('Y-m')])) }}">← Anterior</a>
            <div class="month-switcher__current"><span class="eyebrow">Mes consultado</span><strong>{{ ucfirst($month->locale('es')->translatedFormat('F Y')) }}</strong></div>
            <a class="button button--secondary button--small" href="{{ route('movements.index', array_merge(request()->except(['page', 'from', 'to']), ['project' => $project, 'month' => $month->addMonth()->format('Y-m')])) }}">Siguiente →</a>
        </nav>
    @endif

    <form class="filter-panel" method="get" action="{{ route('movements.index', $project) }}">
        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
        <div class="filter-panel__grid">
            <div class="field"><label class="field__label" for="filter-type">Tipo</label><select class="field__control" id="filter-type" name="type"><option value="">Todos</option>@foreach (\App\Enums\MovementType::cases() as $type)<option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>@endforeach</select></div>
            <div class="field"><label class="field__label" for="filter-account">Cuenta</label><select class="field__control" id="filter-account" name="account"><option value="">Todas</option>@foreach ($accounts as $account)<option value="{{ $account->id }}" @selected((string) request('account') === (string) $account->id)>{{ $account->name }}</option>@endforeach</select></div>
            <div class="field"><label class="field__label" for="filter-category">Categoría</label><select class="field__control" id="filter-category" name="category"><option value="">Todas</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) request('category') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
            <div class="field"><label class="field__label" for="filter-member">Miembro</label><select class="field__control" id="filter-member" name="member"><option value="">Todos</option>@foreach ($members as $member)<option value="{{ $member->id }}" @selected((string) request('member') === (string) $member->id)>{{ $member->name }}</option>@endforeach</select></div>
            <div class="field"><label class="field__label" for="filter-tag">Etiqueta</label><select class="field__control" id="filter-tag" name="tag"><option value="">Todas</option>@foreach ($tags as $tag)<option value="{{ $tag->id }}" @selected((string) request('tag') === (string) $tag->id)># {{ $tag->name }}{{ $tag->archived_at ? ' (archivada)' : '' }}</option>@endforeach</select></div>
            <div class="field"><label class="field__label" for="filter-from">Desde <span class="field__optional">opcional</span></label><input class="field__control" id="filter-from" name="from" type="date" value="{{ request('from') }}"></div>
            <div class="field"><label class="field__label" for="filter-to">Hasta <span class="field__optional">opcional</span></label><input class="field__control" id="filter-to" name="to" type="date" value="{{ request('to') }}"></div>
            <div class="field filter-panel__search"><label class="field__label" for="filter-search">Buscar concepto</label><input class="field__control" id="filter-search" name="search" value="{{ request('search') }}"></div>
            <button class="button button--secondary filter-panel__button" type="submit">Aplicar filtros</button>
        </div>
        <div class="filter-panel__footer">
            <p>La vista exportada respeta el mes y todos los filtros aplicados.</p>
            <div class="button-group"><a class="button button--secondary button--small" href="{{ route('movements.export', array_merge(request()->query(), ['project' => $project, 'scope' => 'filtered', 'month' => $month->format('Y-m')])) }}">Exportar esta vista CSV</a><a class="button button--quiet button--small" href="{{ route('movements.export', ['project' => $project, 'scope' => 'all']) }}">Exportar todo el proyecto</a></div>
        </div>
    </form>

    @if ($movements->isEmpty())
        <section class="empty-state">
            <span class="empty-state__icon" aria-hidden="true">↕</span>
            <h2 class="empty-state__title">No hay movimientos en este periodo</h2>
            <p class="empty-state__text">Añade el primer gasto o ingreso, o cambia el periodo para consultar el historial.</p>
            @can('recordMovements', $project)<a class="button button--primary" href="{{ route('movements.create', $project) }}">Añadir movimiento</a>@endcan
        </section>
    @else
        <div class="movement-table-wrap">
            <table class="movement-table">
                <thead><tr><th>Fecha</th><th>Concepto</th><th>Categoría</th><th>Cuenta</th><th>Miembro</th><th class="movement-table__amount">Importe</th><th>Acciones</th></tr></thead>
                <tbody>
                    @foreach ($movements as $movement)
                        <tr id="movement-{{ $movement->id }}">
                            <td data-label="Fecha">{{ $movement->occurred_on->format('d/m/Y') }}</td>
                            <td data-label="Concepto"><strong>{{ $movement->concept }}</strong><span class="movement-type movement-type--{{ $movement->type->value }}">{{ $movement->type->label() }}</span>@if($movement->tags->isNotEmpty())<span class="movement-tags">@foreach($movement->tags as $tag)<span class="tag-chip tag-chip--small"># {{ $tag->name }}</span>@endforeach</span>@endif @if($movement->generated_automatically_at)<small>Generado automáticamente · visible en el calendario</small>@elseif($movement->plannedMovement)<small>Realizado desde una planificación · visible en el calendario</small>@elseif($movement->show_in_calendar)<small>Seleccionado para el calendario</small>@endif</td>
                            <td data-label="Categoría">{{ $movement->category?->name ?? '—' }}@if ($movement->subcategory)<small>{{ $movement->subcategory->name }}</small>@elseif ($movement->originalMovement)<small>Vinculada a {{ $movement->originalMovement->concept }}</small>@endif</td>
                            <td data-label="Cuenta">{{ $movement->account?->name }}@if ($movement->destinationAccount)<small>→ {{ $movement->destinationAccount->name }}</small>@endif</td>
                            <td data-label="Miembro">{{ $movement->paidBy?->name ?? '—' }}</td>
                            @php($amountPrefix = match($movement->type) { \App\Enums\MovementType::Expense => '−', \App\Enums\MovementType::Income, \App\Enums\MovementType::Refund => '+', default => '' })
                            <td class="movement-table__amount movement-table__amount--{{ $movement->type->value }}" data-label="Importe">{{ $amountPrefix }}{{ $movement->formattedAmount() }}</td>
                            <td data-label="Acciones"><div class="table-actions">@can('recordMovements', $project)<a class="text-link" href="{{ route('movements.edit', [$project, $movement]) }}">Editar</a>@if ($movement->type === \App\Enums\MovementType::Expense && (int) $movement->refunded_cents < $movement->amount_cents)<a class="text-link" href="{{ route('movements.refund.create', [$project, $movement]) }}">Devolver</a>@endif<form action="{{ route('movements.destroy', [$project, $movement]) }}" method="post" onsubmit="return confirm('¿Enviar este movimiento a la papelera durante 30 días?')">@csrf @method('delete')<button class="link-button link-button--danger" type="submit">Retirar</button></form>@endcan</div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $movements->links() }}
    @endif
@endsection
