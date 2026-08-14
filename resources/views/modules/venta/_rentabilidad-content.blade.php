{{--
    Contenido de Venta · Rentabilidad por Ruta (render del componente Livewire).
    Anatomía estándar: breadcrumb → H1 → filtros → KPIs → gráfica → tabla.
    Agregados de venta, gasto, entrega y costo disponible por ruta.
    Sin datos ni cálculos: todo viene de las propiedades del componente
    ($kpi, $series, $rows) y los montos solo vía <x-currency> o
    Money::format (guidelines §2.1, §5.2).
--}}
<div wire:poll.{{ $pollInterval }}s>
    <x-breadcrumb />

    <x-page-header
        title="Rentabilidad por Ruta"
        subtitle="Venta, gastos, costo disponible y utilidad por ruta del periodo."
    />

    <form wire:submit="consultar">
        <x-filter-bar>
            <x-date-range :range="$range" />

            <x-date-input id="rentabilidad-date-from" label="Desde" wire:model="dateFrom" />

            <x-date-input id="rentabilidad-date-to" label="Hasta" wire:model="dateTo" />

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

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        @foreach ($this->kpi as $card)
            <x-kpi-card
                :title="$card['label']"
                :value="(float) $card['value']"
                :delta="$card['delta']"
                :status="$card['status']"
            />
        @endforeach
    </div>

    <x-chart
        :labels="$this->series['labels'] ?? []"
        :datasets="$this->series['datasets'] ?? []"
        summary="Venta y gastos por ruta"
    />

    <div class="mt-6">
        <x-data-table :headers="['Ruta', 'Venta', 'Gastos', 'Entrega', 'Costo', 'Utilidad']" :items="$this->rows">
            <x-slot:row>
                @foreach ($this->rows as $row)
                    <tr class="border-b border-rutx-border hover:bg-rutx-secondary/10 transition-colors">
                        <td class="px-4 py-3 text-sm text-rutx-text">{{ $row['route_name'] }}</td>
                        <td class="px-4 py-3 text-sm text-right"><x-currency :amount="$row['sales_amount']" /></td>
                        <td class="px-4 py-3 text-sm text-right"><x-currency :amount="$row['expense_amount']" /></td>
                        <td class="px-4 py-3 text-sm text-right"><x-currency :amount="$row['delivery_amount']" /></td>
                        <td class="px-4 py-3 text-sm text-right"><x-currency :amount="$row['cost_amount']" /></td>
                        <td class="px-4 py-3 text-sm text-right">
                            <span class="font-mono font-semibold text-rutx-accent">
                                <x-currency :amount="$row['profit_amount']" />
                            </span>
                        </td>
                    </tr>
                @endforeach
            </x-slot:row>
        </x-data-table>
    </div>
</div>
