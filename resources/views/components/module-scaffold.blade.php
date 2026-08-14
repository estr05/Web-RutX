{{--
    Scaffold de módulo — plantilla reutilizable.
    Props:
      @prop string $moduleLabel  Etiqueta del módulo (ej. "Venta")
      @prop string $viewLabel    Etiqueta de la vista (ej. "Reportes y Gráficas")
      @prop string $note         Nota opcional para el desarrollador

    Día 2 — scaffold sin endpoint v2.
    No contiene datos falsos, filtros simulados ni llamadas HTTP.
--}}
@props([
    'moduleLabel' => '',
    'viewLabel'   => '',
    'note'        => 'Esta pantalla es un scaffold del Día 2. El contenido funcional se conecta en sprints posteriores mediante /api/v2/web/*.',
])

<x-app-layout>
    <div class="flex flex-col gap-6">
        {{-- Breadcrumb derivado automáticamente por el componente --}}
        <x-breadcrumb />

        {{-- Cabecera de la vista --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-rutx-text">{{ $viewLabel }}</h1>
                <p class="text-sm text-rutx-text-muted mt-1">{{ $moduleLabel }}</p>
            </div>
        </div>

        {{-- Área de contenido: placeholder neutro --}}
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
