{{--
    Contenido de Inventario por Ruta — piloto (render del componente Livewire).
    Anatomía estándar: breadcrumb → H1 → filtros → tabla con estados
    vacío/error honestos y paginación desde meta. Sin datos ni cálculos:
    todo viene de las propiedades del componente ($rows, $meta, $error).
--}}
<div>
    <x-breadcrumb />

    <x-page-header
        title="Inventario por Ruta"
        subtitle="Piloto de existencias por ruta (≡ vendedor). Lectura estricta: no edita existencias."
    />

    <form wire:submit="consultar">
        <x-filter-bar>
            <div>
                <label for="inventario-ruta" class="block text-xs font-semibold text-rutx-text-muted mb-1">Ruta *</label>
                <input
                    id="inventario-ruta"
                    type="number"
                    min="1"
                    required
                    wire:model="routeId"
                    placeholder="ID de ruta (vendedor)"
                    class="h-[var(--rutx-height-input)] border border-rutx-border rounded-lg bg-rutx-surface-grey px-3 text-sm
                           focus:ring-2 focus:ring-rutx-primary focus:border-rutx-primary outline-none transition-shadow"
                >
            </div>

            <div>
                <label for="inventario-periodo" class="block text-xs font-semibold text-rutx-text-muted mb-1">Periodo</label>
                <input
                    id="inventario-periodo"
                    type="month"
                    wire:model="asOf"
                    aria-label="Periodo (YYYY-MM)"
                    class="h-[var(--rutx-height-input)] border border-rutx-border rounded-lg bg-rutx-surface-grey px-3 text-sm
                           focus:ring-2 focus:ring-rutx-primary focus:border-rutx-primary outline-none transition-shadow"
                >
            </div>

            <div>
                <label for="inventario-zona" class="block text-xs font-semibold text-rutx-text-muted mb-1">Zona</label>
                <input
                    id="inventario-zona"
                    type="number"
                    min="1"
                    wire:model="zoneId"
                    placeholder="ID de zona"
                    class="h-[var(--rutx-height-input)] border border-rutx-border rounded-lg bg-rutx-surface-grey px-3 text-sm
                           focus:ring-2 focus:ring-rutx-primary focus:border-rutx-primary outline-none transition-shadow"
                >
            </div>

            <x-slot:actions>
                <button
                    type="button"
                    wire:click="limpiar"
                    class="h-[var(--rutx-height-button)] px-6 rounded-xl border border-rutx-accent text-rutx-accent
                           hover:bg-rutx-accent-light transition-colors font-medium text-sm
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rutx-accent"
                >
                    Limpiar
                </button>
                <button
                    type="submit"
                    class="h-[var(--rutx-height-button)] px-6 rounded-xl bg-rutx-accent text-white
                           hover:bg-rutx-accent-hover transition-colors font-medium text-sm
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rutx-accent"
                >
                    Consultar
                </button>
            </x-slot:actions>
        </x-filter-bar>
    </form>

    <div class="mt-6">
        @if (! $this->consulted)
            <div class="bg-rutx-surface border border-rutx-border rounded-lg p-8 shadow-[var(--rutx-shadow-base)] text-center">
                <p class="text-sm text-rutx-text-muted">Selecciona una ruta para consultar el piloto de inventario.</p>
            </div>
        @elseif ($this->error !== null)
            <div class="bg-rutx-surface border border-rutx-border rounded-lg p-8 shadow-[var(--rutx-shadow-base)] text-center">
                <p class="text-sm font-semibold text-rutx-text">No se pudo consultar el inventario</p>
                <p class="text-sm text-rutx-text-muted mt-1">{{ $this->error }}</p>
                <button
                    type="button"
                    wire:click="consultar"
                    class="mt-4 h-[var(--rutx-height-button)] px-6 rounded-xl bg-rutx-accent text-white
                           hover:bg-rutx-accent-hover transition-colors font-medium text-sm
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rutx-accent"
                >
                    Reintentar
                </button>
            </div>
        @else
            <div wire:loading.delay wire:target="consultar, irAPagina">
                <div class="bg-rutx-surface border border-rutx-border rounded-lg p-8 shadow-[var(--rutx-shadow-base)] text-center text-sm text-rutx-text-muted">
                    Consultando inventario…
                </div>
            </div>

            <div wire:loading.remove wire:target="consultar, irAPagina">
                <x-data-table :headers="['Artículo', 'Unidad', 'Disponible', 'Vendido', 'Diferencia']" :items="$this->rows">
                    <x-slot:row>
                        @foreach ($this->rows as $row)
                            <tr class="border-b border-rutx-border hover:bg-rutx-secondary/10 transition-colors">
                                <td class="px-4 py-3 text-sm font-medium text-rutx-text">
                                    {{ $row['productCode'] ?? $row['productId'] ?? '—' }} · {{ $row['productName'] ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-rutx-text-muted">{{ $row['unit'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-right text-rutx-text">{{ $row['available'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-right text-rutx-text">{{ $row['sold'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-right text-rutx-text-muted">{{ $row['difference'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </x-slot:row>

                    <x-slot:empty>
                        <div class="flex flex-col items-center justify-center">
                            <span class="text-sm">Sin movimientos para la ruta y periodo seleccionados</span>
                        </div>
                    </x-slot:empty>

                    <x-slot:pagination>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-rutx-text-muted">
                                Página {{ $this->meta['page'] ?? 1 }} de {{ $this->meta['last_page'] ?? 1 }}
                                · {{ $this->meta['total'] ?? 0 }} artículos
                            </span>
                            <div class="flex items-center space-x-3">
                                <button
                                    type="button"
                                    wire:click="irAPagina({{ ($this->meta['page'] ?? 1) - 1 }})"
                                    @disabled(($this->meta['page'] ?? 1) <= 1)
                                    class="px-4 h-[var(--rutx-height-button)] rounded-lg border border-rutx-border text-rutx-text
                                           hover:bg-rutx-secondary/10 transition-colors font-medium text-sm disabled:opacity-40 disabled:cursor-not-allowed"
                                >
                                    Anterior
                                </button>
                                <button
                                    type="button"
                                    wire:click="irAPagina({{ ($this->meta['page'] ?? 1) + 1 }})"
                                    @disabled(($this->meta['page'] ?? 1) >= ($this->meta['last_page'] ?? 1))
                                    class="px-4 h-[var(--rutx-height-button)] rounded-lg border border-rutx-border text-rutx-text
                                           hover:bg-rutx-secondary/10 transition-colors font-medium text-sm disabled:opacity-40 disabled:cursor-not-allowed"
                                >
                                    Siguiente
                                </button>
                            </div>
                        </div>
                    </x-slot:pagination>
                </x-data-table>
            </div>
        @endif
    </div>
</div>