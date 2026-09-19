@extends('layouts.app')

@section('title', 'Cuentas de '.$project->name)

@section('content')
    @php($formatMoney = fn (int $cents) => number_format($cents / 100, 2, ',', '.').' €')
    <header class="page-heading">
        <div class="page-heading__copy">
            <a class="back-link" href="{{ route('projects.show', $project) }}">← {{ $project->name }}</a>
            <p class="eyebrow">Dinero y saldos</p>
            <h1 class="page-heading__title">Cuentas</h1>
            <p class="page-heading__intro">Los saldos se calculan desde su valor inicial y cada operación confirmada.</p>
        </div>
        @can('recordMovements', $project)<a class="button button--primary" href="{{ route('movements.transfer.create', $project) }}">↔ Nueva transferencia</a>@endcan
    </header>

    @include('projects._navigation', ['project' => $project])

    <div class="account-summaries" aria-label="Resumen de cuentas activas">
        <article class="account-summary"><small>Liquidez</small><strong>{{ $formatMoney($summaries['liquidity']) }}</strong><span>Corriente y efectivo</span></article>
        <article class="account-summary account-summary--savings"><small>Ahorro</small><strong>{{ $formatMoney($summaries['savings']) }}</strong><span>Cuentas de ahorro</span></article>
        <article class="account-summary account-summary--investment"><small>Capital aportado</small><strong>{{ $formatMoney($summaries['investment']) }}</strong><span>No es el valor de cartera</span></article>
        <article class="account-summary account-summary--credit"><small>Deuda de tarjetas</small><strong>{{ $formatMoney($summaries['credit']) }}</strong><span>Compra ya contabilizada</span></article>
    </div>

    @if ($canManage)
        <section class="management-panel content-section" aria-labelledby="new-account-title">
            <div class="management-panel__heading"><div><p class="eyebrow">Solo propietarios</p><h2 class="management-panel__title" id="new-account-title">Añadir cuenta</h2></div><p class="management-panel__intro">El saldo inicial sitúa el punto de partida y no se suma a los ingresos.</p></div>
            <form class="form" action="{{ route('financial-accounts.store', $project) }}" method="post">
                @csrf
                @include('financial-accounts._form', ['today' => $today, 'hasMovements' => false])
                <div class="form-actions"><button class="button button--primary" type="submit">Crear cuenta</button></div>
            </form>
        </section>
    @endif

    <section class="section-heading"><div><p class="eyebrow">{{ $accounts->whereNull('archived_at')->count() }} activas</p><h2 class="section-heading__title">Tus cuentas</h2></div></section>
    <div class="account-management-list">
        @foreach ($accounts as $account)
            <article class="account-management-card {{ $account->archived_at ? 'account-management-card--archived' : '' }}">
                <span class="account-management-card__mark" style="--account-color: {{ $account->color ?? '#147d68' }}" aria-hidden="true">{{ $account->type === \App\Enums\FinancialAccountType::CreditCard ? '▭' : ($account->type === \App\Enums\FinancialAccountType::ExternalInvestment ? '◆' : '◫') }}</span>
                <div class="account-management-card__copy">
                    <div class="account-management-card__title"><h3>{{ $account->name }}</h3>@if ($account->archived_at)<span class="badge badge--muted">Archivada</span>@endif</div>
                    <p>{{ $account->type->label() }} · inicial a {{ $account->initial_balance_date->format('d/m/Y') }}</p>
                </div>
                <div class="account-management-card__balance"><small>{{ $account->type === \App\Enums\FinancialAccountType::CreditCard ? 'Deuda' : 'Saldo' }}</small><strong class="{{ $account->currentBalanceCents() < 0 ? 'text-danger' : '' }}">{{ $account->formattedCurrentBalance() }}</strong></div>
                @if ($canManage)
                    <div class="account-management-card__actions">
                        <a class="button button--secondary button--small" href="{{ route('financial-accounts.edit', [$project, $account]) }}">Editar</a>
                        @if ($account->archived_at)
                            <form action="{{ route('financial-accounts.restore', [$project, $account]) }}" method="post">@csrf<button class="button button--secondary button--small" type="submit">Reactivar</button></form>
                        @else
                            <form action="{{ route('financial-accounts.archive', [$project, $account]) }}" method="post" onsubmit="return confirm('¿Archivar esta cuenta? Su historial se conservará.')">@csrf<button class="button button--danger-quiet button--small" type="submit">Archivar</button></form>
                        @endif
                    </div>
                @endif
            </article>
        @endforeach
    </div>

    <div class="alert alert--info content-section" role="note"><p class="alert__title">Las transferencias no duplican ingresos ni gastos.</p><p>Un pago de tarjeta reduce el banco y la deuda. Una aportación a inversión muestra capital aportado, no rentabilidad ni valor actual.</p></div>
@endsection
