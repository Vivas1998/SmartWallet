@extends('layouts.app')

@php($transfer = in_array($plannedMovement->type, [\App\Enums\MovementType::Transfer, \App\Enums\MovementType::InvestmentContribution], true))
@php($kind = $transfer ? 'transfer' : $plannedMovement->type->value)
@php($suggestedDate = $plannedMovement->due_on->toDateString() > $today ? $today : $plannedMovement->due_on->toDateString())
@section('title', 'Realizar '.$plannedMovement->concept)

@section('content')
    <header class="page-heading page-heading--compact">
        <div class="page-heading__copy">
            <a class="back-link" href="{{ route('planned-movements.index', $project) }}">← Planificaciones</a>
            <p class="eyebrow">Vencimiento {{ $plannedMovement->due_on->format('d/m/Y') }}</p>
            <h1 class="page-heading__title">Registrar como realizado</h1>
            <p class="page-heading__intro">Confirma los datos reales. Solo al guardar se actualizarán las cuentas, el presupuesto y los informes.</p>
        </div>
    </header>

    @if($possibleDuplicate)
        <div class="alert alert--warning" role="alert"><p class="alert__title">Puede que este movimiento ya esté registrado.</p><p class="duplicate-warning__text">Coincide en categoría, subcategoría, fecha e importe con “{{ $possibleDuplicate['concept'] }}” del {{ $possibleDuplicate['date'] }} ({{ $possibleDuplicate['amount'] }}). Revisa los datos o pulsa «Guardar igualmente».</p></div>
    @endif

    <form class="form movement-form" action="{{ route('planned-movements.complete.store', [$project, $plannedMovement]) }}" method="post" data-planned-form>
        @csrf
        <input type="hidden" name="movement_kind" value="{{ $kind }}" data-recurrence-kind>
        @if($possibleDuplicate)<input type="hidden" name="allow_duplicate" value="1">@endif
        <section class="wizard__section"><span class="wizard__step" aria-hidden="true">1</span><div class="wizard__content">
            <h2 class="wizard__title">Datos reales</h2>
            <p class="wizard__intro">El tipo permanece fijo, pero el importe y la fecha pueden diferir de la previsión.</p>
            <div class="form-grid">
                <div class="field"><label class="field__label" for="completion-type">Tipo</label><input class="field__control" id="completion-type" value="{{ $plannedMovement->type->label() }}" disabled></div>
                <div class="field"><label class="field__label" for="completion-amount">Importe real</label><div class="money-field"><input class="field__control money-field__control" id="completion-amount" name="amount" inputmode="decimal" value="{{ old('amount', number_format($plannedMovement->amount_cents / 100, 2, ',', '.')) }}" required><span class="money-field__suffix">€</span></div><small class="field__hint">Previsto: {{ $plannedMovement->formattedAmount() }}</small></div>
                <div class="field"><label class="field__label" for="completion-date">Fecha real</label><input class="field__control" id="completion-date" name="occurred_on" type="date" max="{{ $today }}" value="{{ old('occurred_on', $suggestedDate) }}" required></div>
                <div class="field"><label class="field__label" for="completion-concept">Concepto</label><input class="field__control" id="completion-concept" name="concept" value="{{ old('concept', $plannedMovement->concept) }}" maxlength="180" required></div>
            </div>
        </div></section>

        <section class="wizard__section"><span class="wizard__step" aria-hidden="true">2</span><div class="wizard__content">
            <h2 class="wizard__title">Clasificación y cuentas reales</h2>
            <div class="form-grid">
                <div class="field" data-recurrence-standard><label class="field__label" for="completion-category">Categoría</label><select class="field__control" id="completion-category" name="category_id" data-recurrence-category><option value="">Selecciona una categoría</option>@foreach($categories as $category)<option value="{{ $category->id }}" data-category-type="{{ $category->type->value }}" @selected((string) old('category_id', $plannedMovement->category_id) === (string) $category->id)>{{ $category->name }}{{ $category->archived_at ? ' (archivada)' : '' }}</option>@endforeach</select></div>
                <div class="field" data-recurrence-standard><label class="field__label" for="completion-subcategory">Subcategoría <span class="field__optional">opcional</span></label><select class="field__control" id="completion-subcategory" name="subcategory_id" data-recurrence-subcategory><option value="">Sin subcategoría</option>@foreach($categories as $category)@foreach($category->children as $child)<option value="{{ $child->id }}" data-parent-id="{{ $category->id }}" data-category-type="{{ $child->type->value }}" @selected((string) old('subcategory_id', $plannedMovement->subcategory_id) === (string) $child->id)>{{ $child->name }}{{ $child->archived_at ? ' (archivada)' : '' }}</option>@endforeach @endforeach</select></div>
                <div class="field"><label class="field__label" for="completion-source" data-recurrence-source-label>Cuenta de {{ $transfer ? 'origen' : 'cargo' }}</label><select class="field__control" id="completion-source" name="financial_account_id" data-recurrence-source required><option value="">Selecciona una cuenta</option>@foreach($accounts as $account)<option value="{{ $account->id }}" data-account-type="{{ $account->type->value }}" @selected((string) old('financial_account_id', $plannedMovement->financial_account_id) === (string) $account->id)>{{ $account->name }} · {{ $account->type->label() }}{{ $account->archived_at ? ' (archivada)' : '' }}</option>@endforeach</select></div>
                <div class="field" data-recurrence-transfer><label class="field__label" for="completion-destination">Cuenta de destino</label><select class="field__control" id="completion-destination" name="destination_account_id" data-recurrence-destination><option value="">Selecciona una cuenta</option>@foreach($accounts as $account)<option value="{{ $account->id }}" data-account-type="{{ $account->type->value }}" @selected((string) old('destination_account_id', $plannedMovement->destination_account_id) === (string) $account->id)>{{ $account->name }} · {{ $account->type->label() }}{{ $account->archived_at ? ' (archivada)' : '' }}</option>@endforeach</select></div>
                <div class="field" data-recurrence-standard><label class="field__label" for="completion-payer">Pagado o recibido por</label><select class="field__control" id="completion-payer" name="paid_by_user_id">@foreach($members as $member)<option value="{{ $member->id }}" @selected((string) old('paid_by_user_id', $plannedMovement->paid_by_user_id ?? auth()->id()) === (string) $member->id)>{{ $member->name }}</option>@endforeach</select></div>
                <div class="field" data-recurrence-transfer><label class="field__label" for="completion-goal">Objetivo vinculado <span class="field__optional">opcional</span></label><select class="field__control" id="completion-goal" name="savings_goal_id" data-recurrence-goal><option value="">Sin objetivo</option>@foreach($goals as $goal)<option value="{{ $goal->id }}" data-account-id="{{ $goal->financial_account_id }}" @selected((string) old('savings_goal_id', $plannedMovement->savings_goal_id) === (string) $goal->id)>{{ $goal->name }} · {{ $goal->account->name }}{{ $goal->archived_at ? ' (archivado)' : '' }}</option>@endforeach</select></div>
                <div class="field field--wide"><label class="field__label" for="completion-notes">Notas <span class="field__optional">opcional</span></label><textarea class="field__control field__control--textarea" id="completion-notes" name="notes" maxlength="5000">{{ old('notes', $plannedMovement->notes) }}</textarea></div>
                @include('movements._tags', ['selectedTagIds' => $plannedMovement->tags->modelKeys()])
                @include('movements._custom-fields')
            </div>
        </div></section>
        <div class="wizard__actions"><a class="button button--secondary" href="{{ route('planned-movements.index', $project) }}">Cancelar</a><button class="button button--primary" type="submit">{{ $possibleDuplicate ? 'Guardar igualmente' : 'Registrar movimiento real' }}</button></div>
    </form>
@endsection
