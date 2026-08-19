<div>
    <x-filter-bar class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-rutx-text-base mb-1">Zona</label>
                <select wire:model.live="filters.zone_id" class="block w-full rounded-[var(--rutx-radius-base)] border-rutx-border shadow-[var(--rutx-shadow-sm)] focus:border-rutx-primary focus:ring-rutx-primary sm:text-sm">
                    <option value="">Todas las zonas</option>
                    @foreach($options['zones'] as $zone)
                        <option value="{{ $zone['id'] }}">{{ $zone['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-rutx-text-base mb-1">Ruta</label>
                <select wire:model.live="filters.route_id" class="block w-full rounded-[var(--rutx-radius-base)] border-rutx-border shadow-[var(--rutx-shadow-sm)] focus:border-rutx-primary focus:ring-rutx-primary sm:text-sm">
                    <option value="">Todas las rutas</option>
                    @foreach($options['routes'] as $route)
                        <option value="{{ $route['id'] }}">{{ $route['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-rutx-text-base mb-1">Desde</label>
                <input type="date" wire:model.live.debounce.500ms="filters.date_from" class="block w-full rounded-[var(--rutx-radius-base)] border-rutx-border shadow-[var(--rutx-shadow-sm)] focus:border-rutx-primary focus:ring-rutx-primary sm:text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-rutx-text-base mb-1">Hasta</label>
                <input type="date" wire:model.live.debounce.500ms="filters.date_to" class="block w-full rounded-[var(--rutx-radius-base)] border-rutx-border shadow-[var(--rutx-shadow-sm)] focus:border-rutx-primary focus:ring-rutx-primary sm:text-sm">
            </div>
        </div>

        <x-slot:actions>
            <button
                type="button"
                wire:click="applyBatch"
                wire:loading.attr="disabled"
                class="inline-flex items-center px-4 py-2 border border-transparent shadow-[var(--rutx-shadow-sm)] text-sm font-medium rounded-[var(--rutx-radius-base)] text-white bg-rutx-primary hover:bg-rutx-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rutx-primary disabled:opacity-50"
            >
                Guardar Cambios Pendientes
            </button>
        </x-slot:actions>
    </x-filter-bar>

    @if($errorMessage)
        <div class="mb-6">
            <x-alert type="error" :message="$errorMessage" />
        </div>
    @endif

    <div class="relative min-h-[500px] flex flex-col lg:flex-row gap-6">
        <div wire:loading wire:target="loadBoard, filters" class="absolute inset-0 z-10 bg-rutx-backdrop/50 backdrop-blur-sm flex items-center justify-center rounded-[var(--rutx-radius-lg)]">
            <x-loading-state message="Cargando agenda..." />
        </div>

        @if(empty($days))
            <div class="text-center py-12 bg-rutx-surface rounded-[var(--rutx-radius-lg)] shadow-[var(--rutx-shadow-sm)] border border-rutx-border w-full">
                <p class="text-sm text-rutx-text-muted">No se encontraron registros para los filtros seleccionados.</p>
            </div>
        @else
            <!-- Tablero (Izquierda) -->
            <div class="flex-1 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
                @foreach($days as $day)
                    <div class="bg-rutx-surface rounded-[var(--rutx-radius-lg)] shadow-[var(--rutx-shadow-sm)] border {{ ($day['is_today'] ?? false) ? 'border-rutx-primary ring-1 ring-rutx-primary' : 'border-rutx-border' }} flex flex-col h-full overflow-hidden">

                        <div class="bg-rutx-bg px-4 py-3 border-b border-rutx-border flex justify-between items-center">
                            <div>
                                <span class="block text-xs font-medium text-rutx-text-muted uppercase tracking-wider">{{ $day['weekday'] ?? 'Día' }}</span>
                                <span class="block text-lg font-bold text-rutx-text-base">{{ \Carbon\Carbon::parse($day['date'])->format('d M') }}</span>
                            </div>
                            <x-status-badge status="info" label="{{ $day['total_customers'] ?? 0 }}" />
                        </div>

                        <div class="flex-1 p-2 bg-rutx-bg/50 space-y-2 overflow-y-auto" style="max-height: 60vh;">
                            @forelse($day['sellers'] ?? [] as $seller)
                                <div class="bg-rutx-surface border border-rutx-border rounded-[var(--rutx-radius-base)] p-3 shadow-[var(--rutx-shadow-sm)] hover:shadow transition-shadow">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="font-medium text-sm text-rutx-text-base line-clamp-1" title="{{ $seller['name'] }}">
                                            {{ $seller['name'] }}
                                        </div>
                                    </div>
                                    <div class="flex items-center text-xs text-rutx-text-muted mb-1">
                                        {{ $seller['route_name'] ?? 'Ruta Sin Nombre' }}
                                    </div>
                                    <div class="mt-2 flex justify-between items-center">
                                        <x-status-badge status="success" label="{{ $seller['customers_count'] ?? 0 }} cli" />
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-4 text-xs text-rutx-text-muted">Sin rutas asignadas</div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Panel Lateral No Asignados (Derecha) -->
            <div class="w-full lg:w-80 flex flex-col gap-4">
                <div class="bg-rutx-surface rounded-[var(--rutx-radius-lg)] shadow-[var(--rutx-shadow-sm)] border border-rutx-border h-full flex flex-col overflow-hidden">
                    <div class="bg-rutx-bg px-4 py-3 border-b border-rutx-border flex justify-between items-center">
                        <span class="block text-sm font-bold text-rutx-text-base">No Asignados</span>
                        <x-status-badge status="warning" label="{{ count($unassignedCustomers) }}" />
                    </div>

                    <div class="flex-1 p-2 bg-rutx-bg/50 space-y-2 overflow-y-auto" style="max-height: 60vh;">
                        @forelse($unassignedCustomers as $customer)
                            <div class="bg-rutx-surface border border-rutx-border rounded-[var(--rutx-radius-base)] p-3 shadow-[var(--rutx-shadow-sm)]">
                                <div class="font-medium text-sm text-rutx-text-base line-clamp-1" title="{{ $customer['name'] ?? '' }}">
                                    {{ $customer['name'] ?? 'Cliente Desconocido' }}
                                </div>
                                <div class="text-xs text-rutx-text-muted mt-1">
                                    {{ $customer['address'] ?? 'Sin dirección' }}
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4 text-xs text-rutx-text-muted">No hay clientes pendientes</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
