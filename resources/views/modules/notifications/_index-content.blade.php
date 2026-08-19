{{--
    Contenido de Notificaciones — bandeja y emisión (render del componente
    Livewire). Anatomía estándar: breadcrumb → H1 → formulario de envío →
    bandeja con estados vacío/error y paginación desde meta. La emisión usa
    Idempotency-Key (servicio); el botón se deshabilita durante el envío para
    evitar doble clic (la clave también lo garantiza en servidor).
--}}
<div>
    <x-breadcrumb />

    <x-page-header
        title="Notificaciones"
        subtitle="Bandeja de avisos y emisión a vendedores, rutas o zonas."
    />

    {{-- Emisión de avisos --}}
    <div class="bg-rutx-surface border border-rutx-border rounded-lg p-4 mb-6 shadow-[var(--rutx-shadow-base)]">
        <h2 class="text-base font-semibold text-rutx-primary mb-4">Emitir aviso</h2>

        <form wire:submit="enviar" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="aviso-tipo" class="block text-xs font-semibold text-rutx-text-muted mb-1">Destino</label>
                <select
                    id="aviso-tipo"
                    wire:model="targetTypeSend"
                    aria-label="Destino del aviso"
                    class="h-[var(--rutx-height-input)] w-full border border-rutx-border rounded-lg bg-rutx-surface-grey px-3 text-sm
                           focus:ring-2 focus:ring-rutx-primary focus:border-rutx-primary outline-none transition-shadow"
                >
                    <option value="seller">Vendedor</option>
                    <option value="route">Ruta</option>
                    <option value="zone">Zona</option>
                </select>
            </div>

            <div>
                <label for="aviso-ids" class="block text-xs font-semibold text-rutx-text-muted mb-1">Destinatarios (IDs)</label>
                <input
                    id="aviso-ids"
                    type="text"
                    wire:model="targetIdsText"
                    placeholder="1, 2, 3 (máx. 50)"
                    class="h-[var(--rutx-height-input)] w-full border border-rutx-border rounded-lg bg-rutx-surface-grey px-3 text-sm
                           focus:ring-2 focus:ring-rutx-primary focus:border-rutx-primary outline-none transition-shadow"
                >
            </div>

            <div>
                <label for="aviso-prioridad" class="block text-xs font-semibold text-rutx-text-muted mb-1">Prioridad</label>
                <select
                    id="aviso-prioridad"
                    wire:model="priority"
                    aria-label="Prioridad del aviso"
                    class="h-[var(--rutx-height-input)] w-full border border-rutx-border rounded-lg bg-rutx-surface-grey px-3 text-sm
                           focus:ring-2 focus:ring-rutx-primary focus:border-rutx-primary outline-none transition-shadow"
                >
                    <option value="normal">Normal</option>
                    <option value="alta">Alta</option>
                </select>
            </div>

            <div class="md:col-span-2">
                <label for="aviso-titulo" class="block text-xs font-semibold text-rutx-text-muted mb-1">Título</label>
                <input
                    id="aviso-titulo"
                    type="text"
                    wire:model="title"
                    maxlength="200"
                    placeholder="Título del aviso (máx. 200 caracteres)"
                    class="h-[var(--rutx-height-input)] w-full border border-rutx-border rounded-lg bg-rutx-surface-grey px-3 text-sm
                           focus:ring-2 focus:ring-rutx-primary focus:border-rutx-primary outline-none transition-shadow"
                >
            </div>

            <div class="md:col-span-2">
                <label for="aviso-cuerpo" class="block text-xs font-semibold text-rutx-text-muted mb-1">Mensaje</label>
                <textarea
                    id="aviso-cuerpo"
                    wire:model="body"
                    maxlength="1000"
                    rows="2"
                    placeholder="Mensaje del aviso (máx. 1,000 caracteres)"
                    class="w-full border border-rutx-border rounded-lg bg-rutx-surface-grey px-3 py-2 text-sm
                           focus:ring-2 focus:ring-rutx-primary focus:border-rutx-primary outline-none transition-shadow"
                ></textarea>
            </div>

            <div class="md:col-span-4 flex justify-end">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="enviar"
                    class="h-[var(--rutx-height-button)] px-6 rounded-xl bg-rutx-accent text-white
                           hover:bg-rutx-accent-hover transition-colors font-medium text-sm disabled:opacity-40 disabled:cursor-not-allowed
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rutx-accent"
                >
                    <span wire:loading.remove wire:target="enviar">Emitir aviso</span>
                    <span wire:loading wire:target="enviar">Emitiendo…</span>
                </button>
            </div>
        </form>
    </div>

    {{-- Bandeja --}}
    <div class="bg-rutx-surface border border-rutx-border rounded-lg p-4 mb-6 shadow-[var(--rutx-shadow-base)]">
        <h2 class="text-base font-semibold text-rutx-primary mb-4">Bandeja</h2>

        <form wire:submit="consultar">
            <x-filter-bar>
                <div>
                    <label for="bandeja-estatus" class="block text-xs font-semibold text-rutx-text-muted mb-1">Estado</label>
                    <select
                        id="bandeja-estatus"
                        wire:model="status"
                        aria-label="Estado del aviso"
                        class="h-[var(--rutx-height-input)] border border-rutx-border rounded-lg bg-rutx-surface-grey px-3 text-sm
                               focus:ring-2 focus:ring-rutx-primary focus:border-rutx-primary outline-none transition-shadow"
                    >
                        <option value="">Todos</option>
                        <option value="active">Activos</option>
                        <option value="read">Leídos</option>
                        <option value="archived">Archivados</option>
                    </select>
                </div>

                <div>
                    <label for="bandeja-tipo" class="block text-xs font-semibold text-rutx-text-muted mb-1">Destino</label>
                    <select
                        id="bandeja-tipo"
                        wire:model="targetType"
                        aria-label="Tipo de destino"
                        class="h-[var(--rutx-height-input)] border border-rutx-border rounded-lg bg-rutx-surface-grey px-3 text-sm
                               focus:ring-2 focus:ring-rutx-primary focus:border-rutx-primary outline-none transition-shadow"
                    >
                        <option value="">Todos</option>
                        <option value="seller">Vendedor</option>
                        <option value="route">Ruta</option>
                        <option value="zone">Zona</option>
                    </select>
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
            @if ($this->error !== null)
                <div class="bg-rutx-surface border border-rutx-border rounded-lg p-8 shadow-[var(--rutx-shadow-base)] text-center">
                    <p class="text-sm font-semibold text-rutx-text">No se pudo consultar la bandeja</p>
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
                        Consultando avisos…
                    </div>
                </div>

                <div wire:loading.remove wire:target="consultar, irAPagina">
                    <x-data-table :headers="['Aviso', 'Destino', 'Prioridad', 'Estado', 'Emisor', 'Fecha']" :items="$this->rows">
                        <x-slot:row>
                            @foreach ($this->rows as $row)
                                <tr class="border-b border-rutx-border hover:bg-rutx-secondary/10 transition-colors">
                                    <td class="px-4 py-3 text-sm">
                                        <span class="font-medium text-rutx-text">{{ $row['title'] ?? '—' }}</span>
                                        <p class="text-xs text-rutx-text-muted">{{ $row['body'] ?? '' }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-rutx-text-muted">
                                        #{{ $row['targetId'] ?? '—' }} · {{ $row['targetType'] ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <x-status-badge
                                            :status="$row['priority'] === 'alta' ? 'warning' : 'unknown'"
                                            :label="$row['priority'] === 'alta' ? 'Alta' : 'Normal'"
                                        />
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <x-status-badge
                                            :status="$row['status'] === 'active' ? 'success' : 'neutral'"
                                            :label="$row['status'] === 'active' ? 'Activo' : ($row['status'] ?? '—')"
                                        />
                                    </td>
                                    <td class="px-4 py-3 text-sm text-rutx-text-muted">{{ $row['senderUsername'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-rutx-text-muted whitespace-nowrap">{{ $row['createdAt'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </x-slot:row>

                        <x-slot:empty>
                            <div class="flex flex-col items-center justify-center">
                                <span class="text-sm">Sin avisos para los filtros seleccionados</span>
                            </div>
                        </x-slot:empty>

                        <x-slot:pagination>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-rutx-text-muted">
                                    Página {{ $this->meta['page'] ?? 1 }} de {{ $this->meta['last_page'] ?? 1 }}
                                    · {{ $this->meta['total'] ?? 0 }} avisos
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
</div>