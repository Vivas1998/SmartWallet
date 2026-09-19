@extends('layouts.app')

@section('title', 'Papelera de '.$project->name)

@section('content')
    <header class="page-heading"><div class="page-heading__copy"><a class="back-link" href="{{ route('movements.index', $project) }}">← Movimientos</a><p class="eyebrow">Conservación durante 30 días</p><h1 class="page-heading__title">Papelera</h1><p class="page-heading__intro">Estos movimientos ya no afectan a saldos, presupuestos ni resúmenes.</p></div>@can('exportTrash', $project)<a class="button button--secondary" href="{{ route('movements.trash.export', $project) }}">Exportar papelera CSV</a>@endcan</header>
    @include('projects._navigation', ['project' => $project])

    @if ($movements->isEmpty())
        <section class="empty-state"><span class="empty-state__icon" aria-hidden="true">♲</span><h2 class="empty-state__title">La papelera está vacía</h2><p class="empty-state__text">Los movimientos que retires aparecerán aquí hasta su eliminación automática.</p></section>
    @else
        <div class="movement-table-wrap"><table class="movement-table"><thead><tr><th>Movimiento</th><th>Cuenta</th><th>Retirado por</th><th>Eliminación definitiva</th><th class="movement-table__amount">Importe</th><th>Acción</th></tr></thead><tbody>
            @foreach ($movements as $movement)
                <tr><td data-label="Movimiento"><strong>{{ $movement->concept }}</strong><small>{{ $movement->type->label() }} · {{ $movement->occurred_on->format('d/m/Y') }}</small></td><td data-label="Cuenta">{{ $movement->account?->name }}@if ($movement->destinationAccount) → {{ $movement->destinationAccount->name }}@endif</td><td data-label="Retirado por">{{ $movement->deletedBy?->name ?? 'Sistema' }}</td><td data-label="Eliminación definitiva"><strong>{{ $movement->purge_at?->format('d/m/Y H:i') }}</strong><small>{{ $movement->purge_at?->diffForHumans() }}</small></td><td class="movement-table__amount" data-label="Importe">{{ $movement->formattedAmount() }}</td><td data-label="Acción">@can('recordMovements', $project)<form action="{{ route('movements.restore', [$project, $movement]) }}" method="post">@csrf<button class="button button--secondary button--small" type="submit">Restaurar</button></form>@endcan</td></tr>
            @endforeach
        </tbody></table></div>
        {{ $movements->links() }}
    @endif
@endsection
