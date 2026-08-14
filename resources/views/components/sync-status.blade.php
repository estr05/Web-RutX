{{--
    <x-sync-status> — Pill de estado de sincronización de la topbar (Plan Visual §2.3.2).

    Props:
      @prop bool|null  $connected   true → CONECTADO (rutx-status-success);
                                   false/null → SIN CONEXIÓN (rutx-status-unknown).
                                   null = estado desconocido (sin integración aún).
      @prop string|null $lastSyncAt Fecha/hora de la última sincronización (detalle).

    La pill es funcional, no decorativa: refleja el estado de conexión con la
    API del Sincronizador. Mientras no exista un endpoint de salud en el
    contrato v2, la topbar la renderiza con estado desconocido (SIN CONEXIÓN).
--}}
@props([
    'connected' => null,
    'lastSyncAt' => null,
])

@php
    $isConnected = $connected === true;
@endphp

<div
    class="flex items-center gap-2 bg-white/10 rounded-full px-3 py-1 text-xs font-medium text-white/70"
    role="status"
    aria-live="polite"
    aria-label="Estado de sincronización: {{ $isConnected ? 'conectado' : 'sin conexión' }}"
    title="Última sincronización: {{ $lastSyncAt ?? 'no disponible' }}"
>
    <span
        class="w-2 h-2 rounded-full {{ $isConnected ? 'bg-rutx-status-success' : 'bg-rutx-status-unknown' }}"
        aria-hidden="true"
    ></span>
    <span>Sincronización</span>
    <span class="{{ $isConnected ? 'text-rutx-status-success' : 'text-rutx-status-unknown' }} font-semibold uppercase tracking-widest">
        {{ $isConnected ? 'CONECTADO' : 'SIN CONEXIÓN' }}
    </span>
</div>
