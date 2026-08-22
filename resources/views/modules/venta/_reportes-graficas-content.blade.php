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

            <x-date-input id="reportes-date-from" label="Desde" wire:model="dateFrom" />

            <x-date-input id="reportes-date-to" label="Hasta" wire:model="dateTo" />

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

    {{-- Fila 1: métricas monetarias principales (4 tarjetas) --}}
    <div class="grid grid-cols-1 md:grid-cols-[repeat(4,minmax(0,1fr))] gap-4 mb-4">
        @foreach (array_slice($this->kpi, 0, 4) as $card)
            <x-kpi-card
                :title="$card['label']"
                :value="$card['value']"
                :format="$card['format']"
                :delta="$card['delta'] ?? null"
                :status="$card['status'] ?? 'unknown'"
                :icon-name="$card['iconName'] ?? null"
                :sub-label="$card['sub_label'] ?? null"
                :sub-value="$card['sub_value'] ?? null"
                :details="$card['details'] ?? []"
            />
        @endforeach
    </div>

    {{-- Fila 2: métricas operativas (3 tarjetas) --}}
    <div class="grid grid-cols-1 md:grid-cols-[repeat(3,minmax(0,1fr))] gap-4 mb-6">
        @foreach (array_slice($this->kpi, 4) as $card)
            <x-kpi-card
                :title="$card['label']"
                :value="$card['value']"
                :format="$card['format']"
                :delta="$card['delta'] ?? null"
                :status="$card['status'] ?? 'unknown'"
                :icon-name="$card['iconName'] ?? null"
                :sub-label="$card['sub_label'] ?? null"
                :sub-value="$card['sub_value'] ?? null"
                :details="$card['details'] ?? []"
            />
        @endforeach
    </div>

    <x-chart
        type="bar"
        :labels="$this->routeChart['labels'] ?? []"
        :datasets="$this->routeChart['datasets'] ?? []"
        format="currency"
        :currency="$this->currency"
        :height="340"
        summary="Ventas por Ruta — Contado vs Crédito"
    />

    <div class="mt-6">
        <x-data-table :headers="['Ruta', 'Piezas', 'Contado', 'Crédito', 'Total']" :items="$this->movimientos">
            <x-slot:row>
                @foreach ($this->movimientos as $row)
                    <tr x-show="Math.ceil({{ $loop->iteration }} / perPage) === page"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        class="border-b border-rutx-border hover:bg-rutx-secondary/10 transition-colors">
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

            @if(count($this->movimientos) > 0 && !empty($this->totals))
                <x-slot:footer>
                    <tr class="border-t-2 border-rutx-border bg-rutx-surface-grey">
                        <td class="px-4 py-3 text-sm font-bold text-rutx-primary">Total período</td>
                        <td class="px-4 py-3 text-sm text-right font-mono font-bold text-rutx-text">{{ $this->totals['pieces'] ?? 0 }}</td>
                        <td class="px-4 py-3 text-sm text-right font-bold"><x-currency :amount="$this->totals['cash_amount'] ?? 0" /></td>
                        <td class="px-4 py-3 text-sm text-right font-bold"><x-currency :amount="$this->totals['credit_amount'] ?? 0" /></td>
                        <td class="px-4 py-3 text-sm text-right font-bold">
                            <span class="font-mono font-bold text-rutx-accent">
                                <x-currency :amount="$this->totals['sales_amount'] ?? ($this->totals['total_amount'] ?? 0)" />
                            </span>
                        </td>
                    </tr>
                </x-slot:footer>
            @endif
        </x-data-table>
    </div>
</div>
