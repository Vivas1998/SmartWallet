@extends('layouts.app')

@section('title', 'Configuración de '.$project->name)

@section('content')
    <header class="page-heading page-heading--compact">
        <div class="page-heading__copy">
            <a class="back-link" href="{{ route('projects.show', $project) }}">← {{ $project->name }}</a>
            <p class="eyebrow">Datos y ciclo de vida</p>
            <h1 class="page-heading__title">Configuración del proyecto</h1>
            <p class="page-heading__intro">Consulta la identidad, los datos regionales y el estado de este espacio compartido.</p>
        </div>
    </header>

    @include('projects._navigation', ['project' => $project])

    @if ($project->isArchived())
        <div class="alert alert--warning" role="status">
            <strong>Proyecto archivado.</strong> Se pueden consultar sus datos e informes, pero no modificarlos ni añadir operaciones.
        </div>
    @elseif (! $canUpdate)
        <div class="alert" role="status">
            Puedes consultar esta configuración. Solo los propietarios pueden modificarla o archivar el proyecto.
        </div>
    @endif

    <div class="settings-layout">
        <div class="settings-layout__main">
            <section class="settings-card" aria-labelledby="project-identity-title">
                <div class="settings-card__heading">
                    <div>
                        <p class="eyebrow">Identidad</p>
                        <h2 id="project-identity-title">Datos visibles del proyecto</h2>
                    </div>
                    <span class="settings-card__project-icon" style="--project-color: {{ $project->color }}" aria-hidden="true">{{ $project->iconSymbol() }}</span>
                </div>

                <form class="settings-form" action="{{ route('projects.update', $project) }}" method="post">
                    @csrf
                    @method('patch')

                    <div class="form-grid">
                        <div class="field field--wide">
                            <label class="field__label" for="project-name">Nombre</label>
                            <input class="field__control @error('name') field__control--invalid @enderror" id="project-name" name="name" type="text" value="{{ old('name', $project->name) }}" maxlength="120" required @disabled(! $canUpdate)>
                            @error('name')<p class="field__error">{{ $message }}</p>@enderror
                        </div>

                        <div class="field field--wide">
                            <label class="field__label" for="project-description">Descripción <span class="field__optional">(opcional)</span></label>
                            <textarea class="field__control field__control--textarea @error('description') field__control--invalid @enderror" id="project-description" name="description" maxlength="2000" @disabled(! $canUpdate)>{{ old('description', $project->description) }}</textarea>
                            @error('description')<p class="field__error">{{ $message }}</p>@enderror
                        </div>

                        <div class="field">
                            <label class="field__label" for="project-color">Color</label>
                            <input class="field__control field__control--color" id="project-color" name="color" type="color" value="{{ old('color', $project->color) }}" required @disabled(! $canUpdate)>
                        </div>

                        <div class="field">
                            <label class="field__label" for="project-icon">Icono</label>
                            <select class="field__control" id="project-icon" name="icon" required @disabled(! $canUpdate)>
                                <option value="home" @selected(old('icon', $project->icon) === 'home')>Casa</option>
                                <option value="wallet" @selected(old('icon', $project->icon) === 'wallet')>Cartera</option>
                                <option value="personal" @selected(old('icon', $project->icon) === 'personal')>Personal</option>
                                <option value="travel" @selected(old('icon', $project->icon) === 'travel')>Viajes</option>
                                <option value="heart" @selected(old('icon', $project->icon) === 'heart')>Familia</option>
                            </select>
                        </div>
                    </div>

                    @if ($canUpdate)
                        <div class="settings-form__actions">
                            <button class="button button--primary" type="submit">Guardar cambios</button>
                        </div>
                    @endif
                </form>
            </section>

            <section class="settings-card" aria-labelledby="regional-title">
                <div class="settings-card__heading">
                    <div>
                        <p class="eyebrow">Formato común</p>
                        <h2 id="regional-title">Configuración regional</h2>
                    </div>
                    <span class="badge">Fija en 1.0</span>
                </div>
                <dl class="settings-definition-list">
                    <div><dt>Idioma y formato</dt><dd>Español · {{ $project->locale }}</dd></div>
                    <div><dt>Moneda</dt><dd>Euro · {{ $project->currency }}</dd></div>
                    <div><dt>Zona horaria</dt><dd>{{ $project->timezone }}</dd></div>
                </dl>
                <p class="settings-card__note">Todos los importes del proyecto usan una única moneda. La edición regional y las distintas monedas quedan reservadas para una versión futura.</p>
            </section>

            <section class="settings-card" aria-labelledby="data-title">
                <div class="settings-card__heading">
                    <div>
                        <p class="eyebrow">Trazabilidad</p>
                        <h2 id="data-title">Datos e historial</h2>
                    </div>
                </div>
                <div class="settings-links">
                    <a class="settings-link" href="{{ route('audit-logs.index', $project) }}">
                        <span aria-hidden="true">◎</span>
                        <span><strong>Auditoría</strong><small>{{ $project->audit_logs_count }} cambios registrados</small></span>
                        <span aria-hidden="true">→</span>
                    </a>
                    <a class="settings-link" href="{{ route('movements.trash', $project) }}">
                        <span aria-hidden="true">♲</span>
                        <span><strong>Papelera</strong><small>{{ $trashCount }} movimientos pendientes de eliminación</small></span>
                        <span aria-hidden="true">→</span>
                    </a>
                </div>
            </section>

            <section class="settings-card settings-card--danger" aria-labelledby="lifecycle-title">
                <div class="settings-card__heading">
                    <div>
                        <p class="eyebrow">Acceso y conservación</p>
                        <h2 id="lifecycle-title">Ciclo de vida</h2>
                    </div>
                    <span class="badge {{ $project->isArchived() ? 'badge--warning' : 'badge--success' }}">{{ $project->isArchived() ? 'Archivado' : 'Activo' }}</span>
                </div>

                @if ($project->isArchived())
                    <p>Archivado el {{ $project->archived_at->timezone('Europe/Madrid')->format('d/m/Y \a \l\a\s H:i') }}@if($project->archivedBy) por {{ $project->archivedBy->name }}@endif. No se ha eliminado ningún dato.</p>
                    <p class="settings-card__note">Al reactivarlo volverán las acciones de edición. Las apariciones recurrentes vencidas durante el archivado se generarán en la siguiente comprobación y se mostrarán en un aviso.</p>
                    @if ($canRestore)
                        <form action="{{ route('projects.restore', $project) }}" method="post" data-confirm="¿Reactivar este proyecto? Volverá a admitir movimientos y cambios.">
                            @csrf
                            <button class="button button--primary" type="submit">Reactivar proyecto</button>
                        </form>
                    @endif
                @else
                    <p>Archivar conserva movimientos, cuentas, presupuestos e informes, pero bloquea cualquier cambio para todos sus miembros.</p>
                    <p class="settings-card__note">La generación de movimientos recurrentes quedará suspendida mientras esté archivado. SmartWallet no permite eliminar proyectos definitivamente en la versión 1.0.</p>
                    @if ($canArchive)
                        <form action="{{ route('projects.archive', $project) }}" method="post" data-confirm="¿Archivar este proyecto? Quedará en modo de solo lectura para todos los miembros.">
                            @csrf
                            <button class="button button--danger-quiet" type="submit">Archivar proyecto</button>
                        </form>
                    @endif
                @endif
            </section>
        </div>

        <aside class="settings-layout__aside" aria-label="Resumen del proyecto">
            <section class="settings-summary">
                <p class="eyebrow">Resumen</p>
                <h2>{{ $project->name }}</h2>
                <dl>
                    <div><dt>Creado por</dt><dd>{{ $project->creator->name }}</dd></div>
                    <div><dt>Fecha de creación</dt><dd>{{ $project->created_at->timezone('Europe/Madrid')->format('d/m/Y') }}</dd></div>
                    <div><dt>Miembros activos</dt><dd>{{ $project->active_members_count }}</dd></div>
                    <div><dt>Cuentas</dt><dd>{{ $project->financial_accounts_count }}</dd></div>
                    <div><dt>Movimientos activos</dt><dd>{{ $activeMovementCount }}</dd></div>
                    <div><dt>Meses con presupuesto</dt><dd>{{ $project->monthly_budgets_count }}</dd></div>
                </dl>
            </section>
        </aside>
    </div>
@endsection
