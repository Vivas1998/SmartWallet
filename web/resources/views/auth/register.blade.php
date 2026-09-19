@extends('layouts.guest')

@section('title', 'Crear cuenta')

@section('content')
    <section class="auth-card" aria-labelledby="register-title">
        <div class="auth-card__heading">
            <p class="eyebrow">Empieza en unos minutos</p>
            <h1 class="auth-card__title" id="register-title">Crear una cuenta</h1>
            <p class="auth-card__intro">Después podrás crear tus proyectos y compartirlos con tu familia.</p>
        </div>

        <form class="form" action="{{ route('register') }}" method="post">
            @csrf

            <div class="field">
                <label class="field__label" for="name">Nombre visible</label>
                <input
                    class="field__control @error('name') field__control--invalid @enderror"
                    id="name"
                    name="name"
                    type="text"
                    value="{{ old('name') }}"
                    maxlength="150"
                    autocomplete="name"
                    required
                >
                @error('name')
                    <p class="field__error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label class="field__label" for="email">Correo electrónico</label>
                <input
                    class="field__control @error('email') field__control--invalid @enderror"
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    maxlength="255"
                    autocomplete="email"
                    required
                >
                @error('email')
                    <p class="field__error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label class="field__label" for="password">Contraseña</label>
                <input
                    class="field__control @error('password') field__control--invalid @enderror"
                    id="password"
                    name="password"
                    type="password"
                    minlength="12"
                    autocomplete="new-password"
                    required
                >
                <p class="field__hint">Debe tener al menos 12 caracteres.</p>
                @error('password')
                    <p class="field__error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label class="field__label" for="password_confirmation">Repetir contraseña</label>
                <input
                    class="field__control"
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    minlength="12"
                    autocomplete="new-password"
                    required
                >
            </div>

            <button class="button button--primary button--wide" type="submit">Crear mi cuenta</button>
        </form>

        <p class="auth-card__footer">
            ¿Ya tienes cuenta?
            <a class="text-link" href="{{ route('login') }}">Iniciar sesión</a>
        </p>
    </section>
@endsection
