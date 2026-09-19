@php($isUnavailable = $category->isArchived() || ($ancestorArchived ?? false))

<article class="category-item {{ $isUnavailable ? 'category-item--archived' : '' }}">
    <div class="category-item__row">
        <div class="category-item__identity">
            <span
                class="category-item__icon"
                style="--category-color: {{ $category->color }}"
                aria-hidden="true"
            >{{ $category->iconSymbol() }}</span>
            <div class="category-item__copy">
                <div class="category-item__name-row">
                    <h3 class="category-item__name">{{ $category->name }}</h3>
                    @if ($category->is_initial)
                        <span class="badge badge--muted">Inicial</span>
                    @endif
                    @if ($category->isArchived())
                        <span class="badge badge--archived">Archivada</span>
                    @elseif ($ancestorArchived ?? false)
                        <span class="badge badge--archived">Principal archivada</span>
                    @endif
                </div>
                <p class="category-item__meta">
                    {{ $category->isMain() ? 'Categoría principal' : 'Subcategoría' }}
                    @if ($category->isMain())
                        · {{ $category->children->whereNull('archived_at')->count() }} activas
                    @endif
                </p>
            </div>
        </div>

        @if ($canManage && ! ($ancestorArchived ?? false))
            <div class="category-item__actions">
                @if (! $category->isArchived())
                    <div class="order-actions" aria-label="Cambiar posición de {{ $category->name }}">
                        <form action="{{ route('categories.move', [$project, $category]) }}" method="post">
                            @csrf
                            <input name="direction" type="hidden" value="up">
                            <button class="button button--icon" type="submit" title="Subir">↑</button>
                        </form>
                        <form action="{{ route('categories.move', [$project, $category]) }}" method="post">
                            @csrf
                            <input name="direction" type="hidden" value="down">
                            <button class="button button--icon" type="submit" title="Bajar">↓</button>
                        </form>
                    </div>

                    <a
                        class="button button--secondary button--small"
                        href="{{ route('categories.edit', [$project, $category]) }}"
                    >Editar</a>

                    <form
                        action="{{ route('categories.archive', [$project, $category]) }}"
                        method="post"
                        data-confirm="La categoría dejará de estar disponible para nuevos movimientos, pero conservará su historial."
                    >
                        @csrf
                        <button class="button button--quiet button--small" type="submit">Archivar</button>
                    </form>
                @else
                    <form action="{{ route('categories.restore', [$project, $category]) }}" method="post">
                        @csrf
                        <button class="button button--secondary button--small" type="submit">Reactivar</button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    @if ($category->children->isNotEmpty())
        <div class="category-item__children">
            @foreach ($category->children as $child)
                @include('categories._item', [
                    'category' => $child,
                    'project' => $project,
                    'canManage' => $canManage,
                    'iconOptions' => $iconOptions,
                    'ancestorArchived' => $isUnavailable,
                ])
            @endforeach
        </div>
    @endif
</article>
