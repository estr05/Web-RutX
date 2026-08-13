<header class="h-[var(--rutx-height-topbar)] bg-rutx-primary flex items-center justify-between px-6 shadow-sm z-20 relative">
    <div class="flex items-center space-x-6">
        <!-- Logo -->
        <div class="text-white font-bold text-xl tracking-wider">
            RUTX
        </div>
        <!-- Sync Status Pill -->
        <div class="flex items-center bg-white/10 rounded-full px-3 py-1 text-xs font-medium text-white space-x-2">
            <span>Sincronización</span>
            <span class="text-rutx-status-success">●</span>
            <span>CONECTADO</span>
        </div>
    </div>

    <!-- Modules Navigation -->
    <nav class="flex h-full">
        @foreach(config('navigation.modules', []) as $key => $module)
            @php
                // Logica basica para detectar el modulo activo (segun el prefijo de la ruta)
                $isActive = request()->routeIs($key . '.*') || (isset($activeModule) && $activeModule === $key);
            @endphp
            <a href="{{ Route::has($module['default_route'] ?? '') ? route($module['default_route']) : '#' }}" 
               class="flex items-center px-4 h-full transition-colors {{ $isActive ? 'text-white border-b-3 border-rutx-accent bg-rutx-primary-dark' : 'text-white/70 hover:text-white hover:bg-white/10' }}">
                <span class="text-sm font-medium">{{ $module['label'] }}</span>
            </a>
        @endforeach
    </nav>
</header>
