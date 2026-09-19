@php($editing = isset($account))
<div class="form-grid">
    <div class="field">
        <label class="field__label" for="account-name">Nombre</label>
        <input class="field__control" id="account-name" name="name" value="{{ old('name', $account->name ?? '') }}" maxlength="120" placeholder="Ej. Cuenta común" required>
    </div>
    <div class="field">
        <label class="field__label" for="account-type">Tipo</label>
        <select class="field__control" id="account-type" name="type" required @disabled(($hasMovements ?? false))>
            @foreach ($accountTypes as $type)
                <option value="{{ $type->value }}" @selected(old('type', isset($account) ? $account->type->value : 'checking') === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
        @if ($hasMovements ?? false)
            <input type="hidden" name="type" value="{{ $account->type->value }}">
            <p class="field__help">El tipo queda protegido porque esta cuenta ya tiene movimientos.</p>
        @endif
    </div>
    <div class="field">
        <label class="field__label" for="account-balance">{{ isset($account) && $account->type === \App\Enums\FinancialAccountType::CreditCard ? 'Deuda inicial' : 'Saldo inicial' }}</label>
        <div class="money-field"><input class="field__control money-field__control" id="account-balance" name="initial_balance" inputmode="decimal" value="{{ old('initial_balance', isset($account) ? number_format($account->initial_balance_cents / 100, 2, ',', '.') : '0,00') }}" required><span class="money-field__suffix">€</span></div>
    </div>
    <div class="field">
        <label class="field__label" for="account-date">Fecha del saldo inicial</label>
        <input class="field__control" id="account-date" name="initial_balance_date" type="date" value="{{ old('initial_balance_date', isset($account) ? $account->initial_balance_date->toDateString() : $today) }}" required>
    </div>
    <div class="field">
        <label class="field__label" for="account-limit">Límite de crédito <span class="field__optional">solo tarjeta</span></label>
        <div class="money-field"><input class="field__control money-field__control" id="account-limit" name="credit_limit" inputmode="decimal" value="{{ old('credit_limit', isset($account) && $account->credit_limit_cents !== null ? number_format($account->credit_limit_cents / 100, 2, ',', '.') : '') }}"><span class="money-field__suffix">€</span></div>
    </div>
    <div class="field">
        <label class="field__label" for="account-icon">Icono</label>
        <select class="field__control" id="account-icon" name="icon" required>
            @foreach (['wallet' => 'Cartera', 'bank' => 'Banco', 'cash' => 'Efectivo', 'card' => 'Tarjeta', 'savings' => 'Ahorro', 'investment' => 'Inversión'] as $value => $label)
                <option value="{{ $value }}" @selected(old('icon', $account->icon ?? 'wallet') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label class="field__label" for="account-color">Color</label>
        <input class="field__control field__control--color" id="account-color" name="color" type="color" value="{{ old('color', $account->color ?? '#147d68') }}" required>
    </div>
</div>
