@extends('layouts.guest')

@section('title', 'Iniciar sesión')

@section('content')
    <section class="auth-card" aria-labelledby="login-title">
        <div class="auth-card__heading">
            <p class="eyebrow">Tu economía, bien organizada</p>
            <h1 class="auth-card__title" id="login-title">Iniciar sesión</h1>
            <p class="auth-card__intro">Accede a tus proyectos financieros familiares.</p>
        </div>

        <form class="form" action="{{ route('login') }}" method="post">
            @csrf

            <div class="field">
                <label class="field__label" for="email">Correo electrónico</label>
                <input
                    class="field__control @error('email') field__control--invalid @enderror"
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    autocomplete="email"
                    required
                    aria-describedby="@error('email') email-error @enderror"
                >
                @error('email')
                    <p class="field__error" id="email-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <div class="field__label-row">
                    <label class="field__label" for="password">Contraseña</label>
                    <a class="text-link" href="{{ route('password.request') }}">La he olvidado</a>
                </div>
                <input
                    class="field__control @error('password') field__control--invalid @enderror"
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    aria-describedby="@error('password') password-error @enderror"
                >
                @error('password')
                    <p class="field__error" id="password-error">{{ $message }}</p>
                @enderror
            </div>

            <label class="check">
                <input class="check__control" name="remember" type="checkbox" value="1">
                <span class="check__label">Recordarme durante 30 días</span>
            </label>

            <button class="button button--primary button--wide" type="submit">Entrar</button>
        </form>

        @if (config('smartwallet.registration_enabled'))
            <p class="auth-card__footer">
                ¿Aún no tienes cuenta?
                <a class="text-link" href="{{ route('register') }}">Crear una cuenta</a>
            </p>
        @endif
    </section>
@endsection
