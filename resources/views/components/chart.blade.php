{{--
    <x-chart> — Gráfica con Chart.js (npm/Vite, prohibido CDN).

    Props:
      @prop string      $type         Tipo Chart.js: line (default) | bar | doughnut.
      @prop array       $labels       Etiquetas del eje X (ej. ['Lun', 'Mar', ...]).
      @prop array       $datasets     Series: [['label' => string, 'data' => array,
                                     'colorToken' => '--rutx-chart-blue'|'--rutx-chart-cyan'], ...].
      @prop string      $id           Id del <canvas> (default: aleatorio).
      @prop int         $height       Altura del contenedor en px (default: 320).
      @prop string      $summary      Descripción accesible (aria-label y fallback).
      @prop string|null $format       Formato del eje y tooltips (ej. 'currency').
      @prop string|null $currency     Divisa contractual (ej. 'MXN') para tooltips monetarios.
      @prop string      $emptyMessage Mensaje mostrado si la gráfica no tiene datos.

    Este componente es solo HTML + data-attributes; la inicialización vive en
    resources/js/modules/chart.js (importado en app.js). Sin colores propios:
    la paleta sale de los tokens --rutx-chart-* (guidelines §5.2).
--}}
@props([
    'type' => 'line',
    'labels' => [],
    'datasets' => [],
    'id' => 'rutx-chart-'.\Illuminate\Support\Str::random(6),
    'height' => 320,
    'summary' => 'Gráfica',
    'format' => null,
    'currency' => null,
    'emptyMessage' => 'Sin datos para el período seleccionado',
    'scrollable' => false,
    'pageSize' => 6,
])

<div class="bg-rutx-surface border border-rutx-border rounded-lg p-4 shadow-[var(--rutx-shadow-base)]">
    @if (empty($labels) || empty($datasets))
        <div style="height: {{ $height }}px;" class="w-full flex items-center justify-center">
            <p class="text-sm text-rutx-text-muted text-center" role="status">
                {{ $emptyMessage }}
            </p>
        </div>
    @elseif ($scrollable)
        <div class="w-full overflow-x-auto" role="region" aria-label="{{ $summary }}">
            <div data-chart-scroll-inner style="height: {{ $height }}px; min-width: 100%;">
                <canvas
                    id="{{ $id }}"
                    data-rutx-chart
                    data-type="{{ $type }}"
                    data-format="{{ $format }}"
                    data-currency="{{ $currency }}"
                    data-labels='{{ json_encode($labels, JSON_UNESCAPED_UNICODE) }}'
                    data-datasets='{{ json_encode($datasets, JSON_UNESCAPED_UNICODE) }}'
                    data-scrollable="true"
                    data-page-size="{{ $pageSize }}"
                    role="img"
                    aria-label="{{ $summary }}"
                    tabindex="0"
                    class="w-full h-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rutx-accent rounded-lg"
                >{{ $summary }}</canvas>
            </div>
        </div>
    @else
        <div style="height: {{ $height }}px;" class="w-full">
            <canvas
                id="{{ $id }}"
                data-rutx-chart
                data-type="{{ $type }}"
                data-format="{{ $format }}"
                data-currency="{{ $currency }}"
                data-labels='{{ json_encode($labels, JSON_UNESCAPED_UNICODE) }}'
                data-datasets='{{ json_encode($datasets, JSON_UNESCAPED_UNICODE) }}'
                role="img"
                aria-label="{{ $summary }}"
                tabindex="0"
                class="w-full h-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rutx-accent rounded-lg"
            >{{ $summary }}</canvas>
        </div>
    @endif
</div>
