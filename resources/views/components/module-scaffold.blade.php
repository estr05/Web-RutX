{{--
    <x-module-scaffold>
    Plantilla scaffold reutilizable para todas las vistas de módulo.

    Props (todas opcionales — se derivan automáticamente desde config/navigation.php):
      @prop string|null $moduleLabel  Etiqueta del módulo. Si es null, se deriva del nombre de ruta.
      @prop string|null $viewLabel    Etiqueta de la vista. Si es null, se deriva del nombre de ruta.
      @prop string      $note         Nota para el desarrollador sobre el estado scaffold.

    El componente resuelve moduleLabel y viewLabel desde la misma fuente que
    breadcrumb y topbar (config/navigation.php), eliminando la posibilidad de
    divergencia entre la UI y la configuración central.

    Día 2 — scaffold sin endpoint v2.
    No contiene datos falsos, filtros simulados ni llamadas HTTP.
--}}
@props([
    'moduleLabel' => null,
    'viewLabel'   => null,
    'note'        => 'Esta pantalla es un scaffold del Día 2. El contenido funcional se conecta en sprints posteriores mediante /api/v2/web/*.',
])

@php
    // Derivar etiquetas desde config/navigation.php si no se proporcionaron
    if ($moduleLabel === null || $viewLabel === null) {
        $currentRoute = Route::currentRouteName() ?? '';
        foreach (config('navigation.modules', []) as $moduleKey => $module) {
            if (! str_starts_with($currentRoute, $moduleKey . '.')) {
                continue;
            }
            $moduleLabel ??= $module['label'];
            $viewLabel   ??= $module['views'][$currentRoute]['label'] ?? '';
            break;
        }
    }
    $moduleLabel ??= '';
    $viewLabel   ??= '';
@endphp

<x-app-layout>
    <div class="flex flex-col gap-6">
        {{-- Breadcrumb: deriva Módulo · Vista automáticamente --}}
        <x-breadcrumb />

        {{-- Cabecera derivada desde navigation config --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-rutx-text">{{ $viewLabel }}</h1>
                <p class="text-sm text-rutx-text-muted mt-1">{{ $moduleLabel }}</p>
            </div>
        </div>

        {{-- Área de contenido: placeholder neutro, sin datos falsos --}}
        <div class="bg-rutx-surface rounded-[var(--rutx-radius-lg)] border border-rutx-border p-8 flex flex-col items-center justify-center min-h-[320px]">
            <x-loading-state message="Sin datos — pendiente integración v2" />

            @if($note)
                <p class="mt-4 text-xs text-rutx-text-muted text-center max-w-sm">
                    {{ $note }}
                </p>
            @endif
        </div>
    </div>
</x-app-layout>
