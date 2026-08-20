<div>
    <x-filter-bar class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-rutx-text-base mb-1">Buscar (Folio/Cliente)</label>
                <input type="text" wire:model.live.debounce.500ms="filters.search" placeholder="Folio o nombre..." class="block w-full rounded-[var(--rutx-radius-base)] border-rutx-border shadow-[var(--rutx-shadow-sm)] focus:border-rutx-primary focus:ring-rutx-primary sm:text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-rutx-text-base mb-1">Desde</label>
                <input type="date" wire:model.live.debounce.500ms="filters.date_from" class="block w-full rounded-[var(--rutx-radius-base)] border-rutx-border shadow-[var(--rutx-shadow-sm)] focus:border-rutx-primary focus:ring-rutx-primary sm:text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-rutx-text-base mb-1">Hasta</label>
                <input type="date" wire:model.live.debounce.500ms="filters.date_to" class="block w-full rounded-[var(--rutx-radius-base)] border-rutx-border shadow-[var(--rutx-shadow-sm)] focus:border-rutx-primary focus:ring-rutx-primary sm:text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-rutx-text-base mb-1">Estado</label>
                <select wire:model.live="filters.status" class="block w-full rounded-[var(--rutx-radius-base)] border-rutx-border shadow-[var(--rutx-shadow-sm)] focus:border-rutx-primary focus:ring-rutx-primary sm:text-sm">
                    <option value="">Todos</option>
                    <option value="completed">Completadas</option>
                    <option value="canceled">Canceladas</option>
                </select>
            </div>
        </div>
    </x-filter-bar>

    @if($errorMessage)
        <div class="mb-6">
            <x-alert type="error" :message="$errorMessage" />
        </div>
    @endif

    <div class="relative min-h-[300px]">
        <div wire:loading wire:target="loadSales, filters" class="absolute inset-0 z-10 bg-rutx-backdrop/50 backdrop-blur-sm flex items-center justify-center rounded-[var(--rutx-radius-lg)]">
            <x-loading-state message="Cargando transacciones..." />
        </div>

        <div class="bg-rutx-surface rounded-[var(--rutx-radius-lg)] shadow-[var(--rutx-shadow-sm)] overflow-hidden border border-rutx-border">
            <x-data-table :headers="['Folio / Fecha', 'Cliente', 'Vendedor', 'Estado', 'Total', 'Acciones']" :items="$sales">
                <x-slot name="row">
                    @foreach($sales as $sale)
                        <tr class="hover:bg-rutx-surface-hover transition-colors">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm font-medium text-rutx-text-base">{{ $sale['folio'] ?? 'N/A' }}</div>
                                <div class="text-xs text-rutx-text-muted">{{ isset($sale['date']) ? \Carbon\Carbon::parse($sale['date'])->format('d M, Y') : '' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm text-rutx-text-base line-clamp-1" title="{{ $sale['customer_name'] ?? '' }}">{{ $sale['customer_name'] ?? 'Desconocido' }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm text-rutx-text-base">{{ $sale['seller_name'] ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if(($sale['status'] ?? '') === 'canceled')
                                    <x-status-badge status="error" label="Cancelada" />
                                @elseif(($sale['status'] ?? '') === 'pending_cancellation')
                                    <x-status-badge status="warning" label="Pdte. Cancelar" />
                                @else
                                    <x-status-badge status="success" label="Completada" />
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium text-rutx-text-base">
                                <x-currency :amount="$sale['total_amount'] ?? 0" />
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                <a href="{{ route('venta.detalle', ['id' => $sale['id']]) }}" class="text-rutx-primary hover:text-rutx-primary-dark inline-flex items-center">
                                    Ver Detalle
                                    <svg class="ml-1 w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </x-slot>
                <x-slot name="empty">
                    Sin registros. No se encontraron ventas para los filtros actuales.
                </x-slot>
            </x-data-table>

            @if(isset($meta['total']) && $meta['total'] > 0)
            <div class="bg-rutx-bg px-4 py-3 border-t border-rutx-border sm:px-6 flex items-center justify-between">
                <div class="hidden sm:block text-sm text-rutx-text-base">
                    Mostrando página <span class="font-medium">{{ $meta['page'] ?? 1 }}</span> de <span class="font-medium">{{ $meta['last_page'] ?? 1 }}</span>
                    ({{ $meta['total'] ?? 0 }} registros)
                </div>
                <div class="flex-1 flex justify-between sm:justify-end gap-2">
                    <button wire:click="setPage({{ max(1, ($meta['page'] ?? 1) - 1) }})" @disabled(($meta['page'] ?? 1) <= 1) class="relative inline-flex items-center px-4 py-2 border border-rutx-border text-sm font-medium rounded-[var(--rutx-radius-base)] text-rutx-text-base bg-rutx-surface hover:bg-rutx-bg disabled:opacity-50 disabled:cursor-not-allowed">
                        Anterior
                    </button>
                    <button wire:click="setPage({{ min($meta['last_page'] ?? 1, ($meta['page'] ?? 1) + 1) }})" @disabled(($meta['page'] ?? 1) >= ($meta['last_page'] ?? 1)) class="relative inline-flex items-center px-4 py-2 border border-rutx-border text-sm font-medium rounded-[var(--rutx-radius-base)] text-rutx-text-base bg-rutx-surface hover:bg-rutx-bg disabled:opacity-50 disabled:cursor-not-allowed">
                        Siguiente
                    </button>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
