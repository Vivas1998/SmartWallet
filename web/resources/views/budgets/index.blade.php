@extends('layouts.app')

@section('title', 'Presupuesto de '.$project->name)

@section('content')
    @php
        $formatMoney = fn (int $cents) => number_format($cents / 100, 2, ',', '.').' €';
        $formatInput = fn (int $cents) => number_format($cents / 100, 2, ',', '');
        $progress = $budget->total_limit_cents > 0
            ? max(0, min(100, (int) round(($spentCents / $budget->total_limit_cents) * 100)))
            : ($spentCents > 0 ? 100 : 0);
    @endphp

    <header class="page-heading page-heading--compact">
        <div class="page-heading__copy">
            <a class="back-link" href="{{ route('projects.show', $project) }}">← {{ $project->name }}</a>
            <p class="eyebrow">Plan mensual compartido</p>
            <h1 class="page-heading__title">Presupuesto</h1>
            <p class="page-heading__intro">Define cuánto queréis gastar y comprueba el margen disponible.</p>
        </div>
    </header>

    @include('projects._navigation', ['project' => $project])

    <nav class="month-switcher" aria-label="Cambiar mes">
        <a class="button button--secondary button--small" href="{{ route('budgets.index', ['project' => $project, 'month' => $month->subMonth()->format('Y-m')]) }}">← Anterior</a>
        <div class="month-switcher__current">
            <span class="eyebrow">Mes consultado</span>
            <strong>{{ ucfirst($month->locale('es')->translatedFormat('F Y')) }}</strong>
        </div>
        <a class="button button--secondary button--small" href="{{ route('budgets.index', ['project' => $project, 'month' => $month->addMonth()->format('Y-m')]) }}">Siguiente →</a>
    </nav>

    @if ($project->isArchived())
        <div class="alert alert--warning" role="status">El presupuesto se conserva en modo de solo lectura.</div>
    @endif

    <section class="budget-summary" aria-label="Estado del presupuesto">
        <div class="budget-summary__ring {{ $remainingCents < 0 ? 'budget-summary__ring--danger' : '' }}" style="--ring-progress: {{ $progress }}%">
            <span class="budget-summary__ring-value">{{ $progress }}%</span>
            <span class="budget-summary__ring-label">consumido</span>
        </div>
        <dl class="budget-summary__values">
            <div><dt>Presupuesto</dt><dd>{{ $formatMoney($budget->total_limit_cents) }}</dd></div>
            <div><dt>Gasto neto</dt><dd>{{ $formatMoney($spentCents) }}</dd></div>
            <div class="{{ $remainingCents < 0 ? 'budget-summary__negative' : '' }}"><dt>Disponible</dt><dd>{{ $formatMoney($remainingCents) }}</dd></div>
        </dl>
    </section>

    @if ($canManage)
        <form class="form" action="{{ route('budgets.update', $project) }}" method="post">
            @csrf
            @method('PUT')
            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">

            <section class="management-panel" aria-labelledby="budget-total-title">
                <div class="management-panel__heading">
                    <div>
                        <p class="eyebrow">Límite global</p>
                        <h2 class="management-panel__title" id="budget-total-title">Presupuesto del mes</h2>
                    </div>
                    <p class="management-panel__intro">Los ingresos no modifican este importe automáticamente.</p>
                </div>
                <div class="field budget-total-field">
                    <label class="field__label" for="total-limit">Importe total</label>
                    <div class="money-field">
                        <input class="field__control money-field__control" id="total-limit" name="total_limit" inputmode="decimal" value="{{ old('total_limit', $formatInput($budget->total_limit_cents)) }}" required>
                        <span class="money-field__suffix">€</span>
                    </div>
                </div>
            </section>

            <section aria-labelledby="category-limits-title">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Distribución opcional</p>
                        <h2 class="section-heading__title" id="category-limits-title">Límites por categoría principal</h2>
                    </div>
                </div>
                <div class="budget-category-list">
                    @foreach ($categories as $category)
                        @php
                            $limitCents = (int) ($limits->get($category->id)?->limit_cents ?? 0);
                            $categorySpent = (int) ($spentByCategory->get($category->id) ?? 0);
                            $categoryProgress = $limitCents > 0 ? max(0, min(100, (int) round(($categorySpent / $limitCents) * 100))) : ($categorySpent > 0 ? 100 : 0);
                        @endphp
                        <article class="budget-category {{ $category->isArchived() ? 'budget-category--archived' : '' }}">
                            <div class="budget-category__identity">
                                <span class="category-item__icon" style="--category-color: {{ $category->color }}" aria-hidden="true">{{ $category->iconSymbol() }}</span>
                                <div>
                                    <h3 class="budget-category__name">{{ $category->name }}</h3>
                                    <p class="budget-category__spent">Gastado: {{ $formatMoney($categorySpent) }}</p>
                                </div>
                            </div>
                            @if ($category->isArchived())
                                <div class="budget-category__archived-value"><span class="badge badge--archived">Archivada</span><strong>{{ $formatMoney($limitCents) }}</strong></div>
                            @else
                                <div class="field budget-category__field">
                                    <label class="field__label" for="limit-{{ $category->id }}">Límite</label>
                                    <div class="money-field">
                                        <input class="field__control money-field__control" id="limit-{{ $category->id }}" name="limits[{{ $category->id }}]" inputmode="decimal" value="{{ old('limits.'.$category->id, $formatInput($limitCents)) }}">
                                        <span class="money-field__suffix">€</span>
                                    </div>
                                </div>
                            @endif
                            <div class="progress-bar" role="progressbar" aria-label="Presupuesto consumido en {{ $category->name }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $categoryProgress }}"><span class="progress-bar__value {{ $categoryProgress >= 100 ? 'progress-bar__value--danger' : ($categoryProgress >= 80 ? 'progress-bar__value--warning' : '') }}" style="width: {{ $categoryProgress }}%"></span></div>
                        </article>
                    @endforeach
                </div>
            </section>

            <fieldset class="budget-scope">
                <legend class="budget-scope__legend">Aplicar el cambio</legend>
                <label class="choice-card">
                    <input type="radio" name="scope" value="month" @checked(old('scope') === 'month')>
                    <span><strong>Solo este mes</strong><small>Ideal para una excepción, como Navidad.</small></span>
                </label>
                <label class="choice-card">
                    <input type="radio" name="scope" value="future" @checked(old('scope', 'future') === 'future')>
                    <span><strong>Este mes y próximos</strong><small>Será la base habitual al abrir meses nuevos.</small></span>
                </label>
            </fieldset>

            <div class="form-actions"><button class="button button--primary" type="submit">Guardar presupuesto</button></div>
        </form>
    @else
        <section aria-labelledby="category-limits-title">
            <div class="section-heading"><div><p class="eyebrow">Distribución</p><h2 class="section-heading__title" id="category-limits-title">Límites por categoría</h2></div></div>
            <div class="budget-category-list">
                @foreach ($categories as $category)
                    @php($limitCents = (int) ($limits->get($category->id)?->limit_cents ?? 0))
                    @php($categorySpent = (int) ($spentByCategory->get($category->id) ?? 0))
                    <article class="budget-category">
                        <div class="budget-category__identity"><span class="category-item__icon" style="--category-color: {{ $category->color }}" aria-hidden="true">{{ $category->iconSymbol() }}</span><div><h3 class="budget-category__name">{{ $category->name }}</h3><p class="budget-category__spent">{{ $formatMoney($categorySpent) }} gastados de {{ $formatMoney($limitCents) }}</p></div></div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="closure-callout" aria-labelledby="closure-callout-title">
        <div><p class="eyebrow">Fin de mes</p><h2 class="closure-callout__title" id="closure-callout-title">Cierre mensual sin bloquear el periodo</h2><p>{{ $month->endOfMonth()->isPast() ? 'Consulta cuánto sobró y, si quieres, destina una parte a ahorro o inversión.' : 'El resumen definitivo y la opción de destinar sobrante estarán disponibles cuando termine el mes.' }}</p></div>
        <a class="button button--secondary" href="{{ route('budgets.closure', ['project' => $project, 'month' => $month->format('Y-m')]) }}">Ver cierre mensual</a>
    </section>
@endsection
