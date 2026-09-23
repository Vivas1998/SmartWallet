@extends('layouts.app')

@section('title', 'Nuevo movimiento en '.$project->name)

@section('content')
    <header class="page-heading page-heading--compact">
        <div class="page-heading__copy">
            <a class="back-link" href="{{ route('movements.index', $project) }}">← Movimientos</a>
            <p class="eyebrow">{{ $project->name }}</p>
            <h1 class="page-heading__title">Nuevo movimiento</h1>
            <p class="page-heading__intro">Registra un gasto o un ingreso manualmente.</p>
        </div>
        <a class="button button--secondary" href="{{ route('movements.transfer.create', $project) }}">↔ Hacer transferencia</a>
    </header>

    @if ($possibleDuplicate)
        <div class="alert alert--warning" role="alert">
            <p class="alert__title">Puede que este movimiento ya esté registrado.</p>
            <p class="duplicate-warning__text">Coincide en categoría, subcategoría, fecha e importe con “{{ $possibleDuplicate['concept'] }}” del {{ $possibleDuplicate['date'] }} ({{ $possibleDuplicate['amount'] }}). Revisa los datos o pulsa «Guardar igualmente».</p>
        </div>
    @endif

    <form class="form movement-form" action="{{ route('movements.store', $project) }}" method="post" data-movement-form>
        @csrf
        @if ($possibleDuplicate)<input type="hidden" name="allow_duplicate" value="1">@endif

        <section class="wizard__section">
            <span class="wizard__step" aria-hidden="true">1</span>
            <div class="wizard__content">
                <h2 class="wizard__title">Datos principales</h2>
                <p class="wizard__intro">El importe siempre se escribe en positivo; el tipo indica si entra o sale.</p>
                <div class="form-grid">
                    <div class="field">
                        <label class="field__label" for="movement-type">Tipo</label>
                        <select class="field__control" id="movement-type" name="type" data-movement-type required>
                            <option value="expense" @selected(old('type', 'expense') === 'expense')>Gasto</option>
                            <option value="income" @selected(old('type') === 'income')>Ingreso</option>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label" for="movement-amount">Importe</label>
                        <div class="money-field"><input class="field__control money-field__control" id="movement-amount" name="amount" inputmode="decimal" value="{{ old('amount') }}" placeholder="0,00" required><span class="money-field__suffix">€</span></div>
                    </div>
                    <div class="field">
                        <label class="field__label" for="movement-date">Fecha</label>
                        <input class="field__control" id="movement-date" name="occurred_on" type="date" max="{{ $today }}" value="{{ old('occurred_on', $today) }}" required>
                    </div>
                    <div class="field">
                        <label class="field__label" for="movement-concept">Concepto</label>
                        <input class="field__control" id="movement-concept" name="concept" value="{{ old('concept') }}" maxlength="180" placeholder="Ej. Compra semanal" required>
                    </div>
                </div>
            </div>
        </section>

        <section class="wizard__section">
            <span class="wizard__step" aria-hidden="true">2</span>
            <div class="wizard__content">
                <h2 class="wizard__title">Clasificación y cuenta</h2>
                <p class="wizard__intro">La subcategoría es opcional y se adapta a la categoría principal.</p>
                <div class="form-grid">
                    <div class="field">
                        <label class="field__label" for="movement-category">Categoría</label>
                        <select class="field__control" id="movement-category" name="category_id" data-movement-category required>
                            <option value="">Selecciona una categoría</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" data-category-type="{{ $category->type->value }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label" for="movement-subcategory">Subcategoría <span class="field__optional">opcional</span></label>
                        <select class="field__control" id="movement-subcategory" name="subcategory_id" data-movement-subcategory>
                            <option value="">Sin subcategoría</option>
                            @foreach ($categories as $category)
                                @foreach ($category->children as $subcategory)
                                    <option value="{{ $subcategory->id }}" data-parent-id="{{ $category->id }}" data-category-type="{{ $subcategory->type->value }}" @selected((string) old('subcategory_id') === (string) $subcategory->id)>{{ $subcategory->name }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label" for="movement-account">Cuenta</label>
                        <select class="field__control" id="movement-account" name="financial_account_id" data-movement-account required>
                            <option value="">Selecciona una cuenta</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" data-account-type="{{ $account->type->value }}" @selected((string) old('financial_account_id') === (string) $account->id)>{{ $account->name }} · {{ $account->type->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label" for="movement-payer">Pagado o recibido por</label>
                        <select class="field__control" id="movement-payer" name="paid_by_user_id">
                            @foreach ($members as $member)
                                <option value="{{ $member->id }}" @selected((string) old('paid_by_user_id', auth()->id()) === (string) $member->id)>{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field field--wide">
                        <label class="field__label" for="movement-notes">Notas <span class="field__optional">opcional</span></label>
                        <textarea class="field__control field__control--textarea" id="movement-notes" name="notes" maxlength="5000">{{ old('notes') }}</textarea>
                    </div>
                    @include('movements._tags', ['selectedTagIds' => []])
                    @include('movements._calendar-toggle', ['movement' => null])
                </div>
            </div>
        </section>

        <div class="wizard__actions">
            <a class="button button--secondary" href="{{ route('movements.index', $project) }}">Cancelar</a>
            <button class="button button--primary" type="submit">{{ $possibleDuplicate ? 'Guardar igualmente' : 'Guardar movimiento' }}</button>
        </div>
    </form>
@endsection
