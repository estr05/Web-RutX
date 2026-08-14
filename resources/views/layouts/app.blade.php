<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'RutX Web') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-rutx-bg text-rutx-text font-sans antialiased">
    <div class="min-h-screen flex flex-col">
        <!-- Topbar -->
        <x-app-topbar />

        <div class="flex flex-1 overflow-hidden">
            <!-- Sidebar -->
            <x-app-sidebar />

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto p-6 bg-rutx-bg">
                {{ $slot }}
            </main>
        </div>
    </div>

    <x-feedback-stack />
</body>
</html>
