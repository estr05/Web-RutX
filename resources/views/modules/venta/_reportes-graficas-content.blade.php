{{--
    Contenido de Venta · Reportes y Gráficas (render del componente Livewire).
    Anatomía estándar: breadcrumb → H1 → filtros → KPIs → gráfica → tabla.
    Sin datos ni cálculos: todo viene de las propiedades del componente
    ($kpi, $series, $movimientos) y los montos solo vía <x-currency> o
    Money::format (guidelines §2.1, §5.2).
--}}
<div wire:poll.{{ $pollInterval }}s>
    <x-breadcrumb />

    <x-page-header
        title="Reportes y Gráficas"
        subtitle="Ventas, piezas y comparativa del período seleccionado."
    />

    <form wire:submit="consultar">
        <x-filter-bar>
            <x-date-range :range="$range" />

            <div>
                <label for="reportes-date-from" class="block text-xs font-semibold text-rutx-text-muted mb-1">Desde</label>
                <input
                    id="reportes-date-from"
                    type="date"
                    wire:model="dateFrom"
                    class="h-[var(--rutx-height-input)] border border-rutx-border rounded-lg bg-rutx-surface-grey px-3 text-sm
                           focus:ring-2 focus:ring-rutx-primary focus:border-rutx-primary outline-none transition-shadow"
                />
            </div>

            <div>
                <label for="reportes-date-to" class="block text-xs font-semibold text-rutx-text-muted mb-1">Hasta</label>
                <input
                    id="reportes-date-to"
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

    <div class="grid grid-cols-1 md:grid-cols-4 xl:grid-cols-7 gap-4 mb-6">
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
        summary="Serie de ventas del período"
    />

    <div class="mt-6">
        <x-data-table :headers="['Ruta', 'Piezas', 'Contado', 'Crédito', 'Total']" :items="$this->movimientos">
            <x-slot:row>
                @foreach ($this->movimientos as $row)
                    <tr class="border-b border-rutx-border hover:bg-rutx-secondary/10 transition-colors">
                        <td class="px-4 py-3 text-sm text-rutx-text">{{ $row['route_name'] }}</td>
                        <td class="px-4 py-3 text-sm text-right font-mono text-rutx-text">{{ $row['pieces'] }}</td>
                        <td class="px-4 py-3 text-sm text-right"><x-currency :amount="$row['cash_amount']" /></td>
                        <td class="px-4 py-3 text-sm text-right"><x-currency :amount="$row['credit_amount']" /></td>
                        <td class="px-4 py-3 text-sm text-right">
                            <span class="font-mono font-semibold text-rutx-accent">
                                <x-currency :amount="$row['total_amount']" />
                            </span>
                        </td>
                    </tr>
                @endforeach
            </x-slot:row>
        </x-data-table>
    </div>
</div>
