@extends('layouts.app')

@php($editing = $plannedMovement !== null)
@php($kind = old('movement_kind', $editing ? (in_array($plannedMovement->type, [\App\Enums\MovementType::Transfer, \App\Enums\MovementType::InvestmentContribution], true) ? 'transfer' : $plannedMovement->type->value) : 'expense'))
@section('title', ($editing ? 'Editar planificación' : 'Nueva planificación').' en '.$project->name)

@section('content')
    <header class="page-heading page-heading--compact">
        <div class="page-heading__copy">
            <a class="back-link" href="{{ route('planned-movements.index', $project) }}">← Planificaciones</a>
            <p class="eyebrow">{{ $project->name }}</p>
            <h1 class="page-heading__title">{{ $editing ? 'Editar planificación' : 'Nueva planificación puntual' }}</h1>
            <p class="page-heading__intro">Guardar esta previsión no alterará saldos, presupuestos, informes ni objetivos.</p>
        </div>
    </header>

    <form class="form movement-form" action="{{ $editing ? route('planned-movements.update', [$project, $plannedMovement]) : route('planned-movements.store', $project) }}" method="post" data-planned-form>
        @csrf
        @if($editing) @method('put') @endif
        <section class="wizard__section"><span class="wizard__step" aria-hidden="true">1</span><div class="wizard__content">
            <h2 class="wizard__title">Operación prevista</h2>
            <p class="wizard__intro">El importe es una estimación que podrás corregir al registrar el movimiento real.</p>
            <div class="form-grid">
                <div class="field"><label class="field__label" for="planned-kind">Tipo</label><select class="field__control" id="planned-kind" name="movement_kind" data-recurrence-kind required><option value="expense" @selected($kind === 'expense')>Gasto</option><option value="income" @selected($kind === 'income')>Ingreso</option><option value="transfer" @selected($kind === 'transfer')>Transferencia o inversión</option></select></div>
                <div class="field"><label class="field__label" for="planned-amount">Importe previsto</label><div class="money-field"><input class="field__control money-field__control" id="planned-amount" name="amount" inputmode="decimal" value="{{ old('amount', $editing ? number_format($plannedMovement->amount_cents / 100, 2, ',', '.') : '') }}" placeholder="0,00" required><span class="money-field__suffix">€</span></div></div>
                <div class="field"><label class="field__label" for="planned-due">Fecha de vencimiento</label><input class="field__control" id="planned-due" name="due_on" type="date" value="{{ old('due_on', $plannedMovement?->due_on?->toDateString() ?? $today) }}" required></div>
                <div class="field"><label class="field__label" for="planned-concept">Concepto</label><input class="field__control" id="planned-concept" name="concept" value="{{ old('concept', $plannedMovement?->concept) }}" maxlength="180" placeholder="Ej. Seguro del hogar" required></div>
            </div>
        </div></section>

        <section class="wizard__section"><span class="wizard__step" aria-hidden="true">2</span><div class="wizard__content">
            <h2 class="wizard__title">Clasificación prevista</h2>
            <p class="wizard__intro">Puedes ajustar cualquiera de estos datos cuando la operación se realice.</p>
            <div class="form-grid">
                <div class="field" data-recurrence-standard><label class="field__label" for="planned-category">Categoría</label><select class="field__control" id="planned-category" name="category_id" data-recurrence-category><option value="">Selecciona una categoría</option>@foreach($categories as $category)<option value="{{ $category->id }}" data-category-type="{{ $category->type->value }}" @selected((string) old('category_id', $plannedMovement?->category_id) === (string) $category->id)>{{ $category->name }}{{ $category->archived_at ? ' (archivada)' : '' }}</option>@endforeach</select></div>
                <div class="field" data-recurrence-standard><label class="field__label" for="planned-subcategory">Subcategoría <span class="field__optional">opcional</span></label><select class="field__control" id="planned-subcategory" name="subcategory_id" data-recurrence-subcategory><option value="">Sin subcategoría</option>@foreach($categories as $category)@foreach($category->children as $child)<option value="{{ $child->id }}" data-parent-id="{{ $category->id }}" data-category-type="{{ $child->type->value }}" @selected((string) old('subcategory_id', $plannedMovement?->subcategory_id) === (string) $child->id)>{{ $child->name }}{{ $child->archived_at ? ' (archivada)' : '' }}</option>@endforeach @endforeach</select></div>
                <div class="field"><label class="field__label" for="planned-source" data-recurrence-source-label>Cuenta de {{ $kind === 'transfer' ? 'origen' : 'cargo' }}</label><select class="field__control" id="planned-source" name="financial_account_id" data-recurrence-source required><option value="">Selecciona una cuenta</option>@foreach($accounts as $account)<option value="{{ $account->id }}" data-account-type="{{ $account->type->value }}" @selected((string) old('financial_account_id', $plannedMovement?->financial_account_id) === (string) $account->id)>{{ $account->name }} · {{ $account->type->label() }}{{ $account->archived_at ? ' (archivada)' : '' }}</option>@endforeach</select></div>
                <div class="field" data-recurrence-transfer><label class="field__label" for="planned-destination">Cuenta de destino</label><select class="field__control" id="planned-destination" name="destination_account_id" data-recurrence-destination><option value="">Selecciona una cuenta</option>@foreach($accounts as $account)<option value="{{ $account->id }}" data-account-type="{{ $account->type->value }}" @selected((string) old('destination_account_id', $plannedMovement?->destination_account_id) === (string) $account->id)>{{ $account->name }} · {{ $account->type->label() }}{{ $account->archived_at ? ' (archivada)' : '' }}</option>@endforeach</select></div>
                <div class="field" data-recurrence-standard><label class="field__label" for="planned-payer">Pagado o recibido por</label><select class="field__control" id="planned-payer" name="paid_by_user_id">@foreach($members as $member)<option value="{{ $member->id }}" @selected((string) old('paid_by_user_id', $plannedMovement?->paid_by_user_id ?? auth()->id()) === (string) $member->id)>{{ $member->name }}</option>@endforeach</select></div>
                <div class="field" data-recurrence-transfer><label class="field__label" for="planned-goal">Objetivo vinculado <span class="field__optional">opcional</span></label><select class="field__control" id="planned-goal" name="savings_goal_id" data-recurrence-goal><option value="">Sin objetivo</option>@foreach($goals as $goal)<option value="{{ $goal->id }}" data-account-id="{{ $goal->financial_account_id }}" @selected((string) old('savings_goal_id', $plannedMovement?->savings_goal_id) === (string) $goal->id)>{{ $goal->name }} · {{ $goal->account->name }}{{ $goal->archived_at ? ' (archivado)' : '' }}</option>@endforeach</select><small class="field__hint">El destino debe ser la cuenta vinculada al objetivo.</small></div>
                <div class="field field--wide"><label class="field__label" for="planned-notes">Notas <span class="field__optional">opcional</span></label><textarea class="field__control field__control--textarea" id="planned-notes" name="notes" maxlength="5000">{{ old('notes', $plannedMovement?->notes) }}</textarea></div>
                @include('movements._tags', ['selectedTagIds' => $plannedMovement?->tags?->modelKeys() ?? []])
                @include('movements._custom-fields')
            </div>
        </div></section>
        <div class="wizard__actions"><a class="button button--secondary" href="{{ route('planned-movements.index', $project) }}">Cancelar</a><button class="button button--primary" type="submit">{{ $editing ? 'Guardar cambios' : 'Crear planificación' }}</button></div>
    </form>
@endsection
