{{--
    Contenido de Venta · Reportes Globales (render del componente Livewire).
    Anatomía estándar: breadcrumb → H1 → filtros → KPIs → gráfica → tabla.
    Comparativa YoY (periodo actual vs. mismo periodo del año anterior).
    Sin datos ni cálculos: todo viene de las propiedades del componente
    ($kpi, $series, $rows) y los montos solo vía <x-currency> o
    Money::format (guidelines §2.1, §5.2).
--}}
<div wire:poll.{{ $pollInterval }}s>
    <x-breadcrumb />

    <x-page-header
        title="Reportes Globales"
        subtitle="Comparativa del periodo contra el mismo periodo del año anterior."
    />

    <form wire:submit="consultar">
        <x-filter-bar>
            <x-date-range :range="$range" />

            <div>
                <label for="globales-date-from" class="block text-xs font-semibold text-rutx-text-muted mb-1">Desde</label>
                <input
                    id="globales-date-from"
                    type="date"
                    wire:model="dateFrom"
                    class="h-[var(--rutx-height-input)] border border-rutx-border rounded-lg bg-rutx-surface-grey px-3 text-sm
                           focus:ring-2 focus:ring-rutx-primary focus:border-rutx-primary outline-none transition-shadow"
                />
            </div>

            <div>
                <label for="globales-date-to" class="block text-xs font-semibold text-rutx-text-muted mb-1">Hasta</label>
                <input
                    id="globales-date-to"
                    type="date"
                    wire:model="dateTo"
                    class="h-[var(--rutx-height-input)] border border-rutx-border rounded-lg bg-rutx-surface-grey px-3 text-sm
                           focus:ring-2 focus:ring-rutx-primary focus:border-rutx-primary outline-none transition-shadow"
                />
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

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        @foreach ($this->kpi as $card)
            <x-kpi-card
                :title="$card['label']"
                :value="\App\Support\Money::format((float) $card['value'])"
                :delta="$card['delta']"
                :status="$card['status']"
            />
        @endforeach
    </div>

    <x-chart
        :labels="$this->series['labels'] ?? []"
        :datasets="$this->series['datasets'] ?? []"
        summary="Comparativa de ventas contra el año anterior"
    />

    <div class="mt-6">
        <x-data-table :headers="['Periodo', 'Periodo actual', 'Año anterior', 'Variación']" :items="$this->rows">
            <x-slot:row>
                @foreach ($this->rows as $row)
                    <tr class="border-b border-rutx-border hover:bg-rutx-secondary/10 transition-colors">
                        <td class="px-4 py-3 text-sm text-rutx-text">{{ $row['period'] }}</td>
                        <td class="px-4 py-3 text-sm text-right"><x-currency :amount="$row['current_amount']" /></td>
                        <td class="px-4 py-3 text-sm text-right"><x-currency :amount="$row['previous_amount']" /></td>
                        <td class="px-4 py-3 text-sm text-right">
                            @if ($row['yoy_delta'] === null)
                                <span class="text-rutx-text-muted">—</span>
                            @else
                                <span class="font-mono font-semibold {{ $row['yoy_delta'] >= 0 ? 'text-rutx-status-success' : 'text-rutx-status-error' }}">
                                    {{ $row['yoy_delta'] >= 0 ? '+' : '' }}{{ number_format($row['yoy_delta'], 1) }}%
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-slot:row>
        </x-data-table>
    </div>
</div>
