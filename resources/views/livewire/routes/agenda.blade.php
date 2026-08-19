<div>
    <!-- Barra de Filtros -->
    <x-filter-bar class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Zona -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                <select wire:model.live="filters.zone_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    <option value="">Todas las zonas</option>
                    @foreach($options['zones'] as $zone)
                        <option value="{{ $zone['id'] }}">{{ $zone['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Ruta -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ruta</label>
                <select wire:model.live="filters.route_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    <option value="">Todas las rutas</option>
                    @foreach($options['routes'] as $route)
                        <option value="{{ $route['id'] }}">{{ $route['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Fecha Inicio -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
                <input type="date" wire:model.live.debounce.500ms="filters.date_from" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
            </div>

            <!-- Fecha Fin -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
                <input type="date" wire:model.live.debounce.500ms="filters.date_to" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
            </div>
        </div>
    </x-filter-bar>

    <!-- Alertas y Estado -->
    @if($errorMessage)
        <div class="mb-6">
            <x-alert type="error" :message="$errorMessage" />
            @if($traceId)
                <div class="text-xs mt-1 text-red-500 opacity-75">Trace ID: {{ $traceId }}</div>
            @endif
        </div>
    @endif

    <div class="relative min-h-[400px]">
        <!-- Loading Overlay -->
        <div wire:loading wire:target="loadBoard, applyBatch, filters" class="absolute inset-0 z-10 bg-white/50 backdrop-blur-sm flex items-center justify-center rounded-lg">
            <x-loading-state message="Actualizando agenda..." />
        </div>

        @if(empty($days) && !$loading)
            <div class="text-center py-12 bg-white rounded-lg border border-gray-200">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-semibold text-gray-900">Sin datos de agenda</h3>
                <p class="mt-1 text-sm text-gray-500">No se encontraron registros para los filtros seleccionados.</p>
            </div>
        @else
            <!-- Tablero de Agenda Semanal -->
            <div class="grid grid-cols-1 xl:grid-cols-7 gap-4">
                @foreach($days as $day)
                    <div class="bg-white rounded-lg shadow-sm border {{ ($day['is_today'] ?? false) ? 'border-blue-300 ring-1 ring-blue-300' : 'border-gray-200' }} flex flex-col h-full overflow-hidden">
                        
                        <!-- Cabecera del Día -->
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                            <div>
                                <span class="block text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $day['weekday'] ?? 'Día' }}</span>
                                <span class="block text-lg font-bold text-gray-900">{{ \Carbon\Carbon::parse($day['date'])->format('d M') }}</span>
                            </div>
                            <x-status-badge :status="($day['is_today'] ?? false) ? 'active' : 'default'">
                                {{ $day['total_customers'] ?? 0 }}
                            </x-status-badge>
                        </div>

                        <!-- Lista de Vendedores/Rutas del Día -->
                        <div class="flex-1 p-2 bg-gray-50/50 space-y-2 overflow-y-auto" style="max-height: 60vh;">
                            @forelse($day['sellers'] ?? [] as $seller)
                                <div class="bg-white border border-gray-200 rounded-md p-3 shadow-sm hover:shadow transition-shadow cursor-pointer">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="font-medium text-sm text-gray-900 line-clamp-1" title="{{ $seller['name'] }}">
                                            {{ $seller['name'] }}
                                        </div>
                                    </div>
                                    <div class="flex items-center text-xs text-gray-500 mb-1">
                                        <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                        {{ $seller['route_name'] ?? 'Ruta Sin Nombre' }}
                                    </div>
                                    <div class="mt-2 flex justify-between items-center">
                                        <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                            {{ $seller['customers_count'] ?? 0 }} clientes
                                        </span>
                                        <button type="button" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Ver</button>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-4 text-xs text-gray-400">Sin rutas asignadas</div>
                            @endforelse
                        </div>

                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Script para notificaciones -->
    @script
    <script>
        $wire.on('agenda:notify', (e) => {
            // Se asume que el layout tiene algo para atrapar dispatch('notify') general
            // o lo manejamos localmente si el componente existe.
            if (window.dispatchEvent) {
                window.dispatchEvent(new CustomEvent('notify', {
                    detail: e
                }));
            }
        });
    </script>
    @endscript
</div>
