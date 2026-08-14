{{--
    <x-app-sidebar>
    Barra lateral contextual de RutX Web.

    Responsabilidades:
      - Mostrar título del módulo activo.
      - Listar exclusivamente las vistas del módulo activo leídas de config/navigation.php.
      - Marcar la vista activa con fondo rutx-primary-dark y borde izquierdo de 4 px rutx-accent.
      - Admitir colapso a 64 px con tooltip accesible.

    Props: ninguna. El estado activo se deriva de route()->getName().

    Al cambiar de módulo se reconstruye desde la misma fuente de configuración;
    no aparecen vistas de otros módulos.

    Día 2 — sin datos, sin llamadas API.
--}}
@php
    $currentRoute = Route::currentRouteName() ?? '';
    $modules      = config('navigation.modules', []);

    // Detectar módulo activo por prefijo del nombre de ruta
    $activeModuleKey = null;
    foreach ($modules as $key => $module) {
        if (str_starts_with($currentRoute, $key . '.')) {
            $activeModuleKey = $key;
            break;
        }
    }
    $currentModule = $activeModuleKey ? ($modules[$activeModuleKey] ?? null) : null;
@endphp

{{-- El sidebar usa un checkbox oculto para gestionar el colapso sin JS obligatorio --}}
<input
    type="checkbox"
    id="sidebar-collapse-toggle"
    class="sr-only peer"
    aria-hidden="true"
/>

<aside
    class="
        relative flex flex-col bg-rutx-primary-dark shadow-sm z-10 shrink-0
        transition-[width] duration-200 ease-in-out
        w-64 peer-checked:w-16
    "
    aria-label="Navegación de módulo"
>
    {{-- Botón de colapso (48 px objetivo táctil) --}}
    <label
        for="sidebar-collapse-toggle"
        class="
            absolute -right-3 top-[calc(var(--rutx-height-topbar)/2)]
            flex items-center justify-center
            w-6 h-6 rounded-full
            bg-rutx-primary-dark border border-white/20
            cursor-pointer text-white/60 hover:text-white hover:bg-rutx-primary
            transition-colors z-20
            focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rutx-accent
        "
        aria-label="Colapsar / expandir barra lateral"
        tabindex="0"
        role="button"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-3 h-3 peer-checked:rotate-180 transition-transform" aria-hidden="true">
            <path fill-rule="evenodd" d="M11.78 3.22a.75.75 0 0 1 0 1.06L8.06 8l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
        </svg>
    </label>

    @if($currentModule)
        {{-- Cabecera del módulo (visible en estado expandido) --}}
        <div class="h-[var(--rutx-height-topbar)] flex items-center px-6 border-b border-white/10 overflow-hidden">
            <h2 class="text-white font-bold tracking-wider uppercase text-sm whitespace-nowrap peer-checked:opacity-0 transition-opacity">
                {{ $currentModule['label'] }}
            </h2>
        </div>

        {{-- Lista de vistas del módulo activo --}}
        <nav class="flex-1 py-4 overflow-y-auto overflow-x-hidden" aria-label="{{ $currentModule['label'] }}">
            <ul class="space-y-1" role="list">
                @foreach($currentModule['views'] as $viewRoute => $view)
                    @php
                        $isViewActive = ($currentRoute === $viewRoute);
                        $href = (Route::has($viewRoute)) ? route($viewRoute) : '#';
                        $viewId = 'sidebar-' . str_replace('.', '-', $viewRoute);
                    @endphp
                    <li role="listitem">
                        <a
                            id="{{ $viewId }}"
                            href="{{ $href }}"
                            class="
                                flex items-center px-6 py-3 gap-3
                                transition-colors
                                border-l-4
                                min-h-[48px]
                                focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-rutx-accent
                                {{ $isViewActive
                                    ? 'bg-rutx-primary border-l-rutx-accent text-white'
                                    : 'text-white/75 hover:bg-white/10 hover:text-white border-l-transparent' }}
                            "
                            aria-current="{{ $isViewActive ? 'page' : 'false' }}"
                            title="{{ $view['label'] }}"
                        >
                            <span class="text-sm font-medium whitespace-nowrap truncate">{{ $view['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @else
        {{-- Placeholder cuando no hay módulo activo --}}
        <div class="flex-1 flex items-center justify-center p-6 text-white/40 text-sm text-center">
            <span class="peer-checked:hidden">Seleccione un módulo</span>
        </div>
    @endif
</aside>
