@extends('layouts.app')

@section('title', 'Categorías de '.$project->name)

@section('content')
    <header class="page-heading page-heading--compact">
        <div class="page-heading__copy">
            <a class="back-link" href="{{ route('projects.show', $project) }}">← {{ $project->name }}</a>
            <p class="eyebrow">Clasificación propia del proyecto</p>
            <h1 class="page-heading__title">Categorías</h1>
            <p class="page-heading__intro">
                Dos niveles sencillos para entender dónde entra y dónde se va el dinero.
            </p>
        </div>
    </header>

    @include('projects._navigation', ['project' => $project])

    @if ($project->isArchived())
        <div class="alert alert--warning" role="status">
            El catálogo se conserva en modo de solo lectura mientras el proyecto esté archivado.
        </div>
    @endif

    @if ($canManage)
        <section class="management-panel" aria-labelledby="new-category-title">
            <div class="management-panel__heading">
                <div>
                    <p class="eyebrow">Adaptar catálogo</p>
                    <h2 class="management-panel__title" id="new-category-title">Nueva categoría</h2>
                </div>
                <p class="management-panel__intro">
                    Si eliges una categoría principal, el nuevo elemento se guardará como subcategoría.
                </p>
            </div>

            <form class="form" action="{{ route('categories.store', $project) }}" method="post">
                @csrf
                <div class="form-grid">
                    <div class="field">
                        <label class="field__label" for="category-name">Nombre</label>
                        <input
                            class="field__control {{ $errors->has('name') ? 'field__control--invalid' : '' }}"
                            id="category-name"
                            name="name"
                            type="text"
                            value="{{ old('name') }}"
                            maxlength="120"
                            required
                        >
                    </div>
                    <div class="field">
                        <label class="field__label" for="category-type">Tipo</label>
                        <select class="field__control" id="category-type" name="type" data-category-type required>
                            @foreach ($categoryTypes as $type)
                                <option value="{{ $type->value }}" @selected(old('type', 'expense') === $type->value)>
                                    {{ $type->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label" for="category-parent">Dentro de</label>
                        <select
                            class="field__control {{ $errors->has('parent_id') ? 'field__control--invalid' : '' }}"
                            id="category-parent"
                            name="parent_id"
                            data-category-parent
                        >
                            <option value="">Ninguna: será categoría principal</option>
                            @foreach ($categories->flatten(1)->whereNull('archived_at') as $parent)
                                <option
                                    value="{{ $parent->id }}"
                                    data-category-type="{{ $parent->type->value }}"
                                    @selected((string) old('parent_id') === (string) $parent->id)
                                >{{ $parent->name }}</option>
                            @endforeach
                        </select>
                        <p class="field__hint">Solo aparecen opciones del tipo de categoría seleccionado.</p>
                    </div>
                    <div class="field">
                        <label class="field__label" for="category-icon">Icono</label>
                        <select class="field__control" id="category-icon" name="icon" required>
                            @foreach ($iconOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('icon', 'dot') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label" for="category-color">Color</label>
                        <input
                            class="field__control field__control--color"
                            id="category-color"
                            name="color"
                            type="color"
                            value="{{ old('color', '#147d68') }}"
                            required
                        >
                    </div>
                </div>
                <div class="form-actions">
                    <button class="button button--primary" type="submit">Crear categoría</button>
                </div>
            </form>
        </section>
    @endif

    <div class="category-sections">
        @foreach ($categoryTypes as $type)
            @php($typeCategories = $categories->get($type->value, collect()))
            <section class="category-section" aria-labelledby="category-section-{{ $type->value }}">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">{{ $type === \App\Enums\CategoryType::Expense ? 'El dinero que sale' : 'El dinero que entra' }}</p>
                        <h2 class="section-heading__title" id="category-section-{{ $type->value }}">{{ $type->label() }}</h2>
                    </div>
                    <span class="category-section__count">
                        {{ $typeCategories->whereNull('archived_at')->count() }} principales activas
                    </span>
                </div>

                <div class="category-list">
                    @foreach ($typeCategories as $category)
                        @include('categories._item', [
                            'category' => $category,
                            'project' => $project,
                            'canManage' => $canManage,
                            'iconOptions' => $iconOptions,
                            'ancestorArchived' => false,
                        ])
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endsection
