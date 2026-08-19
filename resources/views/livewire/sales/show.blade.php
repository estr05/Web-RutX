<div>
    @if($errorMessage)
        <div class="mb-6">
            <x-alert type="error" :message="$errorMessage" />
        </div>
    @endif

    <div class="relative">
        <div wire:loading wire:target="loadSale" class="absolute inset-0 z-10 bg-white/50 backdrop-blur-sm flex items-center justify-center rounded-lg">
            <x-loading-state message="Cargando detalle..." />
        </div>

        @if(!empty($sale))
            <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6 border border-gray-200">
                <div class="px-4 py-5 sm:px-6 flex justify-between items-center bg-gray-50">
                    <div>
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            Ticket #{{ $sale['folio'] ?? $sale['id'] }}
                        </h3>
                        <p class="mt-1 max-w-2xl text-sm text-gray-500">
                            {{ isset($sale['date']) ? \Carbon\Carbon::parse($sale['date'])->format('d de F Y, H:i') : '' }}
                        </p>
                    </div>
                    <div>
                        @if(($sale['status'] ?? '') === 'canceled')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                Cancelada
                            </span>
                        @elseif(($sale['status'] ?? '') === 'pending_cancellation')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-orange-100 text-orange-800">
                                Pendiente Cancelación
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                Completada
                            </span>
                        @endif
                    </div>
                </div>
                
                <div class="border-t border-gray-200 px-4 py-5 sm:p-0">
                    <dl class="sm:divide-y sm:divide-gray-200">
                        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Cliente</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $sale['customer_name'] ?? 'N/A' }}</dd>
                        </div>
                        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Vendedor</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $sale['seller_name'] ?? 'N/A' }}</dd>
                        </div>
                        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Monto Total</dt>
                            <dd class="mt-1 text-lg font-bold text-gray-900 sm:mt-0 sm:col-span-2">
                                <x-currency :amount="$sale['total_amount'] ?? 0" />
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Acciones -->
            @if(($sale['status'] ?? '') === 'completed')
                <div class="flex justify-end">
                    <button type="button" wire:click="confirmCancellation" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        Solicitar Cancelación
                    </button>
                </div>
            @endif
        @endif
    </div>

    <!-- Modal de Cancelación (Guidelines) -->
    <x-modal wire:model="confirmingCancellation" maxWidth="md">
        <div class="px-6 py-4">
            <div class="text-lg font-medium text-gray-900">
                Confirmar Solicitud de Cancelación
            </div>

            <div class="mt-4 text-sm text-gray-600">
                <p class="mb-4">Por favor, ingrese el motivo de la cancelación. Esta acción quedará registrada para auditoría.</p>
                
                <div class="space-y-1">
                    <label for="reason" class="block text-sm font-medium text-gray-700">Motivo (mín. 10 caracteres)</label>
                    <textarea 
                        id="reason" 
                        wire:model="cancellationReason"
                        rows="3" 
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm"
                        placeholder="Ej. El cliente rechazó la mercancía..."></textarea>
                    
                    @error('cancellationReason') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <div class="flex flex-row justify-end px-6 py-4 bg-gray-100 text-right gap-2 rounded-b-lg">
            <button wire:click="$toggle('confirmingCancellation')" type="button" class="inline-flex justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-200">
                Cerrar
            </button>
            <button wire:click="cancelSale" wire:loading.attr="disabled" type="button" class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50">
                <span wire:loading.remove wire:target="cancelSale">Confirmar Cancelación</span>
                <span wire:loading wire:target="cancelSale">Procesando...</span>
            </button>
        </div>
    </x-modal>
</div>
