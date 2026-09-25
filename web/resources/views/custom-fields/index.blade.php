@extends('layouts.app')

@section('title', 'Campos personalizados de '.$project->name)

@section('content')
    <header class="page-heading page-heading--compact">
        <div class="page-heading__copy">
            <a class="back-link" href="{{ route('projects.show', $project) }}">← {{ $project->name }}</a>
            <p class="eyebrow">Información adicional</p>
            <h1 class="page-heading__title">Campos personalizados</h1>
            <p class="page-heading__intro">Añade datos propios a movimientos, planificaciones y series recurrentes sin cambiar la estructura de SmartWallet.</p>
        </div>
    </header>

    @include('projects._navigation', ['project' => $project])

    @if ($project->isArchived())
        <div class="alert alert--warning" role="status">Los campos y sus valores permanecen en modo de solo lectura mientras el proyecto esté archivado.</div>
    @elseif (! $canManage)
        <div class="alert" role="status">Puedes utilizar estos campos en los movimientos. Solo los propietarios administran sus definiciones.</div>
    @endif

    @if ($canManage && $activeCount < 10)
        <section class="management-panel" aria-labelledby="custom-field-new-title">
            <div class="management-panel__heading">
                <div><p class="eyebrow">{{ $activeCount }} de 10 activos</p><h2 class="management-panel__title" id="custom-field-new-title">Nuevo campo</h2></div>
                <p class="management-panel__intro">Todos los campos son opcionales. El tipo quedará bloqueado cuando se utilice por primera vez.</p>
            </div>
            <form class="form" action="{{ route('custom-fields.store', $project) }}" method="post">
                @csrf
                <div class="form-grid">
                    <div class="field"><label class="field__label" for="custom-field-name">Nombre</label><input class="field__control" id="custom-field-name" name="name" value="{{ old('name') }}" maxlength="80" required></div>
                    <div class="field"><label class="field__label" for="custom-field-type">Tipo</label><select class="field__control" id="custom-field-type" name="type" required>@foreach($fieldTypes as $fieldType)<option value="{{ $fieldType->value }}" @selected(old('type') === $fieldType->value)>{{ $fieldType->label() }}</option>@endforeach</select></div>
                    <fieldset class="field field--wide"><legend class="field__label">Tipos de movimiento donde aparecerá</legend><div class="choice-grid">@foreach($movementTypes as $movementType)<label class="checkbox-card"><input type="checkbox" name="applicable_movement_types[]" value="{{ $movementType->value }}" @checked(in_array($movementType->value, old('applicable_movement_types', array_map(fn($type) => $type->value, $movementTypes)), true))><span>{{ $movementType->label() }}</span></label>@endforeach</div></fieldset>
                </div>
                <div class="form-actions"><button class="button button--primary" type="submit">Crear campo</button></div>
            </form>
        </section>
    @elseif ($canManage)
        <div class="alert alert--warning" role="status">Has alcanzado el máximo de diez campos activos. Puedes archivar uno antes de crear o reactivar otro.</div>
    @endif

    <section class="content-section" aria-labelledby="custom-field-list-title">
        <div class="section-heading"><div><p class="eyebrow">Orden de aparición</p><h2 class="section-heading__title" id="custom-field-list-title">Campos del proyecto</h2></div></div>
        <div class="custom-field-definition-list">
            @foreach ($definitions as $definition)
                <article class="custom-field-definition {{ $definition->isArchived() ? 'custom-field-definition--archived' : '' }}">
                    <div class="custom-field-definition__summary">
                        <span class="custom-field-definition__type">{{ $definition->type->label() }}</span>
                        <div><h3>{{ $definition->name }}</h3><p>@foreach($definition->applicable_movement_types as $movementType){{ \App\Enums\MovementType::from($movementType)->label() }}{{ ! $loop->last ? ' · ' : '' }}@endforeach</p></div>
                        <span class="badge {{ $definition->isArchived() ? 'badge--archived' : '' }}">{{ $definition->isArchived() ? 'Archivado' : $definition->values_count.' valores' }}</span>
                    </div>

                    @if ($canManage)
                        @if ($definition->isArchived())
                            <div class="custom-field-definition__actions">
                                <form action="{{ route('custom-fields.restore', [$project, $definition]) }}" method="post">@csrf<button class="button button--secondary button--small" type="submit">Reactivar</button></form>
                                @if ($definition->values_count === 0)<form action="{{ route('custom-fields.destroy', [$project, $definition]) }}" method="post" data-confirm="¿Eliminar definitivamente este campo vacío?">@csrf @method('delete')<button class="button button--danger-quiet button--small" type="submit">Eliminar</button></form>@endif
                            </div>
                        @else
                            <details class="custom-field-definition__editor">
                                <summary>Editar definición</summary>
                                <form class="form" action="{{ route('custom-fields.update', [$project, $definition]) }}" method="post">@csrf @method('patch')
                                    <div class="form-grid">
                                        <div class="field"><label class="field__label" for="custom-field-name-{{ $definition->id }}">Nombre</label><input class="field__control" id="custom-field-name-{{ $definition->id }}" name="name" value="{{ $definition->name }}" maxlength="80" required></div>
                                        <div class="field"><label class="field__label" for="custom-field-type-{{ $definition->id }}">Tipo</label>@if($definition->values_count > 0)<input type="hidden" name="type" value="{{ $definition->type->value }}"><input class="field__control" id="custom-field-type-{{ $definition->id }}" value="{{ $definition->type->label() }}" disabled><small class="field__hint">Bloqueado porque ya contiene valores.</small>@else<select class="field__control" id="custom-field-type-{{ $definition->id }}" name="type">@foreach($fieldTypes as $fieldType)<option value="{{ $fieldType->value }}" @selected($definition->type === $fieldType)>{{ $fieldType->label() }}</option>@endforeach</select>@endif</div>
                                        <fieldset class="field field--wide"><legend class="field__label">Tipos de movimiento</legend><div class="choice-grid">@foreach($movementTypes as $movementType)<label class="checkbox-card"><input type="checkbox" name="applicable_movement_types[]" value="{{ $movementType->value }}" @checked(in_array($movementType->value, $definition->applicable_movement_types, true))><span>{{ $movementType->label() }}</span></label>@endforeach</div></fieldset>
                                    </div>
                                    <div class="form-actions"><button class="button button--primary button--small" type="submit">Guardar cambios</button></div>
                                </form>
                            </details>
                            <div class="custom-field-definition__actions">
                                <form action="{{ route('custom-fields.move', [$project, $definition]) }}" method="post">@csrf<input type="hidden" name="direction" value="up"><button class="button button--quiet button--small" type="submit" aria-label="Subir {{ $definition->name }}">↑</button></form>
                                <form action="{{ route('custom-fields.move', [$project, $definition]) }}" method="post">@csrf<input type="hidden" name="direction" value="down"><button class="button button--quiet button--small" type="submit" aria-label="Bajar {{ $definition->name }}">↓</button></form>
                                <form action="{{ route('custom-fields.archive', [$project, $definition]) }}" method="post">@csrf<button class="button button--secondary button--small" type="submit">Archivar</button></form>
                                @if ($definition->values_count === 0)<form action="{{ route('custom-fields.destroy', [$project, $definition]) }}" method="post" data-confirm="¿Eliminar definitivamente este campo vacío?">@csrf @method('delete')<button class="button button--danger-quiet button--small" type="submit">Eliminar</button></form>@endif
                            </div>
                        @endif
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@endsection
