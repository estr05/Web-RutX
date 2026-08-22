{{--
    Contenido de Ruta · Mapa en Tiempo Real (render del componente Livewire).
    Anatomía estándar (Plan Visual §2.4): breadcrumb → header → filtros → mapa.
    El mapa ocupa el papel principal y los KPIs van en un panel lateral colapsable ($sidePanelOpen).
    Sin datos ni cálculos: todo viene de las propiedades computadas del
    componente ($zones, $filteredRoutes, $sideKpis, $markers).
--}}
<div wire:poll.{{ $pollInterval }}s>
    <x-breadcrumb />

    <div class="flex gap-6">
    {{-- Panel lateral colapsable con KPIs de ruta (solo en el DOM si está abierto) --}}
    @if ($sidePanelOpen)
        <aside
            class="w-72 shrink-0 bg-rutx-surface border border-rutx-border rounded-lg p-4 space-y-3 overflow-y-auto"
            aria-label="Estado de rutas"
        >
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-sm font-semibold text-rutx-primary uppercase tracking-wider">Estado de rutas</h2>
                <button
                    type="button"
                    wire:click="$toggle('sidePanelOpen')"
                    aria-label="Cerrar panel"
                    class="w-7 h-7 inline-flex items-center justify-center rounded-md text-rutx-text-muted
                           hover:bg-rutx-surface-grey hover:text-rutx-text transition-colors
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rutx-accent"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            @forelse ($this->sideKpis as $kpi)
                <x-kpi-card
                    :title="$kpi['title']"
                    :value="$kpi['value']"
                    :delta="$kpi['delta']"
                    :status="$kpi['status']"
                    format="text"
                />
            @empty
                <p class="text-sm text-rutx-text-muted py-6 text-center">Sin rutas en el periodo.</p>
            @endforelse
        </aside>
    @endif

    <div class="flex-1 min-w-0">
        <x-page-header
            title="Mapa en Tiempo Real"
            subtitle="Posición y última venta de cada ruta, con actualización cada {{ $pollInterval }} s."
        />

        <form wire:submit="consultar">
            <x-filter-bar>
                <div>
                    <label for="mapa-zona" class="block text-xs font-semibold text-rutx-text-muted mb-1">Zona</label>
                    <select
                        id="mapa-zona"
                        wire:model.live="zoneId"
                        aria-label="Zona"
                        class="h-[var(--rutx-height-input)] border border-rutx-border rounded-lg bg-rutx-surface-grey px-3 text-sm
                               focus:ring-2 focus:ring-rutx-primary focus:border-rutx-primary outline-none transition-shadow"
                    >
                        <option value="">Todas las zonas</option>
                        @foreach ($this->zones as $zone)
                            <option value="{{ $zone['zone_id'] }}">{{ $zone['zone_name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="mapa-ruta" class="block text-xs font-semibold text-rutx-text-muted mb-1">Ruta</label>
                    <select
                        id="mapa-ruta"
                        wire:model.live="routeId"
                        aria-label="Ruta"
                        class="h-[var(--rutx-height-input)] border border-rutx-border rounded-lg bg-rutx-surface-grey px-3 text-sm
                               focus:ring-2 focus:ring-rutx-primary focus:border-rutx-primary outline-none transition-shadow"
                    >
                        <option value="">Todas las rutas</option>
                        @foreach ($this->filteredRoutes as $route)
                            <option value="{{ $route['route_id'] }}">{{ $route['route_name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <x-slot:actions>
                    <button
                        type="button"
                        wire:click="$toggle('sidePanelOpen')"
                        class="h-[var(--rutx-height-button)] px-6 rounded-xl border border-rutx-border text-rutx-text-muted
                               hover:bg-rutx-surface-grey transition-colors font-medium text-sm
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rutx-accent"
                    >
                        {{ $sidePanelOpen ? 'Ocultar panel' : 'Mostrar panel' }}
                    </button>
                </x-slot:actions>
            </x-filter-bar>
        </form>

        <x-map-view
            :markers="$this->markers"
            height="520px"
        />
    </div>{{-- .flex --}}
</div>{{-- wire:poll --}}
