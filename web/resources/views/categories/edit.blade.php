@extends('layouts.app')

@section('title', 'Editar '.$category->name)

@section('content')
    <header class="page-heading page-heading--compact">
        <div class="page-heading__copy">
            <a class="back-link" href="{{ route('categories.index', $project) }}">← Categorías</a>
            <p class="eyebrow">{{ $project->name }}</p>
            <h1 class="page-heading__title">Editar {{ $category->name }}</h1>
            <p class="page-heading__intro">
                {{ $category->isMain() ? 'Categoría principal de '.$category->type->label().'.' : 'Subcategoría de '.$category->parent->name.'.' }}
            </p>
        </div>
    </header>

    @include('projects._navigation', ['project' => $project])

    <section class="management-panel management-panel--narrow" aria-labelledby="edit-category-title">
        <div class="management-panel__heading">
            <div>
                <p class="eyebrow">Aspecto y nombre</p>
                <h2 class="management-panel__title" id="edit-category-title">Datos de la categoría</h2>
            </div>
            <p class="management-panel__intro">
                El tipo y el nivel no cambian para proteger los movimientos históricos.
            </p>
        </div>

        <form class="form" action="{{ route('categories.update', [$project, $category]) }}" method="post">
            @csrf
            @method('patch')
            <div class="field">
                <label class="field__label" for="category-name">Nombre</label>
                <input
                    class="field__control {{ $errors->has('name') ? 'field__control--invalid' : '' }}"
                    id="category-name"
                    name="name"
                    type="text"
                    value="{{ old('name', $category->name) }}"
                    maxlength="120"
                    required
                >
            </div>
            <div class="form-grid">
                <div class="field">
                    <label class="field__label" for="category-color">Color</label>
                    <input
                        class="field__control field__control--color"
                        id="category-color"
                        name="color"
                        type="color"
                        value="{{ old('color', $category->color) }}"
                        required
                    >
                </div>
                <div class="field">
                    <label class="field__label" for="category-icon">Icono</label>
                    <select class="field__control" id="category-icon" name="icon" required>
                        @foreach ($iconOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('icon', $category->icon) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-actions form-actions--split">
                <a class="button button--secondary" href="{{ route('categories.index', $project) }}">Cancelar</a>
                <button class="button button--primary" type="submit">Guardar cambios</button>
            </div>
        </form>
    </section>
@endsection
