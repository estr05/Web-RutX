<div>
    <x-filter-bar class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Buscar (Folio/Cliente)</label>
                <input type="text" wire:model.live.debounce.500ms="filters.search" placeholder="Folio o nombre..." class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
                <input type="date" wire:model.live.debounce.500ms="filters.date_from" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
                <input type="date" wire:model.live.debounce.500ms="filters.date_to" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                <select wire:model.live="filters.status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
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
        <div wire:loading wire:target="loadSales, filters" class="absolute inset-0 z-10 bg-white/50 backdrop-blur-sm flex items-center justify-center rounded-lg">
            <x-loading-state message="Cargando transacciones..." />
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Folio / Fecha</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vendedor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($sales as $sale)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $sale['folio'] ?? 'N/A' }}</div>
                                <div class="text-sm text-gray-500">{{ isset($sale['date']) ? \Carbon\Carbon::parse($sale['date'])->format('d M, Y') : '' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900 line-clamp-1" title="{{ $sale['customer_name'] ?? '' }}">{{ $sale['customer_name'] ?? 'Desconocido' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $sale['seller_name'] ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if(($sale['status'] ?? '') === 'canceled')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Cancelada</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Completada</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900">
                                <x-currency :amount="$sale['total_amount'] ?? 0" />
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="{{ route('venta.detalle', ['id' => $sale['id']]) }}" class="text-blue-600 hover:text-blue-900 inline-flex items-center">
                                    Ver Detalle
                                    <svg class="ml-1 w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                <h3 class="mt-2 text-sm font-semibold text-gray-900">Sin registros</h3>
                                <p class="mt-1 text-sm text-gray-500">No se encontraron ventas para los filtros actuales.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            
            @if(isset($meta['total']) && $meta['total'] > 0)
            <div class="bg-gray-50 px-4 py-3 border-t border-gray-200 sm:px-6 flex items-center justify-between">
                <div class="hidden sm:block text-sm text-gray-700">
                    Mostrando página <span class="font-medium">{{ $meta['page'] ?? 1 }}</span> de <span class="font-medium">{{ $meta['last_page'] ?? 1 }}</span> 
                    ({{ $meta['total'] ?? 0 }} registros)
                </div>
                <div class="flex-1 flex justify-between sm:justify-end gap-2">
                    <button wire:click="setPage({{ max(1, ($meta['page'] ?? 1) - 1) }})" @disabled(($meta['page'] ?? 1) <= 1) class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                        Anterior
                    </button>
                    <button wire:click="setPage({{ min($meta['last_page'] ?? 1, ($meta['page'] ?? 1) + 1) }})" @disabled(($meta['page'] ?? 1) >= ($meta['last_page'] ?? 1)) class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                        Siguiente
                    </button>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
