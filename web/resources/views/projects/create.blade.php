@extends('layouts.app')

@section('title', 'Crear proyecto')

@section('content')
    @php
        $budgetMonthLabel = ucfirst($budgetMonth->locale('es')->translatedFormat('F Y'));
    @endphp

    <header class="page-heading page-heading--compact">
        <div class="page-heading__copy">
            <a class="back-link" href="{{ route('dashboard') }}">← Mis proyectos</a>
            <h1 class="page-heading__title">Crear proyecto</h1>
            <p class="page-heading__intro">Configura el espacio, su cuenta principal y el presupuesto del primer mes.</p>
        </div>
    </header>

    <form class="wizard" action="{{ route('projects.store') }}" method="post" data-project-wizard novalidate>
        @csrf

        <ol class="wizard-progress" aria-label="Progreso de creación">
            <li class="wizard-progress__item wizard-progress__item--active" data-wizard-indicator="1">
                <button class="wizard-progress__button" type="button" data-wizard-go="1" aria-label="Paso 1: Identidad" aria-current="step">
                    <span class="wizard-progress__number">1</span>
                    <span class="wizard-progress__label">Identidad</span>
                </button>
            </li>
            <li class="wizard-progress__item" data-wizard-indicator="2">
                <button class="wizard-progress__button" type="button" data-wizard-go="2" aria-label="Paso 2: Cuenta" disabled>
                    <span class="wizard-progress__number">2</span>
                    <span class="wizard-progress__label">Cuenta</span>
                </button>
            </li>
            <li class="wizard-progress__item" data-wizard-indicator="3">
                <button class="wizard-progress__button" type="button" data-wizard-go="3" aria-label="Paso 3: Presupuesto" disabled>
                    <span class="wizard-progress__number">3</span>
                    <span class="wizard-progress__label">Presupuesto</span>
                </button>
            </li>
            <li class="wizard-progress__item" data-wizard-indicator="4">
                <button class="wizard-progress__button" type="button" data-wizard-go="4" aria-label="Paso 4: Revisión" disabled>
                    <span class="wizard-progress__number">4</span>
                    <span class="wizard-progress__label">Revisión</span>
                </button>
            </li>
        </ol>

        <p class="wizard__status" aria-live="polite" data-wizard-status>Paso 1 de 4: Identidad</p>

        <section class="wizard__section" aria-labelledby="identity-title" data-wizard-panel="1">
            <div class="wizard__step" aria-hidden="true">1</div>
            <div class="wizard__content">
                <h2 class="wizard__title" id="identity-title">Identidad del proyecto</h2>
                <p class="wizard__intro">Esta información ayudará a diferenciarlo de los demás.</p>

                <div class="form-grid">
                    <div class="field field--wide">
                        <label class="field__label" for="name">Nombre</label>
                        <input class="field__control @error('name') field__control--invalid @enderror" id="name" name="name" type="text" value="{{ old('name') }}" maxlength="120" placeholder="Por ejemplo, Economía familiar" data-review-name required>
                        @error('name')
                            <p class="field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field field--wide">
                        <label class="field__label" for="description">Descripción <span class="field__optional">(opcional)</span></label>
                        <textarea class="field__control field__control--textarea @error('description') field__control--invalid @enderror" id="description" name="description" maxlength="2000" placeholder="Gastos, ingresos y presupuesto de la casa" data-review-description>{{ old('description') }}</textarea>
                        @error('description')
                            <p class="field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field__label" for="color">Color</label>
                        <input class="field__control field__control--color" id="color" name="color" type="color" value="{{ old('color', '#147d68') }}" data-review-color required>
                    </div>

                    <div class="field">
                        <label class="field__label" for="icon">Icono</label>
                        <select class="field__control" id="icon" name="icon" data-review-icon required>
                            <option value="home" data-symbol="⌂" @selected(old('icon', 'home') === 'home')>Casa</option>
                            <option value="wallet" data-symbol="◫" @selected(old('icon') === 'wallet')>Cartera</option>
                            <option value="personal" data-symbol="●" @selected(old('icon') === 'personal')>Personal</option>
                            <option value="travel" data-symbol="✦" @selected(old('icon') === 'travel')>Viajes</option>
                            <option value="heart" data-symbol="♥" @selected(old('icon') === 'heart')>Familia</option>
                        </select>
                    </div>
                </div>

                <div class="wizard__navigation wizard__navigation--interactive">
                    <a class="button button--secondary" href="{{ route('dashboard') }}">Cancelar</a>
                    <button class="button button--primary" type="button" data-wizard-next>Continuar a cuenta</button>
                </div>
            </div>
        </section>

        <section class="wizard__section" aria-labelledby="account-title" data-wizard-panel="2">
            <div class="wizard__step" aria-hidden="true">2</div>
            <div class="wizard__content">
                <h2 class="wizard__title" id="account-title">Cuenta principal</h2>
                <p class="wizard__intro" id="initial-balance-help">El saldo inicial sirve como punto de partida y no se contará como ingreso.</p>

                <div class="form-grid">
                    <div class="field">
                        <label class="field__label" for="account_name">Nombre de la cuenta</label>
                        <input class="field__control @error('account_name') field__control--invalid @enderror" id="account_name" name="account_name" type="text" value="{{ old('account_name', 'Cuenta principal') }}" maxlength="120" data-review-account-name required>
                        @error('account_name')
                            <p class="field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field__label" for="account_type">Tipo</label>
                        <select class="field__control" id="account_type" name="account_type" data-review-account-type required>
                            @foreach ($accountTypes as $type)
                                <option value="{{ $type->value }}" @selected(old('account_type', 'checking') === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label class="field__label" for="initial_balance">Saldo inicial</label>
                        <div class="money-field">
                            <input class="field__control money-field__control @error('initial_balance') field__control--invalid @enderror" id="initial_balance" name="initial_balance" type="text" inputmode="decimal" value="{{ old('initial_balance', '0,00') }}" pattern="^-?(?:(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?|\d+(?:\.\d{1,2})?)$" aria-describedby="initial-balance-help" data-review-balance required>
                            <span class="money-field__suffix" aria-hidden="true">€</span>
                        </div>
                        @error('initial_balance')
                            <p class="field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field__label" for="initial_balance_date">Fecha del saldo</label>
                        <input class="field__control @error('initial_balance_date') field__control--invalid @enderror" id="initial_balance_date" name="initial_balance_date" type="date" value="{{ old('initial_balance_date', $initialDate) }}" data-review-balance-date required>
                        @error('initial_balance_date')
                            <p class="field__error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="wizard__navigation wizard__navigation--interactive">
                    <button class="button button--secondary" type="button" data-wizard-previous>Volver</button>
                    <button class="button button--primary" type="button" data-wizard-next>Continuar a presupuesto</button>
                </div>
            </div>
        </section>

        <section class="wizard__section" aria-labelledby="budget-title" data-wizard-panel="3">
            <div class="wizard__step" aria-hidden="true">3</div>
            <div class="wizard__content">
                <h2 class="wizard__title" id="budget-title">Presupuesto inicial</h2>
                <p class="wizard__intro" id="monthly-budget-help">Será el límite común de {{ $budgetMonthLabel }} y la base de los próximos meses. Podrás modificar cualquier mes después.</p>

                <div class="wizard-budget">
                    <div class="field wizard-budget__field">
                        <label class="field__label" for="monthly_budget">Presupuesto mensual total</label>
                        <div class="money-field">
                            <input class="field__control money-field__control @error('monthly_budget') field__control--invalid @enderror" id="monthly_budget" name="monthly_budget" type="text" inputmode="decimal" value="{{ old('monthly_budget', '0,00') }}" pattern="^(?:(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?|\d+(?:\.\d{1,2})?)$" aria-describedby="monthly-budget-help" data-review-budget required>
                            <span class="money-field__suffix" aria-hidden="true">€</span>
                        </div>
                        @error('monthly_budget')
                            <p class="field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="wizard-budget__note">
                        <span class="wizard-budget__note-icon" aria-hidden="true">↻</span>
                        <div>
                            <strong>Una base flexible</strong>
                            <p>El dinero sobrante no se arrastra. Los límites por categoría se pueden añadir desde Presupuesto cuando el proyecto ya esté creado.</p>
                        </div>
                    </div>
                </div>

                <div class="wizard__navigation wizard__navigation--interactive">
                    <button class="button button--secondary" type="button" data-wizard-previous>Volver</button>
                    <button class="button button--primary" type="button" data-wizard-next>Revisar proyecto</button>
                </div>
            </div>
        </section>

        <section class="wizard__section" aria-labelledby="review-title" data-wizard-panel="4">
            <div class="wizard__step" aria-hidden="true">4</div>
            <div class="wizard__content">
                <h2 class="wizard__title" id="review-title">Revisión final</h2>
                <p class="wizard__intro">Comprueba los datos. El proyecto no se creará hasta que confirmes este paso.</p>

                <div class="wizard-review">
                    <article class="wizard-review__project">
                        <span class="wizard-review__project-icon" data-review-output="icon" aria-hidden="true">⌂</span>
                        <div class="wizard-review__project-copy">
                            <span class="wizard-review__eyebrow">Proyecto</span>
                            <h3 data-review-output="name">Sin nombre</h3>
                            <p data-review-output="description">Sin descripción</p>
                        </div>
                    </article>

                    <dl class="wizard-review__details">
                        <div class="wizard-review__detail">
                            <dt>Cuenta principal</dt>
                            <dd data-review-output="account-name">Cuenta principal</dd>
                            <span data-review-output="account-type">Cuenta corriente</span>
                        </div>
                        <div class="wizard-review__detail">
                            <dt>Saldo inicial</dt>
                            <dd data-review-output="balance">0,00 €</dd>
                            <span data-review-output="balance-date">{{ $budgetMonth->format('d/m/Y') }}</span>
                        </div>
                        <div class="wizard-review__detail wizard-review__detail--accent">
                            <dt>Presupuesto mensual</dt>
                            <dd data-review-output="budget">0,00 €</dd>
                            <span>Desde {{ $budgetMonthLabel }}</span>
                        </div>
                    </dl>

                    <p class="wizard-review__notice">Se copiará el catálogo inicial de categorías y podrás añadir miembros cuando termine la creación.</p>
                </div>

                <div class="wizard__navigation wizard__navigation--interactive">
                    <button class="button button--secondary" type="button" data-wizard-previous>Volver</button>
                    <button class="button button--primary" type="submit">Crear proyecto</button>
                </div>
            </div>
        </section>

        <div class="wizard__actions wizard__fallback-actions">
            <a class="button button--secondary" href="{{ route('dashboard') }}">Cancelar</a>
            <button class="button button--primary" type="submit">Crear proyecto</button>
        </div>
    </form>
@endsection
