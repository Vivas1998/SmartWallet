@extends('layouts.app')

@section('title', 'Planificaciones de '.$project->name)

@section('content')
    <header class="page-heading">
        <div class="page-heading__copy">
            <a class="back-link" href="{{ route('movements.index', $project) }}">← Movimientos</a>
            <p class="eyebrow">Previsión financiera</p>
            <h1 class="page-heading__title">Planificaciones puntuales</h1>
            <p class="page-heading__intro">Prepara operaciones futuras sin modificar las cuentas, el presupuesto ni los informes hasta que se realicen.</p>
        </div>
        <div class="button-group">
            <a class="button button--secondary" href="{{ route('recurrences.index', $project) }}">Ver recurrentes</a>
            @if($canManage)<a class="button button--primary" href="{{ route('planned-movements.create', $project) }}">+ Nueva planificación</a>@endif
        </div>
    </header>

    @include('projects._navigation', ['project' => $project])

    <div class="alert alert--info" role="note">
        <p class="alert__title">Las planificaciones son únicamente previsiones.</p>
        <p>Cuando conozcas el importe y la fecha reales, utiliza «Registrar como realizado» para contabilizarlas.</p>
    </div>

    @if($plannedMovements->isEmpty())
        <section class="empty-state">
            <span class="empty-state__icon" aria-hidden="true">◷</span>
            <h2 class="empty-state__title">No hay planificaciones puntuales</h2>
            <p class="empty-state__text">Añade pagos, cobros o transferencias que quieras recordar en una fecha concreta.</p>
            @if($canManage)<a class="button button--primary" href="{{ route('planned-movements.create', $project) }}">Crear la primera planificación</a>@endif
        </section>
    @else
        <div class="movement-table-wrap">
            <table class="movement-table">
                <thead><tr><th>Vencimiento</th><th>Concepto</th><th>Clasificación</th><th>Cuenta</th><th class="movement-table__amount">Importe previsto</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                    @foreach($plannedMovements as $plan)
                        @php($overdue = $plan->status === \App\Enums\PlannedMovementStatus::Pending && $plan->due_on->isBefore(today('Europe/Madrid')))
                        <tr id="planned-movement-{{ $plan->id }}">
                            <td data-label="Vencimiento">{{ $plan->due_on->format('d/m/Y') }}@if($plan->movement && $plan->movement->occurred_on->toDateString() !== $plan->due_on->toDateString())<small>Real: {{ $plan->movement->occurred_on->format('d/m/Y') }}</small>@endif</td>
                            <td data-label="Concepto"><strong>{{ $plan->concept }}</strong><span class="movement-type movement-type--{{ $plan->type->value }}">{{ $plan->type->label() }}</span>@if($plan->tags->isNotEmpty())<span class="movement-tags">@foreach($plan->tags as $tag)<span class="tag-chip tag-chip--small"># {{ $tag->name }}</span>@endforeach</span>@endif</td>
                            <td data-label="Clasificación">{{ $plan->category?->name ?? '—' }}@if($plan->subcategory)<small>{{ $plan->subcategory->name }}</small>@elseif($plan->paidBy)<small>{{ $plan->paidBy->name }}</small>@endif</td>
                            <td data-label="Cuenta">{{ $plan->account?->name ?? '—' }}@if($plan->destinationAccount)<small>→ {{ $plan->destinationAccount->name }}</small>@endif</td>
                            <td class="movement-table__amount movement-table__amount--{{ $plan->type->value }}" data-label="Importe previsto">{{ $plan->formattedAmount() }}@if($plan->movement && $plan->movement->amount_cents !== $plan->amount_cents)<small>Real: {{ $plan->movement->formattedAmount() }}</small>@endif</td>
                            <td data-label="Estado">
                                @if($plan->status === \App\Enums\PlannedMovementStatus::Completed)<span class="badge badge--success">Realizado</span>
                                @elseif($plan->status === \App\Enums\PlannedMovementStatus::Cancelled)<span class="badge badge--muted">Cancelado</span>
                                @elseif($overdue)<span class="badge badge--warning">Vencido</span>
                                @else<span class="badge">Previsto</span>@endif
                            </td>
                            <td data-label="Acciones">
                                <div class="table-actions">
                                    @if($canManage && $plan->status === \App\Enums\PlannedMovementStatus::Pending)
                                        <a class="text-link" href="{{ route('planned-movements.complete', [$project, $plan]) }}">Registrar como realizado</a>
                                        <a class="text-link" href="{{ route('planned-movements.edit', [$project, $plan]) }}">Editar</a>
                                        <form action="{{ route('planned-movements.cancel', [$project, $plan]) }}" method="post" data-confirm="¿Cancelar esta planificación? Permanecerá visible en el historial.">@csrf<button class="link-button link-button--danger" type="submit">Cancelar</button></form>
                                    @elseif($canManage && $plan->movement)
                                        <a class="text-link" href="{{ route('movements.edit', [$project, $plan->movement]) }}">Editar movimiento real</a>
                                    @else
                                        <span>—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $plannedMovements->links() }}
    @endif
@endsection
