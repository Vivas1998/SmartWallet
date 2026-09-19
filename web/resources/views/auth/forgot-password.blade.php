@extends('layouts.guest')

@section('title', 'Recuperar contraseña')

@section('content')
    <section class="auth-card" aria-labelledby="forgot-title">
        <div class="auth-card__heading">
            <p class="eyebrow">Recuperación segura</p>
            <h1 class="auth-card__title" id="forgot-title">Recuperar contraseña</h1>
            <p class="auth-card__intro">
                Introduce tu correo. Si existe una cuenta, enviaremos un enlace válido durante 60 minutos.
            </p>
        </div>

        <form class="form" action="{{ route('password.email') }}" method="post">
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
                >
                @error('email')
                    <p class="field__error">{{ $message }}</p>
                @enderror
            </div>

            <button class="button button--primary button--wide" type="submit">Enviar enlace</button>
        </form>

        <p class="auth-card__footer">
            <a class="text-link" href="{{ route('login') }}">Volver al inicio de sesión</a>
        </p>
    </section>
@endsection
