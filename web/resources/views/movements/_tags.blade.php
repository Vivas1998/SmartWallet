@php($selectedTags = collect(old('tag_ids', $selectedTagIds ?? []))->map(fn ($id) => (int) $id))
<fieldset class="field field--wide tag-picker">
    <legend class="field__label">Etiquetas <span class="field__optional">opcional</span></legend>
    @if ($tags->isEmpty())
        <p class="tag-picker__empty">Todavía no hay etiquetas disponibles. <a class="text-link" href="{{ route('tags.index', $project) }}">Gestionar etiquetas</a></p>
    @else
        <div class="tag-picker__options">
            @foreach ($tags as $tag)
                <label class="tag-choice {{ $tag->archived_at ? 'tag-choice--archived' : '' }}">
                    <input name="tag_ids[]" type="checkbox" value="{{ $tag->id }}" @checked($selectedTags->contains($tag->id))>
                    <span># {{ $tag->name }}{{ $tag->archived_at ? ' · archivada' : '' }}</span>
                </label>
            @endforeach
        </div>
        <small class="field__hint">Puedes seleccionar varias. <a class="text-link" href="{{ route('tags.index', $project) }}">Gestionar etiquetas</a></small>
    @endif
</fieldset>
