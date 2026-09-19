@extends('layouts.app')

@section('title', 'Miembros de '.$project->name)

@section('content')
    <header class="page-heading page-heading--compact">
        <div class="page-heading__copy">
            <a class="back-link" href="{{ route('projects.show', $project) }}">← {{ $project->name }}</a>
            <p class="eyebrow">Personas con acceso</p>
            <h1 class="page-heading__title">Miembros</h1>
            <p class="page-heading__intro">
                Todos pueden consultar y modificar la información financiera del proyecto.
            </p>
        </div>
    </header>

    @include('projects._navigation', ['project' => $project])

    @if ($project->isArchived())
        <div class="alert alert--warning" role="status">
            El proyecto está archivado. Los miembros se conservan, pero no pueden modificarse.
        </div>
    @endif

    @if ($canManage)
        <section class="management-panel" aria-labelledby="add-member-title">
            <div class="management-panel__heading">
                <div>
                    <p class="eyebrow">Cuenta existente</p>
                    <h2 class="management-panel__title" id="add-member-title">Añadir miembro</h2>
                </div>
                <p class="management-panel__intro">
                    Introduce el correo de una persona que ya tenga cuenta en SmartWallet.
                </p>
            </div>

            <form class="inline-form" action="{{ route('project-members.store', $project) }}" method="post">
                @csrf
                <div class="field inline-form__field">
                    <label class="field__label" for="member-email">Correo electrónico</label>
                    <input
                        class="field__control {{ $errors->has('email') ? 'field__control--invalid' : '' }}"
                        id="member-email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        autocomplete="off"
                        required
                    >
                </div>
                <button class="button button--primary inline-form__button" type="submit">Añadir al proyecto</button>
            </form>
        </section>
    @endif

    <section class="content-section" aria-labelledby="member-list-title">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Acceso actual</p>
                <h2 class="section-heading__title" id="member-list-title">
                    {{ $memberships->count() }} {{ $memberships->count() === 1 ? 'persona' : 'personas' }}
                </h2>
            </div>
        </div>

        <div class="member-list">
            @foreach ($memberships as $membership)
                @php($isCreator = $membership->user_id === $project->creator_user_id)
                <article class="member-card {{ $isCreator ? 'member-card--creator' : '' }}">
                    <div class="member-card__identity">
                        <span class="avatar" aria-hidden="true">{{ $membership->user->initials() }}</span>
                        <div class="member-card__copy">
                            <div class="member-card__name-row">
                                <h3 class="member-card__name">{{ $membership->user->name }}</h3>
                                @if ($membership->user_id === auth()->id())
                                    <span class="badge badge--muted">Tú</span>
                                @endif
                                @if ($isCreator)
                                    <span class="badge badge--protected">Creador protegido</span>
                                @endif
                            </div>
                            <p class="member-card__email">{{ $membership->user->email }}</p>
                            <p class="member-card__joined">
                                Se incorporó el {{ $membership->joined_at->format('d/m/Y') }}
                            </p>
                        </div>
                    </div>

                    <div class="member-card__access">
                        <span class="badge {{ $membership->role->value === 'owner' ? 'badge--owner' : 'badge--member' }}">
                            {{ $membership->role->label() }}
                        </span>

                        @if ($canManage && ! $isCreator)
                            <div class="member-card__actions">
                                <form action="{{ route('project-members.update', [$project, $membership]) }}" method="post">
                                    @csrf
                                    @method('patch')
                                    <input
                                        name="role"
                                        type="hidden"
                                        value="{{ $membership->role->value === 'owner' ? 'member' : 'owner' }}"
                                    >
                                    <button class="button button--secondary button--small" type="submit">
                                        {{ $membership->role->value === 'owner' ? 'Cambiar a miembro' : 'Hacer propietario' }}
                                    </button>
                                </form>
                                <form
                                    action="{{ route('project-members.destroy', [$project, $membership]) }}"
                                    method="post"
                                    data-confirm="Se retirará el acceso inmediatamente, pero se conservará su historial."
                                >
                                    @csrf
                                    @method('delete')
                                    <button class="button button--danger button--small" type="submit">
                                        {{ $membership->user_id === auth()->id() ? 'Salir del proyecto' : 'Retirar acceso' }}
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endsection
