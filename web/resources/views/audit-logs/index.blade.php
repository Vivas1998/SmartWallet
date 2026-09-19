@extends('layouts.app')

@section('title', 'Auditoría de '.$project->name)

@section('content')
    @php
        $typeLabels = collect($types)->mapWithKeys(fn ($type) => [$type => (new \App\Models\AuditLog(['subject_type' => $type]))->subjectLabel()]);
        $actionLabels = collect($actions)->mapWithKeys(fn ($action) => [$action => (new \App\Models\AuditLog(['action' => $action]))->actionLabel()]);
    @endphp
    <header class="page-heading"><div class="page-heading__copy"><a class="back-link" href="{{ route('projects.show', $project) }}">← {{ $project->name }}</a><p class="eyebrow">Historial protegido</p><h1 class="page-heading__title">Auditoría</h1><p class="page-heading__intro">Consulta quién cambió cada dato. Este historial no se puede editar ni borrar desde la aplicación.</p></div></header>
    @include('projects._navigation', ['project' => $project])

    <form class="filter-panel" method="get" action="{{ route('audit-logs.index', $project) }}"><div class="filter-panel__grid">
        <div class="field"><label class="field__label" for="audit-member">Persona</label><select class="field__control" id="audit-member" name="member"><option value="">Todas</option>@foreach ($members as $member)<option value="{{ $member->id }}" @selected((string) request('member') === (string) $member->id)>{{ $member->name }}</option>@endforeach</select></div>
        <div class="field"><label class="field__label" for="audit-action">Acción</label><select class="field__control" id="audit-action" name="action"><option value="">Todas</option>@foreach ($actions as $action)<option value="{{ $action }}" @selected(request('action') === $action)>{{ $actionLabels[$action] }}</option>@endforeach</select></div>
        <div class="field"><label class="field__label" for="audit-type">Elemento</label><select class="field__control" id="audit-type" name="type"><option value="">Todos</option>@foreach ($types as $type)<option value="{{ $type }}" @selected(request('type') === $type)>{{ $typeLabels[$type] }}</option>@endforeach</select></div>
        <div class="field"><label class="field__label" for="audit-from">Desde</label><input class="field__control" id="audit-from" name="from" type="date" value="{{ request('from') }}"></div>
        <div class="field"><label class="field__label" for="audit-to">Hasta</label><input class="field__control" id="audit-to" name="to" type="date" value="{{ request('to') }}"></div>
        <button class="button button--secondary filter-panel__button" type="submit">Aplicar filtros</button>
    </div></form>

    @if ($logs->isEmpty())
        <section class="empty-state"><span class="empty-state__icon" aria-hidden="true">◎</span><h2 class="empty-state__title">Todavía no hay cambios registrados</h2><p class="empty-state__text">Las nuevas operaciones sensibles aparecerán aquí con su autor y sus valores.</p></section>
    @else
        <div class="audit-list">@foreach ($logs as $log)<article class="audit-entry"><span class="audit-entry__icon" aria-hidden="true">{{ $log->subject_type === 'movement' ? '↕' : ($log->subject_type === 'account' ? '◫' : '◎') }}</span><div class="audit-entry__copy"><div class="audit-entry__title"><strong>{{ $log->actionLabel() }}</strong><span>{{ $log->subjectLabel() }} #{{ $log->subject_id }}</span></div><p>{{ $log->actor?->name ?? 'Sistema' }} · {{ $log->created_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }}</p><details><summary>Ver valores registrados</summary><div class="audit-entry__values">@if ($log->before_values)<div><small>Antes</small><pre>{{ json_encode($log->before_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div>@endif @if ($log->after_values)<div><small>Después</small><pre>{{ json_encode($log->after_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div>@endif</div></details></div></article>@endforeach</div>
        {{ $logs->links() }}
    @endif
@endsection
