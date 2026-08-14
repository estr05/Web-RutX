{{--
    Contenido de Ruta · Jornada del Día (render del componente Livewire).
    Anatomía estándar: breadcrumb → H1 → filtros → tabla-timeline con
    <x-status-badge> (estado siempre con color + etiqueta, guidelines §5.2).
    Sin datos ni cálculos: todo viene de las propiedades del componente
    ($routes, $rows) y los montos solo vía <x-currency>.
--}}
<div wire:poll.{{ $pollInterval }}s>
    <x-breadcrumb />

    <x-page-header
        title="Jornada del Día"
        subtitle="Línea temporal de visitas de la ruta seleccionada."
    />

    <form wire:submit="consultar">
        <x-filter-bar>
            <div>
                <label for="jornada-ruta" class="block text-xs font-semibold text-rutx-text-muted mb-1">Ruta</label>
                <select
                    id="jornada-ruta"
                    wire:model.live="routeId"
                    aria-label="Ruta"
                    class="h-[var(--rutx-height-input)] border border-rutx-border rounded-lg bg-rutx-surface-grey px-3 text-sm
                           focus:ring-2 focus:ring-rutx-primary focus:border-rutx-primary outline-none transition-shadow"
                >
                    <option value="">Selecciona una ruta</option>
                    @foreach ($this->routes as $route)
                        <option value="{{ $route['route_id'] }}">{{ $route['route_name'] }}</option>
                    @endforeach
                </select>
            </div>

            <x-slot:actions>
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
        <x-data-table :headers="['Hora', 'Cliente', 'Tipo', 'Duración', 'Monto']" :items="$this->rows">
            <x-slot:row>
                @foreach ($this->rows as $row)
                    <tr class="border-b border-rutx-border hover:bg-rutx-secondary/10 transition-colors">
                        <td class="px-4 py-3 text-sm text-rutx-text-muted whitespace-nowrap">{{ $row['at'] ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-rutx-text">{{ $row['customer_name'] }}</td>
                        <td class="px-4 py-3 text-sm">
                            <x-status-badge
                                :status="$row['type'] === 'visit' ? 'success' : 'warning'"
                                :label="$row['type_label']"
                            />
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-rutx-text">
                            {{ $row['duration_minutes'] === null ? '—' : $row['duration_minutes'].' min' }}
                        </td>
                        <td class="px-4 py-3 text-sm text-right">
                            @if ($row['amount'] === null)
                                <span class="text-rutx-text-muted">—</span>
                            @else
                                <x-currency :amount="$row['amount']" />
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-slot:row>
        </x-data-table>
    </div>
</div>
