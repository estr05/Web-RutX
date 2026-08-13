@php
    // Obtener el módulo activo según la ruta actual
    $activeModuleKey = null;
    $modules = config('navigation.modules', []);
    foreach($modules as $key => $module) {
        if (request()->routeIs($key . '.*')) {
            $activeModuleKey = $key;
            break;
        }
    }
    // Si no hay match pero se envio la variable, usarla (útil para playground)
    if (!$activeModuleKey && isset($activeModule)) {
        $activeModuleKey = $activeModule;
    }
    $currentModule = $activeModuleKey ? ($modules[$activeModuleKey] ?? null) : null;
@endphp

<aside class="w-64 bg-[#002D47] shadow-sm flex flex-col z-10 shrink-0">
    @if($currentModule)
        <!-- Module Header -->
        <div class="h-[var(--rutx-height-topbar)] flex items-center px-6 border-b border-white/10">
            <h2 class="text-white font-bold tracking-wider uppercase text-sm">{{ $currentModule['label'] }}</h2>
        </div>

        <!-- Views Navigation -->
        <nav class="flex-1 py-4 overflow-y-auto">
            <ul class="space-y-1">
                @foreach($currentModule['views'] as $route => $view)
                    @php
                        $isViewActive = request()->routeIs($route);
                    @endphp
                    <li>
                        <a href="{{ route($route ?? '#') }}" 
                           class="flex items-center px-6 py-3 transition-colors {{ $isViewActive ? 'bg-rutx-primary border-l-4 border-rutx-accent text-white' : 'text-white/75 hover:bg-white/10 hover:text-white border-l-4 border-transparent' }}">
                            <span class="text-sm font-medium">{{ $view['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @else
        <!-- Placeholder cuando no hay modulo seleccionado -->
        <div class="flex-1 flex items-center justify-center p-6 text-white/50 text-sm text-center">
            Seleccione un módulo
        </div>
    @endif
</aside>
