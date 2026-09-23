@extends('layouts.app')

@php($editing = $movement !== null)
@section('title', ($editing ? 'Editar devolución' : 'Nueva devolución').' en '.$project->name)

@section('content')
    @php($availableCents = $original->amount_cents - ($editing ? (int) $original->refunds()->whereNull('trashed_at')->whereKeyNot($movement->id)->sum('amount_cents') : $refundedCents))
    <header class="page-heading page-heading--compact"><div class="page-heading__copy"><a class="back-link" href="{{ route('movements.index', $project) }}">← Movimientos</a><p class="eyebrow">{{ $project->name }}</p><h1 class="page-heading__title">{{ $editing ? 'Editar devolución' : 'Registrar devolución' }}</h1><p class="page-heading__intro">Devuelve saldo a la cuenta y reduce el gasto neto de la categoría y del presupuesto.</p></div></header>

    <article class="linked-movement">
        <div><small>Gasto original</small><strong>{{ $original->concept }}</strong><span>{{ $original->occurred_on->format('d/m/Y') }} · {{ $original->category?->name }}@if ($original->subcategory) / {{ $original->subcategory->name }}@endif · {{ $original->account?->name }}</span></div>
        <div class="linked-movement__amount"><small>Pendiente de devolver</small><strong>{{ number_format($availableCents / 100, 2, ',', '.') }} €</strong></div>
    </article>

    <form class="form movement-form" action="{{ $editing ? route('movements.update', [$project, $movement]) : route('movements.refund.store', [$project, $original]) }}" method="post">
        @csrf
        @if ($editing) @method('patch') @endif
        <section class="wizard__section"><span class="wizard__step" aria-hidden="true">1</span><div class="wizard__content"><h2 class="wizard__title">Datos de la devolución</h2><p class="wizard__intro">Puede ser parcial; varias devoluciones nunca podrán superar el gasto original.</p><div class="form-grid">
            <div class="field"><label class="field__label" for="refund-amount">Importe</label><div class="money-field"><input class="field__control money-field__control" id="refund-amount" name="amount" inputmode="decimal" value="{{ old('amount', $editing ? number_format($movement->amount_cents / 100, 2, ',', '.') : '') }}" placeholder="0,00" required><span class="money-field__suffix">€</span></div></div>
            <div class="field"><label class="field__label" for="refund-date">Fecha</label><input class="field__control" id="refund-date" name="occurred_on" type="date" min="{{ $original->occurred_on->toDateString() }}" max="{{ $today }}" value="{{ old('occurred_on', $editing ? $movement->occurred_on->toDateString() : $today) }}" required></div>
            <div class="field field--wide"><label class="field__label" for="refund-concept">Concepto</label><input class="field__control" id="refund-concept" name="concept" value="{{ old('concept', $movement?->concept ?? 'Devolución de '.$original->concept) }}" maxlength="180" required></div>
            <div class="field field--wide"><label class="field__label" for="refund-notes">Notas <span class="field__optional">opcional</span></label><textarea class="field__control field__control--textarea" id="refund-notes" name="notes" maxlength="5000">{{ old('notes', $movement?->notes) }}</textarea></div>
            @include('movements._tags', ['selectedTagIds' => $movement?->tags?->modelKeys() ?? $original->tags->whereNull('archived_at')->modelKeys()])
            @include('movements._calendar-toggle', ['movement' => $movement])
        </div></div></section>
        <div class="wizard__actions"><a class="button button--secondary" href="{{ route('movements.index', $project) }}">Cancelar</a><button class="button button--primary" type="submit">{{ $editing ? 'Guardar cambios' : 'Registrar devolución' }}</button></div>
    </form>
@endsection
