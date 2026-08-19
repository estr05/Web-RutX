{{--
    <x-notification-bell> — campana del topbar (utilidad de Notificaciones).

    Estados honestos (checklist §9):
      - $count === null  → desconocido (aún sin respuesta; NO se muestra cero).
      - $failed === true → error de red; conserva el último valor conocido.
      - $count > 0       → badge con el número de avisos activos.
      - $count === 0     → campana limpia.

    El polling (30 s) y la consulta los maneja el componente Livewire
    App\Livewire\Notifications\Bell; esta vista solo renderiza el arquetipo.
--}}
@props(['count' => null, 'failed' => false])

<div wire:poll.{{ $pollInterval }}s="refresh" class="relative" title="{{ $failed ? 'No se pudo conectar con el servicio' : ($count === null ? 'Notificaciones' : ($count > 0 ? "$count avisos activos" : 'Sin avisos activos')) }}">
    <a
        href="{{ Route::has('notifications.index') ? route('notifications.index') : '#' }}"
        id="topbar-notifications-bell"
        aria-label="Notificaciones"
        class="flex items-center justify-center w-10 h-10 rounded-lg transition-colors
               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rutx-accent
               {{ $failed ? 'bg-rutx-status-error/15' : 'hover:bg-white/10' }}"
    >
        {{-- Campana --}}
        <svg class="w-5 h-5 {{ $failed ? 'text-rutx-status-error' : 'text-white/80' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
        </svg>

        {{-- Badge de avisos activos (nunca 0; solo cuando count > 0) --}}
        @if ($count !== null && $count > 0)
            <span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-rutx-accent text-white text-[11px] font-bold flex items-center justify-center">
                {{ $count > 99 ? '99+' : $count }}
            </span>
        @endif
    </a>
</div>