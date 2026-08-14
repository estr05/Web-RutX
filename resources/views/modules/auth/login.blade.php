{{-- Login — placeholder de oficina (Sprint 3 · Etapa 2).
     Solo es el destino de redirección de auth.session. El vertical real
     (Form Request + POST /api/v2/web/auth/login) llega en una etapa
     posterior. Sin datos, sin hex: únicamente tokens y componentes. --}}
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
        <div class="w-full max-w-md bg-rutx-surface border border-rutx-border rounded-[var(--rutx-radius-lg)] p-8 shadow-sm">
            <h1 class="text-[30px] leading-[1.2] font-bold text-rutx-primary tracking-tight">
                RUTX
            </h1>
            <p class="mt-1 text-sm text-rutx-text-muted">
                Plataforma de administración y operaciones de rutas.
            </p>

            <div class="mt-6 border-t border-rutx-border pt-6">
                <p class="text-sm text-rutx-text">
                    La autenticación de oficina se integra en una etapa posterior.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
