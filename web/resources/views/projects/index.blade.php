@extends('layouts.app')

@section('title', 'Mis proyectos')

@section('content')
    <header class="page-heading">
        <div class="page-heading__copy">
            <p class="eyebrow">Espacios financieros independientes</p>
            <h1 class="page-heading__title">Mis proyectos</h1>
            <p class="page-heading__intro">Cada proyecto conserva sus cuentas y datos por separado.</p>
        </div>
        <a class="button button--primary" href="{{ route('projects.create') }}">Crear proyecto</a>
    </header>

    @if ($projects->isEmpty())
        <section class="empty-state" aria-labelledby="empty-title">
            <span class="empty-state__icon" aria-hidden="true">⌂</span>
            <h2 class="empty-state__title" id="empty-title">Crea tu primer proyecto</h2>
            <p class="empty-state__text">
                Puedes empezar con la economía familiar, tus finanzas personales o cualquier otro ámbito.
            </p>
            <a class="button button--primary" href="{{ route('projects.create') }}">Empezar</a>
        </section>
    @else
        <div class="project-grid">
            @foreach ($projects as $project)
                <article class="project-card">
                    <a class="project-card__link" href="{{ route('projects.show', $project) }}">
                        <span
                            class="project-card__icon"
                            style="--project-color: {{ $project->color }}"
                            aria-hidden="true"
                        >{{ $project->iconSymbol() }}</span>

                        <span class="project-card__content">
                            <span class="project-card__heading">
                                <span class="project-card__name">{{ $project->name }}</span>
                                @if ($project->isArchived())
                                    <span class="badge badge--muted">Archivado</span>
                                @endif
                            </span>
                            <span class="project-card__description">
                                {{ $project->description ?: 'Sin descripción' }}
                            </span>
                            <span class="project-card__meta">
                                {{ $project->active_members_count }}
                                {{ $project->active_members_count === 1 ? 'miembro' : 'miembros' }}
                                ·
                                {{ $project->pivot->role === 'owner' ? 'Propietario' : 'Miembro' }}
                            </span>
                            <span class="project-card__activity">
                                @if ($project->latestAuditLog)
                                    Última actividad {{ $project->latestAuditLog->created_at->diffForHumans() }}
                                @else
                                    Creado {{ $project->created_at->diffForHumans() }}
                                @endif
                            </span>
                        </span>

                        <span class="project-card__arrow" aria-hidden="true">→</span>
                    </a>
                </article>
            @endforeach
        </div>

        <section class="dashboard-section" aria-labelledby="recent-activity-title">
            <div class="dashboard-section__heading">
                <div>
                    <p class="eyebrow">Todos tus espacios</p>
                    <h2 class="dashboard-section__title" id="recent-activity-title">Actividad reciente</h2>
                </div>
                <span class="dashboard-section__hint">No mezcla importes entre proyectos</span>
            </div>

            @if ($recentActivities->isEmpty())
                <div class="empty-state empty-state--compact">
                    <p class="empty-state__text">Todavía no hay actividad registrada en tus proyectos.</p>
                </div>
            @else
                <div class="activity-feed">
                    @foreach ($recentActivities as $activity)
                        <a class="activity-item" href="{{ route('audit-logs.index', $activity->project) }}">
                            <span
                                class="activity-item__project-icon"
                                style="--project-color: {{ $activity->project->color }}"
                                aria-hidden="true"
                            >{{ $activity->project->iconSymbol() }}</span>
                            <span class="activity-item__copy">
                                <span class="activity-item__description">
                                    <strong>{{ $activity->actor?->name ?? 'Sistema' }}</strong>
                                    {{ $activity->activityDescription() }}
                                </span>
                                <span class="activity-item__meta">
                                    {{ $activity->project->name }}
                                    @if ($activity->project->isArchived())
                                        · Archivado
                                    @endif
                                </span>
                            </span>
                            <time
                                class="activity-item__date"
                                datetime="{{ $activity->created_at->toIso8601String() }}"
                                title="{{ $activity->created_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }}"
                            >{{ $activity->created_at->diffForHumans() }}</time>
                            <span class="activity-item__arrow" aria-hidden="true">→</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    @endif
@endsection
