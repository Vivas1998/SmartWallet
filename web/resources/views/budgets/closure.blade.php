@extends('layouts.app')

@section('title', 'Cierre mensual de '.$project->name)

@section('content')
    @php
        $money = fn (int $cents) => number_format($cents / 100, 2, ',', '.').' €';
        $inputMoney = fn (int $cents) => number_format($cents / 100, 2, '.', '');
        $monthLabel = ucfirst($month->locale('es')->translatedFormat('F Y'));
    @endphp
    <header class="page-heading page-heading--compact">
        <div class="page-heading__copy"><a class="back-link" href="{{ route('budgets.index', ['project' => $project, 'month' => $month->format('Y-m')]) }}">← Presupuesto</a><p class="eyebrow">Resumen recalculado</p><h1 class="page-heading__title">Cierre mensual</h1><p class="page-heading__intro">Comprueba qué ocurrió en el mes y decide si quieres trasladar parte del sobrante a ahorro o inversión.</p></div>
    </header>

    @include('projects._navigation', ['project' => $project])

    <nav class="month-switcher" aria-label="Cambiar mes del cierre">
        <a class="button button--secondary button--small" href="{{ route('budgets.closure', ['project' => $project, 'month' => $month->subMonth()->format('Y-m')]) }}">← Anterior</a>
        <div class="month-switcher__current"><span class="eyebrow">Mes consultado</span><strong>{{ $monthLabel }}</strong></div>
        <a class="button button--secondary button--small" href="{{ route('budgets.closure', ['project' => $project, 'month' => $month->addMonth()->format('Y-m')]) }}">Siguiente →</a>
    </nav>

    @if(! $completed)
        <div class="alert alert--info" role="note"><p class="alert__title">Este mes todavía no ha terminado.</p><p>Puedes consultar sus cifras provisionales, pero «Destinar sobrante» se habilitará al comenzar el mes siguiente.</p></div>
    @endif
    @if($summary['overallocated_cents'] > 0)
        <div class="alert alert--warning" role="alert"><p class="alert__title">Se destinó más dinero del que ahora figura como sobrante.</p><p>Una corrección posterior ha dejado una diferencia de {{ $money($summary['overallocated_cents']) }}. No se ha revertido ninguna transferencia automáticamente.</p></div>
    @endif

    <section class="closure-overview" aria-labelledby="closure-overview-title">
        <div class="section-heading"><div><p class="eyebrow">Resultado del mes</p><h2 class="section-heading__title" id="closure-overview-title">{{ $monthLabel }}</h2></div><span class="badge {{ $completed ? '' : 'badge--muted' }}">{{ $completed ? 'Mes terminado' : 'En curso' }}</span></div>
        <div class="closure-metrics">
            <article class="closure-metric closure-metric--budget"><small>Presupuesto inicial</small><strong>{{ $money($summary['budget_cents']) }}</strong></article>
            <article class="closure-metric closure-metric--expense"><small>Gasto neto</small><strong>{{ $money($summary['expense_cents']) }}</strong><span>{{ $money($summary['refund_cents']) }} devuelto</span></article>
            <article class="closure-metric {{ $summary['remaining_cents'] < 0 ? 'closure-metric--danger' : 'closure-metric--leftover' }}"><small>{{ $summary['remaining_cents'] < 0 ? 'Exceso de presupuesto' : 'Sobrante del presupuesto' }}</small><strong>{{ $money($summary['remaining_cents']) }}</strong><span>No cambia al transferirlo</span></article>
            <article class="closure-metric closure-metric--income"><small>Ingresos</small><strong>{{ $money($summary['income_cents']) }}</strong><span>No amplían el presupuesto</span></article>
            <article class="closure-metric {{ $summary['balance_cents'] < 0 ? 'closure-metric--danger' : 'closure-metric--balance' }}"><small>Balance</small><strong>{{ $money($summary['balance_cents']) }}</strong><span>Ingresos menos gasto neto</span></article>
        </div>
    </section>

    <section class="content-section" aria-labelledby="closure-accounts-title">
        <div class="section-heading"><div><p class="eyebrow">Dinero real</p><h2 class="section-heading__title" id="closure-accounts-title">Saldos al finalizar el mes</h2></div><a class="text-link" href="{{ route('financial-accounts.index', $project) }}">Ver saldos actuales</a></div>
        @if($accountsAtClose->isEmpty())
            <div class="compact-empty"><p>No había cuentas con saldo inicial en este periodo.</p></div>
        @else
            <div class="closure-account-grid">@foreach($accountsAtClose as $account)<article class="closure-account"><span class="closure-account__icon" aria-hidden="true">◫</span><div><strong>{{ $account->name }}</strong><small>{{ $account->type->label() }}{{ $account->archived_at ? ' · archivada' : '' }}</small></div><b>{{ $account->formattedCurrentBalance() }}</b></article>@endforeach</div>
        @endif
    </section>

    <section class="content-section" aria-labelledby="allocated-leftover-title">
        <div class="section-heading"><div><p class="eyebrow">Transferencias vinculadas</p><h2 class="section-heading__title" id="allocated-leftover-title">Sobrante destinado</h2></div></div>
        <div class="allocated-summary">
            <article><small>Total destinado</small><strong>{{ $money($summary['allocated_cents']) }}</strong></article>
            <article><small>A ahorro</small><strong>{{ $money($summary['allocated_savings_cents']) }}</strong></article>
            <article><small>A inversión</small><strong>{{ $money($summary['allocated_investment_cents']) }}</strong></article>
            <article><small>Todavía disponible</small><strong>{{ $money($summary['available_to_allocate_cents']) }}</strong></article>
        </div>
        @if($summary['allocations']->isEmpty())
            <div class="compact-empty"><p>No se ha destinado parte del sobrante de este mes.</p></div>
        @else
            <div class="closure-allocation-list">
                @foreach($summary['allocations'] as $allocation)
                    @php($movement = $allocation->movement)
                    <article class="closure-allocation"><span class="closure-allocation__icon" aria-hidden="true">{{ $movement->destinationAccount->type === \App\Enums\FinancialAccountType::ExternalInvestment ? '◆' : '◎' }}</span><div class="closure-allocation__copy"><strong>{{ $movement->destinationAccount->name }}</strong><span>{{ $movement->account->name }} → {{ $movement->destinationAccount->name }} · {{ $movement->occurred_on->format('d/m/Y') }}</span>@if($movement->goalAllocation?->savingsGoal)<small>Objetivo: {{ $movement->goalAllocation->savingsGoal->name }}</small>@endif</div><strong class="closure-allocation__amount">{{ $money($movement->amount_cents) }}</strong><a class="text-link" href="{{ route('movements.edit', [$project, $movement]) }}">Editar</a></article>
                @endforeach
            </div>
        @endif
    </section>

    @if($canAllocate)
        <form class="form closure-form" action="{{ route('budgets.closure.store', $project) }}" method="post" data-closure-allocation-form>
            @csrf
            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
            <div class="management-panel__heading"><div><p class="eyebrow">Acción opcional</p><h2 class="management-panel__title">Destinar sobrante</h2></div><p class="management-panel__intro">Se creará una transferencia real. El sobrante histórico seguirá siendo {{ $money($summary['remaining_cents']) }}.</p></div>
            <div class="form-grid">
                <div class="field"><label class="field__label" for="closure-source">Cuenta de origen</label><select class="field__control" id="closure-source" name="financial_account_id" required><option value="">Selecciona una cuenta</option>@foreach($sourceAccounts as $account)<option value="{{ $account->id }}" @selected((string) old('financial_account_id') === (string) $account->id)>{{ $account->name }} · saldo actual {{ $money($account->currentBalanceCents()) }}</option>@endforeach</select><small class="field__hint">Debe disponer del importe que vas a transferir.</small></div>
                <div class="field"><label class="field__label" for="closure-destination">Destino</label><select class="field__control" id="closure-destination" name="destination_account_id" required data-closure-destination><option value="">Selecciona ahorro o inversión</option>@foreach($destinationAccounts as $account)<option value="{{ $account->id }}" @selected((string) old('destination_account_id') === (string) $account->id)>{{ $account->name }} · {{ $account->type->label() }}</option>@endforeach</select></div>
                <div class="field"><label class="field__label" for="closure-amount">Importe</label><div class="money-field"><input class="field__control money-field__control" id="closure-amount" name="amount" inputmode="decimal" value="{{ old('amount', $inputMoney($summary['available_to_allocate_cents'])) }}" max="{{ $inputMoney($summary['available_to_allocate_cents']) }}" required><span class="money-field__suffix">€</span></div><small class="field__hint">Máximo pendiente: {{ $money($summary['available_to_allocate_cents']) }}</small></div>
                <div class="field"><label class="field__label" for="closure-date">Fecha de la transferencia</label><input class="field__control" id="closure-date" name="occurred_on" type="date" min="{{ $minimumDate }}" max="{{ $today }}" value="{{ old('occurred_on', $today) }}" required></div>
                <div class="field field--wide"><label class="field__label" for="closure-goal">Objetivo de ahorro <span class="field__optional">opcional</span></label><select class="field__control" id="closure-goal" name="savings_goal_id" data-closure-goal><option value="">Sin vincular a un objetivo</option>@foreach($goals as $goal)<option value="{{ $goal->id }}" data-account-id="{{ $goal->financial_account_id }}" @selected((string) old('savings_goal_id') === (string) $goal->id)>{{ $goal->name }} · {{ $goal->account->name }}</option>@endforeach</select><small class="field__hint">Solo aparecerán como válidos los objetivos de la cuenta de destino.</small></div>
                <div class="field field--wide"><label class="field__label" for="closure-notes">Notas <span class="field__optional">opcional</span></label><textarea class="field__control field__control--textarea" id="closure-notes" name="notes" maxlength="5000">{{ old('notes') }}</textarea></div>
            </div>
            <div class="form-actions"><button class="button button--primary" type="submit">Crear transferencia</button></div>
        </form>
    @elseif($completed && $summary['available_to_allocate_cents'] > 0 && ! $project->isArchived() && ! $canManage)
        <div class="alert alert--info" role="note">Solo los propietarios pueden destinar el sobrante. Todos los miembros pueden consultar este resumen.</div>
    @elseif($completed && $summary['available_to_allocate_cents'] > 0 && $canManage && ! $hasCompatiblePair)
        <div class="alert alert--info" role="note"><p class="alert__title">Faltan cuentas compatibles para realizar la transferencia.</p><p>Necesitas una cuenta de origen con saldo positivo y otra cuenta activa de ahorro o inversión externa.</p></div>
    @elseif($completed && $summary['available_to_allocate_cents'] === 0)
        <div class="compact-empty"><p>{{ $summary['remaining_cents'] <= 0 ? 'Este mes no tiene sobrante disponible.' : 'Todo el sobrante disponible ya ha sido destinado.' }}</p></div>
    @endif
@endsection
