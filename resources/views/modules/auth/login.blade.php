{{-- Login de oficina (Sprint 4 · Bloque 0).
     Formulario real: CSRF, validación local, throttle en la ruta y errores
     funcionales. El token JWT se guarda SOLO en la sesión cifrada del
     servidor; nunca en localStorage, cookies legibles ni atributos data-.
     Sin hex: únicamente tokens y componentes existentes. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Inicia sesión · RutX Web</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-rutx-bg text-rutx-text font-sans antialiased">
    <div class="min-h-screen flex items-center justify-center p-6">
        <div class="w-full max-w-md bg-rutx-surface border border-rutx-border rounded-[var(--rutx-radius-lg)] p-8 shadow-[var(--rutx-shadow-base)]">
            <h1 class="text-[30px] leading-[1.2] font-bold text-rutx-primary tracking-tight">
                RUTX
            </h1>
            <p class="mt-1 text-sm text-rutx-text-muted">
                Plataforma de administración y operaciones de rutas.
            </p>

            @if ($errors->any())
                <div class="mt-6">
                    <x-alert type="error" :message="$errors->first()" />
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="mt-6 border-t border-rutx-border pt-6 flex flex-col gap-4">
                @csrf

                <div class="flex flex-col gap-1.5">
                    <label for="username" class="text-sm font-medium text-rutx-text">
                        Usuario
                    </label>
                    <input
                        id="username"
                        type="text"
                        name="username"
                        value="{{ old('username') }}"
                        required
                        autocomplete="username"
                        class="h-[var(--rutx-height-input)] px-3 rounded-lg border border-rutx-border bg-rutx-bg text-sm focus:outline-none focus:ring-2 focus:ring-rutx-accent"
                    >
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="password" class="text-sm font-medium text-rutx-text">
                        Contraseña
                    </label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        class="h-[var(--rutx-height-input)] px-3 rounded-lg border border-rutx-border bg-rutx-bg text-sm focus:outline-none focus:ring-2 focus:ring-rutx-accent"
                    >
                </div>

                <button
                    type="submit"
                    class="h-[var(--rutx-height-button)] rounded-xl bg-rutx-accent text-white hover:bg-rutx-accent-hover transition-colors font-medium text-sm mt-2"
                >
                    Iniciar sesión
                </button>
            </form>
        </div>
    </div>
</body>
</html>