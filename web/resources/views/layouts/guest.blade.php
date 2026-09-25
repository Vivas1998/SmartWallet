<!DOCTYPE html>
<html lang="es" data-theme="auto">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title') · {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <a class="skip-link" href="#main-content">Saltar al contenido principal</a>
        <div class="auth-shell">
            <header class="auth-shell__header">
                <a class="brand" href="{{ route('login') }}" aria-label="{{ config('app.name') }}, inicio">
                    <span class="brand__mark" aria-hidden="true">S</span>
                    <span class="brand__name">{{ config('app.name') }}</span>
                </a>
            </header>

            <main class="auth-shell__main" id="main-content" tabindex="-1">
                @if (session('status'))
                    <div class="alert alert--success" role="status">{{ session('status') }}</div>
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
