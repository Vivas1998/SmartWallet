@extends('layouts.app')

@section('title', 'Objetivos de '.$project->name)

@section('content')
    @php($formatMoney = fn (int $cents) => number_format($cents / 100, 2, ',', '.').' €')
    <header class="page-heading page-heading--compact"><div class="page-heading__copy"><a class="back-link" href="{{ route('projects.show', $project) }}">← {{ $project->name }}</a><p class="eyebrow">Ahorro e inversión</p><h1 class="page-heading__title">Objetivos</h1><p class="page-heading__intro">Mide avances con aportaciones explícitas, sin confundirlos con todo el saldo de una cuenta.</p></div></header>

    @include('projects._navigation', ['project' => $project])

    @if($canManage)
        <section class="management-panel content-section" aria-labelledby="new-goal-title"><div class="management-panel__heading"><div><p class="eyebrow">Solo propietarios</p><h2 class="management-panel__title" id="new-goal-title">Nuevo objetivo</h2></div><p class="management-panel__intro">Una vez creado, cualquier miembro puede realizar aportaciones o retiradas.</p></div>
            @if($eligibleAccounts->isEmpty())
                <div class="alert alert--warning">Antes necesitas una <a class="text-link" href="{{ route('financial-accounts.index', $project) }}">cuenta de ahorro o inversión externa</a>.</div>
            @else
                <form class="form" action="{{ route('savings-goals.store', $project) }}" method="post">@csrf @include('savings-goals._form', ['goal' => null])<div class="form-actions"><button class="button button--primary" type="submit">Crear objetivo</button></div></form>
            @endif
        </section>
    @endif

    <section class="section-heading"><div><p class="eyebrow">{{ $goals->whereNull('archived_at')->count() }} activos</p><h2 class="section-heading__title">Progreso</h2></div></section>
    @if($goals->isEmpty())
        <div class="empty-state"><span class="empty-state__icon" aria-hidden="true">◎</span><h2>Aún no hay objetivos</h2><p>Crea uno y vincula aportaciones reales desde cualquier cuenta del proyecto.</p></div>
    @else
        <div class="goal-grid">
            @foreach($goals as $goal)
                @php($current = $goal->currentAmountCents())
                @php($percentage = $goal->progressPercentage())
                @php($overdue = $goal->target_date && $goal->target_date->toDateString() < now('Europe/Madrid')->toDateString() && $percentage < 100)
                <article class="goal-card {{ $goal->archived_at ? 'goal-card--archived' : '' }}">
                    <div class="goal-card__heading"><div><div class="goal-card__title"><h2>{{ $goal->name }}</h2>@if($goal->archived_at)<span class="badge badge--muted">Archivado</span>@elseif($percentage >= 100)<span class="badge badge--success">Completado</span>@elseif($overdue)<span class="badge badge--warning">Fecha superada</span>@endif</div><p>{{ $goal->account->name }} · {{ $goal->account->type->label() }}</p></div><strong class="goal-card__percentage">{{ $percentage }}%</strong></div>
                    <div class="progress-bar goal-card__progress" role="progressbar" aria-label="Progreso de {{ $goal->name }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ min(100, $percentage) }}" aria-valuetext="{{ $percentage }} % conseguido"><span class="progress-bar__value" style="width: {{ min(100, $percentage) }}%"></span></div>
                    <dl class="goal-card__values"><div><dt>Conseguido</dt><dd>{{ $formatMoney($current) }}</dd></div><div><dt>Objetivo</dt><dd>{{ $formatMoney($goal->target_amount_cents) }}</dd></div><div><dt>Fecha</dt><dd>{{ $goal->target_date?->format('d/m/Y') ?? 'Sin fecha' }}</dd></div></dl>
                    @if(!$goal->archived_at && !$project->isArchived())
                        <div class="goal-card__actions"><a class="button button--primary button--small" href="{{ route('savings-goals.contribute', [$project, $goal]) }}">+ Aportar</a><a class="button button--secondary button--small" href="{{ route('savings-goals.contribute', [$project, $goal, 'direction' => 'withdrawal']) }}">Retirar</a>@if($canManage)<a class="button button--quiet button--small" href="{{ route('savings-goals.edit', [$project, $goal]) }}">Editar</a><form action="{{ route('savings-goals.archive', [$project, $goal]) }}" method="post" data-confirm="¿Archivar este objetivo? Su historial se conservará.">@csrf<button class="button button--danger-quiet button--small" type="submit">Archivar</button></form>@endif</div>
                    @endif
                    @if($goal->allocations->isNotEmpty())
                        <details class="goal-card__history"><summary>{{ $goal->allocations->count() }} movimiento(s) vinculado(s)</summary><ul>@foreach($goal->allocations->sortByDesc(fn($allocation) => $allocation->movement?->occurred_on)->take(5) as $allocation)@if($allocation->movement)<li><span>{{ $allocation->movement->occurred_on->format('d/m/Y') }} · {{ $allocation->direction->label() }}</span><strong>{{ $allocation->direction === \App\Enums\GoalAllocationDirection::Contribution ? '+' : '−' }}{{ $allocation->movement->formattedAmount() }}{{ $allocation->movement->trashed_at ? ' (papelera)' : '' }}</strong></li>@endif @endforeach</ul></details>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
