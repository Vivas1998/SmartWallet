<div class="project-context">
    <div class="project-context__identity">
        <span
            class="project-context__icon"
            style="--project-color: {{ $project->color }}"
            aria-hidden="true"
        >{{ $project->iconSymbol() }}</span>
        <div class="project-context__field">
            <label class="project-context__label" for="project-switcher">Proyecto actual</label>
            <select class="project-context__select" id="project-switcher" data-project-switcher>
                @foreach ($projectSwitcherItems as $switcherProject)
                    <option
                        value="{{ route('projects.show', $switcherProject) }}"
                        @selected($switcherProject->is($project))
                    >{{ $switcherProject->iconSymbol() }} {{ $switcherProject->name }}{{ $switcherProject->isArchived() ? ' (archivado)' : '' }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <a class="project-context__all" href="{{ route('dashboard') }}">Ver mis proyectos</a>
</div>

<nav class="project-nav" aria-label="Secciones del proyecto">
    <a
        class="project-nav__link {{ request()->routeIs('projects.show') ? 'project-nav__link--active' : '' }}"
        href="{{ route('projects.show', $project) }}"
        @if(request()->routeIs('projects.show')) aria-current="page" @endif
    >Resumen</a>
    <a
        class="project-nav__link {{ request()->routeIs('movements.*') || request()->routeIs('planned-movements.*') ? 'project-nav__link--active' : '' }}"
        href="{{ route('movements.index', $project) }}"
        @if(request()->routeIs('movements.*') || request()->routeIs('planned-movements.*')) aria-current="page" @endif
    >Movimientos</a>
    <a
        class="project-nav__link {{ request()->routeIs('calendar.*') ? 'project-nav__link--active' : '' }}"
        href="{{ route('calendar.index', $project) }}"
        @if(request()->routeIs('calendar.*')) aria-current="page" @endif
    >Calendario</a>
    <a
        class="project-nav__link {{ request()->routeIs('budgets.*') ? 'project-nav__link--active' : '' }}"
        href="{{ route('budgets.index', $project) }}"
        @if(request()->routeIs('budgets.*')) aria-current="page" @endif
    >Presupuesto</a>
    <a
        class="project-nav__link {{ request()->routeIs('reports.*') ? 'project-nav__link--active' : '' }}"
        href="{{ route('reports.monthly', $project) }}"
        @if(request()->routeIs('reports.*')) aria-current="page" @endif
    >Informes</a>
    <a
        class="project-nav__link {{ request()->routeIs('financial-accounts.*') ? 'project-nav__link--active' : '' }}"
        href="{{ route('financial-accounts.index', $project) }}"
        @if(request()->routeIs('financial-accounts.*')) aria-current="page" @endif
    >Cuentas</a>
    <a
        class="project-nav__link {{ request()->routeIs('savings-goals.*') ? 'project-nav__link--active' : '' }}"
        href="{{ route('savings-goals.index', $project) }}"
        @if(request()->routeIs('savings-goals.*')) aria-current="page" @endif
    >Objetivos</a>
    <a
        class="project-nav__link {{ request()->routeIs('recurrences.*') ? 'project-nav__link--active' : '' }}"
        href="{{ route('recurrences.index', $project) }}"
        @if(request()->routeIs('recurrences.*')) aria-current="page" @endif
    >Recurrentes</a>
    <a
        class="project-nav__link {{ request()->routeIs('categories.*') ? 'project-nav__link--active' : '' }}"
        href="{{ route('categories.index', $project) }}"
        @if(request()->routeIs('categories.*')) aria-current="page" @endif
    >Categorías</a>
    <a
        class="project-nav__link {{ request()->routeIs('tags.*') ? 'project-nav__link--active' : '' }}"
        href="{{ route('tags.index', $project) }}"
        @if(request()->routeIs('tags.*')) aria-current="page" @endif
    >Etiquetas</a>
    <a
        class="project-nav__link {{ request()->routeIs('project-members.*') ? 'project-nav__link--active' : '' }}"
        href="{{ route('project-members.index', $project) }}"
        @if(request()->routeIs('project-members.*')) aria-current="page" @endif
    >Miembros</a>
    <a
        class="project-nav__link {{ request()->routeIs('audit-logs.*') || request()->routeIs('movements.trash') ? 'project-nav__link--active' : '' }}"
        href="{{ route('audit-logs.index', $project) }}"
        @if(request()->routeIs('audit-logs.*') || request()->routeIs('movements.trash')) aria-current="page" @endif
    >Historial</a>
    <a
        class="project-nav__link {{ request()->routeIs('projects.settings') || request()->routeIs('projects.update') || request()->routeIs('projects.archive') || request()->routeIs('projects.restore') ? 'project-nav__link--active' : '' }}"
        href="{{ route('projects.settings', $project) }}"
        @if(request()->routeIs('projects.settings') || request()->routeIs('projects.update') || request()->routeIs('projects.archive') || request()->routeIs('projects.restore')) aria-current="page" @endif
    >Configuración</a>
</nav>
