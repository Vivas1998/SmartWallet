@extends('layouts.app')

@section('title', 'Movimientos recurrentes de '.$project->name)

@section('content')
    <header class="page-heading">
        <div class="page-heading__copy">
            <a class="back-link" href="{{ route('projects.show', $project) }}">← {{ $project->name }}</a>
            <p class="eyebrow">Automatización local</p>
            <h1 class="page-heading__title">Movimientos recurrentes</h1>
            <p class="page-heading__intro">SmartWallet crea cada aparición en su fecha y recupera las vencidas al volver a encenderse.</p>
        </div>
        @if ($canManage)<a class="button button--primary" href="{{ route('recurrences.create', $project) }}">+ Nueva serie</a>@endif
    </header>

    @include('projects._navigation', ['project' => $project])

    <div class="alert alert--info" role="note"><p class="alert__title">Cada aparición se puede corregir por separado.</p><p>Editar un movimiento generado no altera la serie futura. Pausar una serie omite las fechas transcurridas durante la pausa.</p></div>

    @if ($templates->isEmpty())
        <div class="empty-state"><span class="empty-state__icon" aria-hidden="true">↻</span><h2>Aún no hay movimientos recurrentes</h2><p>Automatiza gastos, ingresos, transferencias o aportaciones periódicas.</p>@if($canManage)<a class="button button--primary" href="{{ route('recurrences.create', $project) }}">Crear la primera serie</a>@endif</div>
    @else
        <div class="recurrence-list">
            @foreach ($templates as $recurrence)
                <article class="recurrence-card {{ $recurrence->paused_at ? 'recurrence-card--paused' : '' }}" id="recurrence-{{ $recurrence->id }}">
                    <div class="recurrence-card__identity">
                        <span class="recurrence-card__mark recurrence-card__mark--{{ $recurrence->type->value }}" aria-hidden="true">{{ in_array($recurrence->type, [\App\Enums\MovementType::Transfer, \App\Enums\MovementType::InvestmentContribution], true) ? '↔' : ($recurrence->type === \App\Enums\MovementType::Income ? '↑' : '↓') }}</span>
                        <div><div class="recurrence-card__title"><h2>{{ $recurrence->concept }}</h2>@if($recurrence->paused_at)<span class="badge badge--muted">Pausada</span>@elseif($recurrence->next_occurrence_on === null)<span class="badge badge--muted">Finalizada</span>@else<span class="badge">Activa</span>@endif</div><p>{{ $recurrence->type->label() }} · {{ $recurrence->frequency->label() }} · {{ $recurrence->formattedAmount() }}</p>@if($recurrence->tags->isNotEmpty())<div class="movement-tags">@foreach($recurrence->tags as $tag)<span class="tag-chip tag-chip--small"># {{ $tag->name }}</span>@endforeach</div>@endif</div>
                    </div>
                    <dl class="recurrence-card__details">
                        <div><dt>Próxima</dt><dd>{{ $recurrence->next_occurrence_on?->format('d/m/Y') ?? 'Sin más apariciones' }}</dd></div>
                        <div><dt>Origen</dt><dd>{{ $recurrence->account->name }}</dd></div>
                        @if($recurrence->destinationAccount)<div><dt>Destino</dt><dd>{{ $recurrence->destinationAccount->name }}</dd></div>@endif
                        @if($recurrence->category)<div><dt>Categoría</dt><dd>{{ $recurrence->category->name }}</dd></div>@endif
                        @if($recurrence->savingsGoal)<div><dt>Objetivo</dt><dd>{{ $recurrence->savingsGoal->name }}</dd></div>@endif
                        <div><dt>Procesadas</dt><dd>{{ $recurrence->occurrences_count }}</dd></div>
                    </dl>
                    @if ($canManage)
                        <div class="recurrence-card__actions">
                            <a class="button button--secondary button--small" href="{{ route('recurrences.edit', [$project, $recurrence]) }}">Editar futuro</a>
                            @if($recurrence->next_occurrence_on)
                                <form action="{{ route('recurrences.skip', [$project, $recurrence]) }}" method="post" data-confirm="¿Omitir solo la aparición del {{ $recurrence->next_occurrence_on->format('d/m/Y') }}?">@csrf<button class="button button--quiet button--small" type="submit">Omitir próxima</button></form>
                                @if($recurrence->paused_at)
                                    <form action="{{ route('recurrences.resume', [$project, $recurrence]) }}" method="post">@csrf<button class="button button--secondary button--small" type="submit">Reanudar</button></form>
                                @else
                                    <form action="{{ route('recurrences.pause', [$project, $recurrence]) }}" method="post">@csrf<button class="button button--danger-quiet button--small" type="submit">Pausar</button></form>
                                @endif
                            @endif
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
