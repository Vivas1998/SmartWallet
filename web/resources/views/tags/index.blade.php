@extends('layouts.app')

@section('title', 'Etiquetas de '.$project->name)

@section('content')
    <header class="page-heading page-heading--compact">
        <div class="page-heading__copy">
            <a class="back-link" href="{{ route('projects.show', $project) }}">← {{ $project->name }}</a>
            <p class="eyebrow">Organización flexible</p>
            <h1 class="page-heading__title">Etiquetas</h1>
            <p class="page-heading__intro">Añade varias etiquetas a un movimiento para encontrarlo desde distintos puntos de vista, sin sustituir sus categorías.</p>
        </div>
    </header>

    @include('projects._navigation', ['project' => $project])

    @if ($project->isArchived())
        <div class="alert alert--warning" role="status">Las etiquetas se conservan en modo de solo lectura mientras el proyecto esté archivado.</div>
    @endif

    @if ($canCreate)
        <section class="management-panel" aria-labelledby="new-tag-title">
            <div class="management-panel__heading">
                <div><p class="eyebrow">Disponible para propietarios y miembros</p><h2 class="management-panel__title" id="new-tag-title">Nueva etiqueta</h2></div>
                <p class="management-panel__intro">Por ejemplo: Navidad, Trabajo, Viaje o Deducible.</p>
            </div>
            <form class="inline-form" action="{{ route('tags.store', $project) }}" method="post">
                @csrf
                <div class="field inline-form__field"><label class="field__label" for="tag-name">Nombre</label><input class="field__control" id="tag-name" name="name" value="{{ old('name') }}" maxlength="60" required></div>
                <button class="button button--primary inline-form__button" type="submit">Crear etiqueta</button>
            </form>
        </section>
    @endif

    <section class="content-section" aria-labelledby="tag-list-title">
        <div class="section-heading"><div><p class="eyebrow">{{ $tags->whereNull('archived_at')->count() }} activas</p><h2 class="section-heading__title" id="tag-list-title">Etiquetas del proyecto</h2></div></div>
        @if ($tags->isEmpty())
            <div class="empty-state"><span class="empty-state__icon" aria-hidden="true">#</span><h3 class="empty-state__title">Todavía no hay etiquetas</h3><p class="empty-state__text">Crea la primera cuando necesites agrupar movimientos más allá de sus categorías.</p></div>
        @else
            <div class="tag-list">
                @foreach ($tags as $tag)
                    <article class="tag-card {{ $tag->archived_at ? 'tag-card--archived' : '' }}">
                        <div class="tag-card__summary">
                            <span class="tag-chip"># {{ $tag->name }}</span>
                            @if ($tag->archived_at)<span class="badge badge--muted">{{ $tag->mergedInto ? 'Fusionada' : 'Archivada' }}</span>@endif
                            <p>{{ $tag->movements_count }} {{ $tag->movements_count === 1 ? 'movimiento' : 'movimientos' }} · {{ $tag->recurrence_templates_count }} {{ $tag->recurrence_templates_count === 1 ? 'serie' : 'series' }}</p>
                            @if ($tag->mergedInto)<small>Ahora se agrupa dentro de # {{ $tag->mergedInto->name }}.</small>@endif
                        </div>
                        @if ($canManage)
                            <div class="tag-card__actions">
                                @if ($tag->archived_at)
                                    @unless ($tag->mergedInto)
                                        <form action="{{ route('tags.restore', [$project, $tag]) }}" method="post">@csrf<button class="button button--secondary button--small" type="submit">Reactivar</button></form>
                                    @endunless
                                @else
                                    <form class="tag-card__rename" action="{{ route('tags.update', [$project, $tag]) }}" method="post">@csrf @method('patch')<label class="sr-only" for="tag-name-{{ $tag->id }}">Nuevo nombre de {{ $tag->name }}</label><input class="field__control" id="tag-name-{{ $tag->id }}" name="name" value="{{ $tag->name }}" maxlength="60" required><button class="button button--secondary button--small" type="submit">Renombrar</button></form>
                                    @if ($activeTags->count() > 1)
                                        <form class="tag-card__merge" action="{{ route('tags.merge', [$project, $tag]) }}" method="post" onsubmit="return confirm('¿Fusionar esta etiqueta? Todos sus movimientos y series pasarán a la etiqueta elegida.')">@csrf<label class="sr-only" for="tag-target-{{ $tag->id }}">Fusionar {{ $tag->name }} con</label><select class="field__control" id="tag-target-{{ $tag->id }}" name="target_tag_id" required><option value="">Fusionar con…</option>@foreach ($activeTags->where('id', '!=', $tag->id) as $target)<option value="{{ $target->id }}"># {{ $target->name }}</option>@endforeach</select><button class="button button--secondary button--small" type="submit">Fusionar</button></form>
                                    @endif
                                    <form action="{{ route('tags.archive', [$project, $tag]) }}" method="post" onsubmit="return confirm('¿Archivar esta etiqueta? Se conservará en los movimientos existentes.')">@csrf<button class="button button--danger-quiet button--small" type="submit">Archivar</button></form>
                                @endif
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
