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
            <x-date-input id="date-from" label="Desde" wire:model.live.debounce.500ms="filters.date_from" />
            <x-date-input id="date-to" label="Hasta" wire:model.live.debounce.500ms="filters.date_to" />
        </div>

        <x-slot:actions>
            @can('agendas.assign')
                <button
                    type="button"
                    wire:click="applyBatch"
                    wire:loading.attr="disabled"
                    @disabled(empty($pendingBatch))
                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-[var(--rutx-shadow-sm)] text-sm font-medium rounded-[var(--rutx-radius-base)] text-white {{ count($pendingBatch) > 0 ? 'bg-rutx-warning hover:bg-rutx-warning-dark' : 'bg-rutx-primary hover:bg-rutx-primary-dark' }} focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rutx-primary disabled:opacity-50"
                >
                    Guardar{{ count($pendingBatch) > 0 ? ' ('.count($pendingBatch).')' : '' }}
                </button>
            @endcan
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
                            <x-status-badge status="success" label="{{ $day['total_customers'] ?? 0 }}" />
                        </div>

                        <div class="flex-1 p-2 bg-rutx-bg/50 space-y-2 overflow-y-auto" style="max-height: 60vh;">
                            @forelse($day['sellers'] ?? [] as $seller)
                                <div class="bg-rutx-surface border border-rutx-border rounded-[var(--rutx-radius-base)] p-3 shadow-[var(--rutx-shadow-sm)] hover:shadow transition-shadow">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="font-medium text-sm text-rutx-text-base line-clamp-1" title="{{ $seller['seller_name'] ?? $seller['name'] ?? '' }}">
                                            {{ $seller['seller_name'] ?? $seller['name'] ?? 'Vendedor' }}
                                        </div>
                                    </div>
                                    <div class="flex items-center text-xs text-rutx-text-muted mb-1">
                                        {{ $seller['route_name'] ?? 'Ruta Sin Nombre' }}
                                    </div>
                                    <div class="mt-2 flex justify-between items-center">
                                        <x-status-badge status="success" label="{{ $seller['customers_count'] ?? $seller['customer_count'] ?? 0 }} cli" />
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
                                @can('agendas.assign')
                                    <div class="mt-2">
                                        <button
                                            type="button"
                                            wire:click="openAssignModal({{ $customer['customer_id'] }})"
                                            class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-[var(--rutx-radius-base)] text-rutx-primary bg-rutx-primary/10 hover:bg-rutx-primary/20 transition-colors"
                                        >
                                            + Asignar
                                        </button>
                                    </div>
                                @endcan
                            </div>
                        @empty
                            <div class="text-center py-4 text-xs text-rutx-text-muted">No hay clientes pendientes</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Resumen de movimientos pendientes -->
    @if(!empty($pendingBatch) && count($pendingBatch) > 0)
        <div class="mt-6 bg-rutx-surface rounded-[var(--rutx-radius-lg)] shadow-[var(--rutx-shadow-sm)] border border-rutx-border overflow-hidden">
            <div class="bg-rutx-bg px-4 py-3 border-b border-rutx-border flex justify-between items-center">
                <span class="text-sm font-bold text-rutx-text-base">Movimientos Pendientes ({{ count($pendingBatch) }})</span>
                <x-status-badge status="warning" label="Sin guardar" />
            </div>
            <div class="p-3 space-y-2">
                @foreach($pendingBatch as $index => $change)
                    <div class="flex items-center justify-between bg-rutx-bg/50 rounded-[var(--rutx-radius-base)] px-3 py-2 border border-rutx-border">
                        <div class="flex items-center gap-2 text-sm text-rutx-text-base">
                            @if($change['action'] === 'assign')
                                <x-status-badge status="success" label="Asignar" />
                                <span>Cliente #{{ $change['customer_id'] }} → Vendedor #{{ $change['seller_id'] }} el {{ $change['agenda_date'] }}</span>
                            @elseif($change['action'] === 'move')
                                <x-status-badge status="warning" label="Mover" />
                                <span>Cliente #{{ $change['customer_id'] }} → Vendedor #{{ $change['seller_id'] }} el {{ $change['agenda_date'] }}</span>
                            @else
                                <x-status-badge status="error" label="Quitar" />
                                <span>Cliente #{{ $change['customer_id'] }}</span>
                            @endif
                        </div>
                        <button
                            type="button"
                            wire:click="removePendingAssignment({{ $index }})"
                            class="text-rutx-text-muted hover:text-rutx-error transition-colors p-1"
                            title="Deshacer"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Modal de Asignación -->
    <x-modal wire:model="showAssignModal" maxWidth="md">
        <div class="px-6 py-4">
            <div class="text-lg font-medium text-rutx-text-base">
                Asignar Cliente
            </div>

            <div class="mt-4 text-sm text-rutx-text-muted space-y-4">
                <p>Seleccione el vendedor y la fecha para asignar el cliente.</p>

                <div>
                    <label for="assign-seller" class="block text-sm font-medium text-rutx-text-muted mb-1">Vendedor</label>
                    <select
                        id="assign-seller"
                        wire:model="assignSellerId"
                        class="block w-full rounded-[var(--rutx-radius-base)] border-rutx-border shadow-[var(--rutx-shadow-sm)] focus:border-rutx-primary focus:ring-rutx-primary sm:text-sm"
                    >
                        <option value="">Seleccionar vendedor...</option>
                        @foreach($this->availableSellers as $seller)
                            <option value="{{ $seller['seller_id'] }}">{{ $seller['seller_name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="assign-date" class="block text-sm font-medium text-rutx-text-muted mb-1">Fecha</label>
                    <select
                        id="assign-date"
                        wire:model="assignDate"
                        class="block w-full rounded-[var(--rutx-radius-base)] border-rutx-border shadow-[var(--rutx-shadow-sm)] focus:border-rutx-primary focus:ring-rutx-primary sm:text-sm"
                    >
                        <option value="">Seleccionar fecha...</option>
                        @foreach($this->availableDates as $dateOption)
                            <option value="{{ $dateOption['date'] }}">{{ $dateOption['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="flex flex-row justify-end px-6 py-4 bg-rutx-bg text-right gap-2 rounded-b-lg">
            <button wire:click="closeAssignModal" type="button" class="inline-flex justify-center px-4 py-2 text-sm font-medium text-rutx-text-muted bg-rutx-surface border border-rutx-border rounded-[var(--rutx-radius-base)] shadow-[var(--rutx-shadow-sm)] hover:bg-rutx-surface-hover focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rutx-border">
                Cancelar
            </button>
            <button
                wire:click="submitAssignment"
                wire:loading.attr="disabled"
                type="button"
                @disabled(empty($assignSellerId) || empty($assignDate))
                class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-rutx-primary border border-transparent rounded-[var(--rutx-radius-base)] shadow-[var(--rutx-shadow-sm)] hover:bg-rutx-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rutx-primary disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="submitAssignment">Confirmar</span>
                <span wire:loading wire:target="submitAssignment">Procesando...</span>
            </button>
        </div>
    </x-modal>
</div>
