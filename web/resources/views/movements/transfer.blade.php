@extends('layouts.app')

@php($editing = $movement !== null)
@php($goalDirectionValue = old('goal_direction', $goalDirection?->value))
@php($isGoalContribution = $goal && $goalDirectionValue === \App\Enums\GoalAllocationDirection::Contribution->value)
@php($isGoalWithdrawal = $goal && $goalDirectionValue === \App\Enums\GoalAllocationDirection::Withdrawal->value)
@php($leftoverMonth = $movement?->leftoverAllocation?->budget_month)
@php($pageTitle = $leftoverMonth ? 'Editar asignación del sobrante' : ($editing ? 'Editar transferencia' : ($isGoalWithdrawal ? 'Retirar del objetivo' : ($isGoalContribution ? 'Aportar al objetivo' : 'Nueva transferencia'))))
@section('title', $pageTitle.' en '.$project->name)

@section('content')
    <header class="page-heading page-heading--compact">
        <div class="page-heading__copy"><a class="back-link" href="{{ $leftoverMonth ? route('budgets.closure', ['project' => $project, 'month' => $leftoverMonth->format('Y-m')]) : ($goal ? route('savings-goals.index', $project) : route('movements.index', $project)) }}">← {{ $leftoverMonth ? 'Cierre mensual' : ($goal ? 'Objetivos' : 'Movimientos') }}</a><p class="eyebrow">{{ $project->name }}</p><h1 class="page-heading__title">{{ $pageTitle }}</h1><p class="page-heading__intro">@if($leftoverMonth)Esta transferencia está vinculada al sobrante de {{ $leftoverMonth->locale('es')->translatedFormat('F Y') }}. Sus cambios recalcularán cuánto se ha destinado, pero no cuánto sobró. @elseif($goal){{ $isGoalWithdrawal ? 'Retira dinero de' : 'Envía dinero a' }} «{{ $goal->name }}» mediante una transferencia real. @else Mueve dinero entre cuentas sin contarlo de nuevo como ingreso o gasto. @endif</p></div>
    </header>

    <form class="form movement-form" action="{{ $editing ? route('movements.update', [$project, $movement]) : route('movements.transfer.store', $project) }}" method="post">
        @csrf
        @if ($editing) @method('patch') @endif
        @if ($goal)
            <input type="hidden" name="savings_goal_id" value="{{ $goal->id }}">
            <input type="hidden" name="goal_direction" value="{{ $goalDirectionValue }}">
        @endif
        <section class="wizard__section"><span class="wizard__step" aria-hidden="true">1</span><div class="wizard__content">
            <h2 class="wizard__title">Origen y destino</h2><p class="wizard__intro">Para pagar una tarjeta, elige el banco como origen y la tarjeta como destino. Las inversiones externas muestran solo el capital aportado.</p>
            <div class="form-grid">
                <div class="field"><label class="field__label" for="transfer-source">Cuenta de origen</label><select class="field__control" id="transfer-source" name="financial_account_id" required><option value="">Selecciona una cuenta</option>@foreach ($accounts as $account)<option value="{{ $account->id }}" @selected((string) old('financial_account_id', $movement?->financial_account_id ?? ($isGoalWithdrawal ? $goal->financial_account_id : null)) === (string) $account->id) @disabled($leftoverMonth ? ! in_array($account->type, [\App\Enums\FinancialAccountType::Checking, \App\Enums\FinancialAccountType::Savings, \App\Enums\FinancialAccountType::Cash], true) : $account->type === \App\Enums\FinancialAccountType::CreditCard)>{{ $account->name }} · {{ $account->type->label() }}{{ $account->archived_at ? ' (archivada)' : '' }}</option>@endforeach</select>@if($isGoalWithdrawal)<small class="field__hint">Debe salir de {{ $goal->account->name }}.</small>@endif</div>
                <div class="field"><label class="field__label" for="transfer-destination">Cuenta de destino</label><select class="field__control" id="transfer-destination" name="destination_account_id" required><option value="">Selecciona una cuenta</option>@foreach ($accounts as $account)<option value="{{ $account->id }}" @selected((string) old('destination_account_id', $movement?->destination_account_id ?? ($isGoalContribution ? $goal->financial_account_id : null)) === (string) $account->id) @disabled($leftoverMonth && ! in_array($account->type, [\App\Enums\FinancialAccountType::Savings, \App\Enums\FinancialAccountType::ExternalInvestment], true))>{{ $account->name }} · {{ $account->type->label() }}{{ $account->archived_at ? ' (archivada)' : '' }}</option>@endforeach</select>@if($isGoalContribution)<small class="field__hint">Debe llegar a {{ $goal->account->name }}.</small>@endif</div>
                <div class="field"><label class="field__label" for="transfer-amount">Importe</label><div class="money-field"><input class="field__control money-field__control" id="transfer-amount" name="amount" inputmode="decimal" value="{{ old('amount', $editing ? number_format($movement->amount_cents / 100, 2, ',', '.') : '') }}" placeholder="0,00" required><span class="money-field__suffix">€</span></div></div>
                <div class="field"><label class="field__label" for="transfer-date">Fecha</label><input class="field__control" id="transfer-date" name="occurred_on" type="date" max="{{ $today }}" value="{{ old('occurred_on', $editing ? $movement->occurred_on->toDateString() : $today) }}" required></div>
                <div class="field field--wide"><label class="field__label" for="transfer-concept">Concepto</label><input class="field__control" id="transfer-concept" name="concept" value="{{ old('concept', $movement?->concept) }}" maxlength="180" placeholder="Ej. Aportación mensual a ahorro" required></div>
                <div class="field field--wide"><label class="field__label" for="transfer-notes">Notas <span class="field__optional">opcional</span></label><textarea class="field__control field__control--textarea" id="transfer-notes" name="notes" maxlength="5000">{{ old('notes', $movement?->notes) }}</textarea></div>
                @include('movements._tags', ['selectedTagIds' => $movement?->tags?->modelKeys() ?? []])
            </div>
        </div></section>
        <div class="wizard__actions"><a class="button button--secondary" href="{{ $leftoverMonth ? route('budgets.closure', ['project' => $project, 'month' => $leftoverMonth->format('Y-m')]) : ($goal ? route('savings-goals.index', $project) : route('movements.index', $project)) }}">Cancelar</a><button class="button button--primary" type="submit">{{ $editing ? 'Guardar cambios' : ($isGoalWithdrawal ? 'Registrar retirada' : ($isGoalContribution ? 'Registrar aportación' : 'Registrar transferencia')) }}</button></div>
    </form>
@endsection
