@extends('layouts.guest')

@section('title', 'Cambiar contraseña')

@section('content')
    <section class="auth-card" aria-labelledby="reset-title">
        <div class="auth-card__heading">
            <p class="eyebrow">Elige una nueva clave</p>
            <h1 class="auth-card__title" id="reset-title">Cambiar contraseña</h1>
            <p class="auth-card__intro">La nueva contraseña debe tener al menos 12 caracteres.</p>
        </div>

        <form class="form" action="{{ route('password.update') }}" method="post">
            @csrf
            <input name="token" type="hidden" value="{{ $token }}">

            <div class="field">
                <label class="field__label" for="email">Correo electrónico</label>
                <input
                    class="field__control @error('email') field__control--invalid @enderror"
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email', $email) }}"
                    autocomplete="email"
                    required
                >
                @error('email')
                    <p class="field__error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label class="field__label" for="password">Nueva contraseña</label>
                <input
                    class="field__control @error('password') field__control--invalid @enderror"
                    id="password"
                    name="password"
                    type="password"
                    minlength="12"
                    autocomplete="new-password"
                    required
                >
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

            <button class="button button--primary button--wide" type="submit">Guardar contraseña</button>
        </form>
    </section>
@endsection
