@extends('layouts.app')

@section('title', $project->name)

@section('content')
    @php
        $formatMoney = fn (int $cents) => number_format($cents / 100, 2, ',', '.').' €';
        $expenseProgress = $budget->total_limit_cents > 0 ? max(0, min(100, (int) round(($expenseCents / $budget->total_limit_cents) * 100))) : ($expenseCents > 0 ? 100 : 0);
        $incomeProgress = max($budget->total_limit_cents, $incomeCents) > 0 ? min(100, (int) round(($incomeCents / max($budget->total_limit_cents, $incomeCents)) * 100)) : 0;
        $remainingProgress = $budget->total_limit_cents > 0 ? max(0, min(100, (int) round(($remainingCents / $budget->total_limit_cents) * 100))) : 0;
    @endphp
    <header class="project-heading">
        <div class="project-heading__identity">
            <a class="back-link" href="{{ route('dashboard') }}">← Mis proyectos</a>
            <div class="project-heading__title-row">
                <span
                    class="project-heading__icon"
                    style="--project-color: {{ $project->color }}"
                    aria-hidden="true"
                >{{ $project->iconSymbol() }}</span>
                <div>
                    <p class="eyebrow">Proyecto</p>
                    <h1 class="project-heading__title">{{ $project->name }}</h1>
                </div>
            </div>
            @if ($project->description)
                <p class="project-heading__description">{{ $project->description }}</p>
            @endif
        </div>
        <div class="project-heading__meta">
            <span>{{ $project->activeMembers->count() }} {{ $project->activeMembers->count() === 1 ? 'miembro' : 'miembros' }}</span>
            <span>{{ $project->currency }} · {{ $project->timezone }}</span>
        </div>
    </header>

    @include('projects._navigation', ['project' => $project])

    @foreach($recoveryNotices as $notice)
        <section class="alert alert--warning recovery-notice" role="status">
            <div><p class="alert__title">SmartWallet recuperó {{ $notice->generated_count }} movimiento(s) vencido(s) al arrancar.</p><ul class="alert__list">@foreach(array_slice($notice->details, 0, 5) as $detail)<li>{{ date('d/m/Y', strtotime($detail['date'])) }} · {{ $detail['concept'] }} · {{ $formatMoney((int) $detail['amount_cents']) }}</li>@endforeach</ul>@if($notice->generated_count > 5)<p>Y {{ $notice->generated_count - 5 }} más.</p>@endif</div>
            @can('recordMovements', $project)<form action="{{ route('recurrence-notices.dismiss', [$project, $notice]) }}" method="post">@csrf<button class="button button--secondary button--small" type="submit">Marcar como revisado</button></form>@endcan
        </section>
    @endforeach

    @if ($project->isArchived())
        <div class="alert alert--warning" role="status">
            Este proyecto está archivado y permanece en modo de solo lectura.
        </div>
    @endif

    <section class="dashboard-heading">
        <div>
            <p class="eyebrow">{{ ucfirst($month->locale('es')->translatedFormat('F Y')) }}</p>
            <h2 class="section-heading__title">Así va el mes</h2>
        </div>
        @can('recordMovements', $project)
            <a class="button button--primary" href="{{ route('movements.create', $project) }}">+ Añadir movimiento</a>
        @endcan
    </section>

    <div class="metric-rings">
        <a class="metric-ring-card metric-ring-card--income" href="{{ route('movements.index', ['project' => $project, 'type' => 'income']) }}">
            <span class="metric-ring-card__ring" style="--ring-progress: {{ $incomeProgress }}%"><span aria-hidden="true">↑</span></span>
            <span class="metric-ring-card__copy"><small>Ingresos</small><strong>{{ $formatMoney($incomeCents) }}</strong><span>registrados este mes</span></span>
        </a>
        <a class="metric-ring-card metric-ring-card--expense" href="{{ route('movements.index', ['project' => $project, 'type' => 'expense']) }}">
            <span class="metric-ring-card__ring" style="--ring-progress: {{ $expenseProgress }}%"><span aria-hidden="true">↓</span></span>
            <span class="metric-ring-card__copy"><small>Gasto neto</small><strong>{{ $formatMoney($expenseCents) }}</strong><span>{{ $expenseProgress }} % del presupuesto · {{ $formatMoney($refundCents) }} devuelto</span></span>
        </a>
        <a class="metric-ring-card {{ $remainingCents < 0 ? 'metric-ring-card--danger' : 'metric-ring-card--budget' }}" href="{{ route('budgets.index', $project) }}">
            <span class="metric-ring-card__ring" style="--ring-progress: {{ $remainingCents < 0 ? 100 : $remainingProgress }}%"><span aria-hidden="true">€</span></span>
            <span class="metric-ring-card__copy"><small>Presupuesto disponible</small><strong>{{ $formatMoney($remainingCents) }}</strong><span>de {{ $formatMoney((int) $budget->total_limit_cents) }}</span></span>
        </a>
    </div>

    <section class="section-heading">
        <div>
            <p class="eyebrow">Punto de partida</p>
            <h2 class="section-heading__title">Cuentas</h2>
        </div>
    </section>

    <div class="account-grid">
        @foreach ($project->financialAccounts as $account)
            <article class="account-card">
                <div class="account-card__heading">
                    <span class="account-card__icon" aria-hidden="true">◫</span>
                    <div>
                        <h3 class="account-card__name">{{ $account->name }}</h3>
                        <p class="account-card__type">{{ $account->type->label() }}</p>
                    </div>
                </div>
                <p class="account-card__balance">{{ $account->formattedCurrentBalance() }}</p>
                <p class="account-card__date">
                    Saldo actual · inicial a {{ $account->initial_balance_date->format('d/m/Y') }}
                </p>
            </article>
        @endforeach
    </div>

    <section class="section-heading">
        <div><p class="eyebrow">Actividad del proyecto</p><h2 class="section-heading__title">Últimos movimientos</h2></div>
        <a class="text-link" href="{{ route('movements.index', $project) }}">Ver todos</a>
    </section>

    @if ($recentMovements->isEmpty())
        <div class="compact-empty"><p>Aún no hay gastos ni ingresos registrados.</p>@can('recordMovements', $project)<a class="text-link" href="{{ route('movements.create', $project) }}">Añadir el primero</a>@endcan</div>
    @else
        <div class="recent-movements">
            @foreach ($recentMovements as $movement)
                <article class="recent-movement">
                    @php($symbol = match($movement->type) { \App\Enums\MovementType::Expense => '↓', \App\Enums\MovementType::Income => '↑', \App\Enums\MovementType::Refund => '↩', default => '↔' })
                    @php($prefix = match($movement->type) { \App\Enums\MovementType::Expense => '−', \App\Enums\MovementType::Income, \App\Enums\MovementType::Refund => '+', default => '' })
                    <span class="recent-movement__icon recent-movement__icon--{{ $movement->type->value }}" aria-hidden="true">{{ $symbol }}</span>
                    <div class="recent-movement__copy"><strong>{{ $movement->concept }}</strong><span>{{ $movement->category?->name ?? $movement->type->label() }}@if ($movement->subcategory) · {{ $movement->subcategory->name }}@endif · {{ $movement->occurred_on->format('d/m/Y') }}</span></div>
                    <strong class="recent-movement__amount recent-movement__amount--{{ $movement->type->value }}">{{ $prefix }}{{ $movement->formattedAmount() }}</strong>
                </article>
            @endforeach
        </div>
    @endif
@endsection
