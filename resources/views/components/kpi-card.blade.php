{{--
    <x-kpi-card> — Tarjeta de indicador clave de rendimiento.

    Props:
      @prop string            $title     Etiqueta del KPI.
      @prop float|int|string  $value     Valor principal.
      @prop string            $format    Formato explícito: 'currency' | 'integer' | 'percent' | 'text' (obligatorio).
      @prop string|null       $delta     Etiqueta de variación opcional (+5.2%).
      @prop string            $status    Color semántico del delta (success, warning, error, unknown).
      @prop mixed             $icon      Slot opcional con SVG.
      @prop string|null       $iconName  Nombre técnico de icono del allowlist (<x-navigation-icon>).
      @prop string|null       $subLabel  Subetiqueta secundaria opcional.
      @prop float|int|string|null $subValue Valor secundario opcional.
      @prop array             $details   Desgloses secundarios opcionales (ej. Contado/Crédito).
--}}
@props([
    'title',
    'value',
    'format',
    'delta' => null,
    'status' => 'unknown',
    'icon' => null,
    'iconName' => null,
    'subLabel' => null,
    'subValue' => null,
    'details' => [],
])

@php
    $displayValue = match($format) {
        'currency' => \App\Support\Money::format((float) $value),
        'integer'  => number_format((float) $value, 0, '.', ','),
        'percent'  => (string) $value,
        default    => (string) $value,
    };

    $isMonetary = ($format === 'currency');

    $statusColor = match($status) {
        'success' => 'text-rutx-status-success',
        'warning' => 'text-rutx-status-warning',
        'error'   => 'text-rutx-status-error',
        default   => 'text-rutx-status-unknown',
    };
@endphp

<div class="bg-rutx-surface border border-rutx-border rounded-lg p-4 shadow-[var(--rutx-shadow-base)] hover:shadow-[var(--rutx-shadow-hover)] transition-shadow min-w-0 flex flex-col justify-between">
    <div>
        <div class="flex items-center justify-between mb-2 gap-2 min-w-0">
            <span class="text-xs font-medium text-rutx-text-muted uppercase tracking-wider truncate">{{ $title }}</span>
            @if($icon)
                <div class="text-rutx-primary w-5 h-5 shrink-0">
                    {{ $icon }}
                </div>
            @elseif($iconName)
                <div class="text-rutx-primary w-5 h-5 shrink-0">
                    <x-navigation-icon :name="$iconName" class="w-5 h-5" />
                </div>
            @endif
        </div>

        {{-- Contenedor fluido sin truncate ni overflow-hidden: flex-wrap para preservar dígitos completos sin cortes extraños --}}
        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1 min-w-0">
            <span class="text-[20px] sm:text-[24px] xl:text-[28px] font-bold text-rutx-text leading-tight whitespace-nowrap {{ $isMonetary ? 'font-mono text-right' : '' }}">
                {{ $displayValue }}
            </span>

            @if($delta)
                <span class="text-xs font-semibold {{ $statusColor }} bg-current/10 px-1.5 py-0.5 rounded shrink-0">
                    {{ $delta }}
                </span>
            @endif
        </div>
    </div>

    {{-- Sublínea de detalle de negocio (ej. "Contado + Crédito") --}}
    @if($subLabel !== null)
        <div class="mt-2 flex items-center justify-between text-xs text-rutx-text-muted min-w-0 gap-2 border-t border-rutx-border/50 pt-1.5">
            <span class="truncate">{{ $subLabel }}</span>
            @if($subValue !== null)
                <span class="font-mono shrink-0"><x-currency :amount="$subValue" /></span>
            @endif
        </div>
    @endif

    {{-- Desgloses secundarios opcionales (ej. Entrega: Contado / Crédito) --}}
    @if(!empty($details))
        <div class="mt-2 space-y-1 border-t border-rutx-border/50 pt-2 min-w-0">
            @foreach($details as $detail)
                <div class="flex items-center justify-between text-xs text-rutx-text-muted min-w-0 gap-2">
                    <span class="truncate">{{ $detail['label'] ?? '' }}</span>
                    <span class="font-mono shrink-0"><x-currency :amount="$detail['value'] ?? 0" /></span>
                </div>
            @endforeach
        </div>
    @endif
</div>
