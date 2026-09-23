@extends('layouts.app')

@section('title', 'Mi perfil')

@section('content')
    <header class="page-heading page-heading--compact">
        <div class="page-heading__copy">
            <a class="back-link" href="{{ route('dashboard') }}">← Mis proyectos</a>
            <p class="eyebrow">Cuenta y seguridad</p>
            <h1 class="page-heading__title">Mi perfil</h1>
            <p class="page-heading__intro">Actualiza tus datos y revisa dónde permanece abierta tu cuenta.</p>
        </div>
    </header>

    <div class="profile-layout">
        <aside class="profile-layout__aside" aria-label="Resumen de la cuenta">
            <section class="profile-summary">
                <span class="profile-summary__avatar" aria-hidden="true">{{ $user->initials() }}</span>
                <h2>{{ $user->name }}</h2>
                <p>{{ $user->email }}</p>
                <dl>
                    <div><dt>Cuenta creada</dt><dd>{{ $user->created_at->timezone('Europe/Madrid')->format('d/m/Y') }}</dd></div>
                    <div><dt>Último acceso</dt><dd>{{ $user->last_login_at?->timezone('Europe/Madrid')->format('d/m/Y H:i') ?? 'No disponible' }}</dd></div>
                </dl>
                <p class="profile-summary__note">La versión actual utiliza tus iniciales y no almacena fotografías de perfil.</p>
            </section>
        </aside>

        <div class="profile-layout__main">
            <section class="profile-card" aria-labelledby="profile-name-title">
                <div class="profile-card__heading"><div><p class="eyebrow">Identidad</p><h2 id="profile-name-title">Nombre visible</h2></div></div>
                <p class="profile-card__intro">Es el nombre que verán los demás miembros en movimientos, proyectos y auditorías.</p>
                <form class="profile-form" action="{{ route('profile.name.update') }}" method="post">
                    @csrf
                    @method('patch')
                    <div class="field">
                        <label class="field__label" for="profile-name">Nombre</label>
                        <input class="field__control @error('name') field__control--invalid @enderror" id="profile-name" name="name" type="text" value="{{ old('name', $user->name) }}" maxlength="150" autocomplete="name" required>
                        @error('name')<p class="field__error">{{ $message }}</p>@enderror
                    </div>
                    <div class="profile-form__actions"><button class="button button--primary" type="submit">Guardar nombre</button></div>
                </form>
            </section>

            <section class="profile-card" aria-labelledby="profile-email-title">
                <div class="profile-card__heading"><div><p class="eyebrow">Acceso</p><h2 id="profile-email-title">Correo electrónico</h2></div><span class="badge">Sin verificación en 1.0</span></div>
                <p class="profile-card__intro">El nuevo correo será tu identificador de acceso y no podrá pertenecer a otra cuenta.</p>
                <form class="profile-form" action="{{ route('profile.email.update') }}" method="post">
                    @csrf
                    @method('patch')
                    <div class="profile-form__grid">
                        <div class="field">
                            <label class="field__label" for="profile-email">Nuevo correo</label>
                            <input class="field__control @error('email') field__control--invalid @enderror" id="profile-email" name="email" type="email" value="{{ old('email', $user->email) }}" maxlength="255" autocomplete="email" required>
                            @error('email')<p class="field__error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label class="field__label" for="email-current-password">Contraseña actual</label>
                            <input class="field__control @error('email_current_password') field__control--invalid @enderror" id="email-current-password" name="email_current_password" type="password" autocomplete="current-password" required>
                            @error('email_current_password')<p class="field__error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="profile-form__actions"><button class="button button--primary" type="submit">Cambiar correo</button></div>
                </form>
            </section>

            <section class="profile-card" aria-labelledby="profile-password-title">
                <div class="profile-card__heading"><div><p class="eyebrow">Seguridad</p><h2 id="profile-password-title">Cambiar contraseña</h2></div></div>
                <p class="profile-card__intro">Utiliza al menos 12 caracteres. Los enlaces de recuperación pendientes dejarán de ser válidos.</p>
                <form class="profile-form" action="{{ route('profile.password.update') }}" method="post">
                    @csrf
                    @method('put')
                    <div class="profile-form__grid">
                        <div class="field">
                            <label class="field__label" for="password-current-password">Contraseña actual</label>
                            <input class="field__control @error('password_current_password') field__control--invalid @enderror" id="password-current-password" name="password_current_password" type="password" autocomplete="current-password" required>
                            @error('password_current_password')<p class="field__error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label class="field__label" for="profile-password">Nueva contraseña</label>
                            <input class="field__control @error('password') field__control--invalid @enderror" id="profile-password" name="password" type="password" minlength="12" autocomplete="new-password" required>
                            @error('password')<p class="field__error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label class="field__label" for="profile-password-confirmation">Repetir nueva contraseña</label>
                            <input class="field__control" id="profile-password-confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required>
                        </div>
                    </div>
                    <label class="check profile-form__check"><input class="check__control" name="logout_other_sessions" type="checkbox" value="1" @checked(old('logout_other_sessions', true))><span class="check__label"><strong>Cerrar las demás sesiones</strong><small>Recomendado si otra persona pudiera conocer tu contraseña anterior.</small></span></label>
                    <div class="profile-form__actions"><button class="button button--primary" type="submit">Actualizar contraseña</button></div>
                </form>
            </section>

            <section class="profile-card" aria-labelledby="profile-sessions-title">
                <div class="profile-card__heading">
                    <div><p class="eyebrow">Dispositivos</p><h2 id="profile-sessions-title">Sesiones recientes</h2></div>
                    @if ($otherSessionCount > 0)
                        <form action="{{ route('profile.sessions.destroy-others') }}" method="post" data-confirm="¿Cerrar todas las demás sesiones? Tendrán que volver a iniciar sesión.">@csrf @method('delete')<button class="button button--danger-quiet button--small" type="submit">Cerrar las demás</button></form>
                    @endif
                </div>
                <p class="profile-card__intro">Una sesión normal caduca tras dos horas sin actividad. Si activaste «Recordarme», el acceso puede mantenerse durante 30 días.</p>
                @error('session')<div class="alert alert--error" role="alert">{{ $message }}</div>@enderror
                <div class="session-list">
                    @foreach ($sessions as $session)
                        <article class="session-card {{ $session['is_current'] ? 'session-card--current' : '' }}">
                            <span class="session-card__icon" aria-hidden="true">{{ $session['is_current'] ? '●' : '◫' }}</span>
                            <div class="session-card__copy">
                                <div class="session-card__title"><strong>{{ $session['label'] }}</strong>@if($session['is_current'])<span class="badge badge--success">Sesión actual</span>@elseif($session['is_expired'])<span class="badge badge--muted">Caducada</span>@endif</div>
                                <p>IP {{ $session['ip_address'] }} · Actividad {{ $session['last_activity_at']->locale('es')->diffForHumans() }}</p>
                                <small>{{ $session['last_activity_at']->timezone('Europe/Madrid')->format('d/m/Y H:i') }}</small>
                            </div>
                            @if (! $session['is_current'])
                                <form action="{{ route('profile.sessions.destroy', $session['id']) }}" method="post" data-confirm="¿Cerrar esta sesión? El dispositivo tendrá que volver a iniciar sesión.">@csrf @method('delete')<button class="button button--secondary button--small" type="submit">Revocar</button></form>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="profile-card profile-card--muted" aria-labelledby="profile-account-title">
                <div class="profile-card__heading"><div><p class="eyebrow">Conservación</p><h2 id="profile-account-title">Cuenta de usuario</h2></div></div>
                <p class="profile-card__intro">SmartWallet no permite eliminar cuentas definitivamente en la versión actual. Así se conserva la autoría histórica de movimientos y cambios.</p>
            </section>
        </div>
    </div>
@endsection
