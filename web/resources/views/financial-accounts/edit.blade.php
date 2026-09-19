@extends('layouts.app')

@section('title', 'Editar '.$account->name)

@section('content')
    <header class="page-heading page-heading--compact"><div class="page-heading__copy"><a class="back-link" href="{{ route('financial-accounts.index', $project) }}">← Cuentas</a><p class="eyebrow">{{ $project->name }}</p><h1 class="page-heading__title">Editar cuenta</h1><p class="page-heading__intro">Los cambios quedan registrados en el historial de auditoría.</p></div></header>
    <form class="form movement-form" action="{{ route('financial-accounts.update', [$project, $account]) }}" method="post">
        @csrf
        @method('patch')
        <section class="wizard__section"><span class="wizard__step" aria-hidden="true">1</span><div class="wizard__content"><h2 class="wizard__title">Datos de {{ $account->name }}</h2><p class="wizard__intro">Si corriges el saldo inicial, todos los saldos calculados se actualizarán.</p>@include('financial-accounts._form', ['today' => now('Europe/Madrid')->toDateString()])</div></section>
        <div class="wizard__actions"><a class="button button--secondary" href="{{ route('financial-accounts.index', $project) }}">Cancelar</a><button class="button button--primary" type="submit">Guardar cambios</button></div>
    </form>
@endsection
