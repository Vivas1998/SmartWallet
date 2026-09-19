@extends('layouts.app')

@section('title', 'Editar '.$goal->name)

@section('content')
    <header class="page-heading page-heading--compact"><div class="page-heading__copy"><a class="back-link" href="{{ route('savings-goals.index', $project) }}">← Objetivos</a><p class="eyebrow">{{ $project->name }}</p><h1 class="page-heading__title">Editar objetivo</h1><p class="page-heading__intro">Las aportaciones existentes conservarán su relación con el objetivo.</p></div></header>
    <form class="form management-panel" action="{{ route('savings-goals.update', [$project, $goal]) }}" method="post">@csrf @method('put') @include('savings-goals._form')<div class="form-actions"><a class="button button--secondary" href="{{ route('savings-goals.index', $project) }}">Cancelar</a><button class="button button--primary" type="submit">Guardar cambios</button></div></form>
@endsection
