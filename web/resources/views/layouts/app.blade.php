<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title') · {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <a class="skip-link" href="#main-content">Saltar al contenido principal</a>
        <div class="app-shell">
            <header class="topbar">
                <a class="brand" href="{{ route('dashboard') }}" aria-label="{{ config('app.name') }}, mis proyectos">
                    <span class="brand__mark" aria-hidden="true">S</span>
                    <span class="brand__name">{{ config('app.name') }}</span>
                </a>

                <div class="topbar__account">
                    <a class="topbar__profile {{ request()->routeIs('profile.*') ? 'topbar__profile--active' : '' }}" href="{{ route('profile.show') }}" aria-label="Abrir mi perfil">
                        <span class="topbar__avatar" aria-hidden="true">{{ auth()->user()->initials() }}</span>
                        <span class="topbar__user">{{ auth()->user()->name }}</span>
                    </a>
                    <form action="{{ route('logout') }}" method="post">
                        @csrf
                        <button class="button button--quiet" type="submit">Cerrar sesión</button>
                    </form>
                </div>
            </header>

            <main class="app-shell__main" id="main-content" tabindex="-1">
                @if (session('status'))
                    <div class="alert alert--success" role="status">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert--error" role="alert">
                        <p class="alert__title">Revisa los datos indicados:</p>
                        <ul class="alert__list">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>

        @unless(app()->isProduction())
            <footer class="environment">
                {{ config('app.version') }} ·
                {{ app()->environment() }} ·
                {{ config('database.connections.'.config('database.default').'.database') }}
            </footer>
        @endunless
    </body>
</html>
