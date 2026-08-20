<div>
    @if($errorMessage)
        <div class="mb-6">
            <x-alert type="error" :message="$errorMessage" />
        </div>
    @endif

    <div class="relative">
        <div wire:loading wire:target="loadSale" class="absolute inset-0 z-10 bg-rutx-surface/50 backdrop-blur-sm flex items-center justify-center rounded-[var(--rutx-radius-lg)]">
            <x-loading-state message="Cargando detalle..." />
        </div>

        @if(!empty($sale))
            <div class="bg-rutx-surface shadow overflow-hidden sm:rounded-[var(--rutx-radius-lg)] mb-6 border border-rutx-border">
                <div class="px-4 py-5 sm:px-6 flex justify-between items-center bg-rutx-bg">
                    <div>
                        <h3 class="text-lg leading-6 font-medium text-rutx-text-base">
                            Ticket #{{ $sale['folio'] ?? $sale['id'] }}
                        </h3>
                        <p class="mt-1 max-w-2xl text-sm text-rutx-text-muted">
                            {{ isset($sale['date']) ? \Carbon\Carbon::parse($sale['date'])->format('d de F Y, H:i') : '' }}
                        </p>
                    </div>
                    <div>
                        @if(($sale['status'] ?? '') === 'canceled')
                            <x-status-badge status="error" label="Cancelada" />
                        @elseif(($sale['status'] ?? '') === 'pending_cancellation')
                            <x-status-badge status="warning" label="Pendiente Cancelación" />
                        @else
                            <x-status-badge status="success" label="Completada" />
                        @endif
                    </div>
                </div>
                <div class="border-t border-rutx-border px-4 py-5 sm:p-0">
                    <dl class="sm:divide-y sm:divide-rutx-border">
                        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-rutx-text-muted">Cliente</dt>
                            <dd class="mt-1 text-sm text-rutx-text-base sm:mt-0 sm:col-span-2">{{ $sale['customer_name'] ?? 'N/A' }}</dd>
                        </div>
                        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-rutx-text-muted">Vendedor</dt>
                            <dd class="mt-1 text-sm text-rutx-text-base sm:mt-0 sm:col-span-2">{{ $sale['seller_name'] ?? 'N/A' }}</dd>
                        </div>
                        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-rutx-text-muted">Monto Total</dt>
                            <dd class="mt-1 text-lg font-bold text-rutx-text-base sm:mt-0 sm:col-span-2">
                                <x-currency :amount="$sale['total_amount'] ?? 0" />
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Acciones -->
            @if(($sale['status'] ?? '') === 'completed')
                @can('sales.cancel')
                    <div class="flex justify-end">
                        <button type="button" wire:click="confirmCancellation" class="inline-flex items-center px-4 py-2 border border-transparent shadow-[var(--rutx-shadow-sm)] text-sm font-medium rounded-[var(--rutx-radius-base)] text-white bg-rutx-error hover:bg-rutx-error-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rutx-error">
                            Solicitar Cancelación
                        </button>
                    </div>
                @endcan
            @endif
        @endif
    </div>

    <!-- Modal de Cancelación (Guidelines) -->
    @can('sales.cancel')
        <x-modal wire:model="confirmingCancellation" maxWidth="md">
            <div class="px-6 py-4">
                <div class="text-lg font-medium text-rutx-text-base">
                    Confirmar Solicitud de Cancelación
                </div>

                <div class="mt-4 text-sm text-rutx-text-muted">
                    <p class="mb-4">Por favor, ingrese el motivo de la cancelación. Esta acción quedará registrada para auditoría.</p>

                    <div class="space-y-1">
                        <label for="reason" class="block text-sm font-medium text-rutx-text-muted">Motivo (mín. 10 caracteres)</label>
                        <textarea
                            id="reason"
                            wire:model="cancellationReason"
                            rows="3"
                            class="block w-full rounded-[var(--rutx-radius-base)] border-rutx-border shadow-[var(--rutx-shadow-sm)] focus:border-rutx-error focus:ring-rutx-error sm:text-sm"
                            placeholder="Ej. El cliente rechazó la mercancía..."></textarea>

                        @error('cancellationReason') <span class="text-rutx-error text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="flex flex-row justify-end px-6 py-4 bg-rutx-bg text-right gap-2 rounded-b-lg">
                <button wire:click="$toggle('confirmingCancellation')" type="button" class="inline-flex justify-center px-4 py-2 text-sm font-medium text-rutx-text-muted bg-rutx-surface border border-rutx-border rounded-[var(--rutx-radius-base)] shadow-[var(--rutx-shadow-sm)] hover:bg-rutx-surface-hover focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rutx-border">
                    Cerrar
                </button>
                <button wire:click="cancelSale" wire:loading.attr="disabled" type="button" class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-rutx-error border border-transparent rounded-[var(--rutx-radius-base)] shadow-[var(--rutx-shadow-sm)] hover:bg-rutx-error-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rutx-error disabled:opacity-50">
                    <span wire:loading.remove wire:target="cancelSale">Confirmar Cancelación</span>
                    <span wire:loading wire:target="cancelSale">Procesando...</span>
                </button>
            </div>
        </x-modal>
    @endcan
</div>
