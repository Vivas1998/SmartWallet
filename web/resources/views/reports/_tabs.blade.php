@php($sharedReportFilters = array_filter($filters, fn ($value) => $value !== null))
<nav class="report-tabs" aria-label="Tipos de informe">
    <a class="report-tabs__link {{ request()->routeIs('reports.monthly') ? 'report-tabs__link--active' : '' }}" href="{{ route('reports.monthly', ['project' => $project, ...$sharedReportFilters]) }}" @if(request()->routeIs('reports.monthly')) aria-current="page" @endif>Mensual</a>
    <a class="report-tabs__link {{ request()->routeIs('reports.annual') ? 'report-tabs__link--active' : '' }}" href="{{ route('reports.annual', ['project' => $project, ...$sharedReportFilters]) }}" @if(request()->routeIs('reports.annual')) aria-current="page" @endif>Anual</a>
    <a class="report-tabs__link {{ request()->routeIs('reports.compare') ? 'report-tabs__link--active' : '' }}" href="{{ route('reports.compare', ['project' => $project, ...$sharedReportFilters]) }}" @if(request()->routeIs('reports.compare')) aria-current="page" @endif>Comparar</a>
</nav>
