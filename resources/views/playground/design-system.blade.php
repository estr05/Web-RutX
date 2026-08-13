<x-app-layout>
    <x-slot:activeModule>cliente</x-slot:activeModule>

    <x-page-header title="Design System Playground">
        <x-slot name="actions">
            <button class="h-[var(--rutx-height-button)] px-6 bg-rutx-primary text-white rounded-lg text-sm font-medium hover:bg-rutx-primary-dark transition-colors shadow-sm">
                Acción Principal
            </button>
        </x-slot>
    </x-page-header>

    <x-breadcrumb :paths="['Playground', 'Design System']" />

    <div class="space-y-8 mt-8">

        <section>
            <h2 class="text-xl font-bold text-rutx-text mb-4 border-b border-rutx-border pb-2">1. KPI Cards</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <x-kpi-card
                    title="Venta del Día"
                    value="$12,450.00"
                    delta="+5.2%"
                    status="success"
                    icon="🛒" />

                <x-kpi-card
                    title="Clientes Visitados"
                    value="42 / 50"
                    delta="-2"
                    status="warning"
                    icon="👥" />

                <x-kpi-card
                    title="Devoluciones"
                    value="3"
                    delta="Alerta"
                    status="error"
                    icon="⚠️" />

                <x-kpi-card
                    title="Efectividad"
                    value="84%"
                    status="unknown"
                    icon="📊" />
            </div>
        </section>

        <section>
            <h2 class="text-xl font-bold text-rutx-text mb-4 border-b border-rutx-border pb-2">2. Filter Bar & Alerts</h2>

            <x-alert type="warning" message="Atención: Esta es una alerta de prueba. Por favor revise los parámetros de configuración." />
            <div class="h-4"></div>
            <x-alert type="error" message="Error de conexión con el Sincronizador." />
            <div class="h-4"></div>
            <x-alert type="success" message="Los datos se han guardado correctamente." />

            <div class="mt-6">
                <x-filter-bar>
                    <div class="w-64">
                        <label class="block text-xs font-semibold text-rutx-text-muted mb-1">Ruta</label>
                        <select class="w-full h-[var(--rutx-height-input)] border border-rutx-border rounded-lg bg-rutx-surface px-3 text-sm focus:ring-2 focus:ring-rutx-primary focus:border-rutx-primary outline-none transition-shadow">
                            <option>Ruta Norte</option>
                            <option>Ruta Sur</option>
                        </select>
                    </div>
                    <div class="w-64">
                        <label class="block text-xs font-semibold text-rutx-text-muted mb-1">Estado</label>
                        <select class="w-full h-[var(--rutx-height-input)] border border-rutx-border rounded-lg bg-rutx-surface px-3 text-sm focus:ring-2 focus:ring-rutx-primary focus:border-rutx-primary outline-none transition-shadow">
                            <option>Todos</option>
                            <option>Activo</option>
                        </select>
                    </div>
                </x-filter-bar>
            </div>
        </section>

        <section>
            <h2 class="text-xl font-bold text-rutx-text mb-4 border-b border-rutx-border pb-2">3. Data Table & Badges</h2>

            @php
                $dummyData = [
                    ['id' => '1001', 'cliente' => 'Cliente de ejemplo A', 'ruta' => 'Norte', 'monto' => 4500.50, 'estado' => 'success', 'estadoLabel' => 'Activo'],
                    ['id' => '1002', 'cliente' => 'Cliente de ejemplo B', 'ruta' => 'Sur', 'monto' => 1250.00, 'estado' => 'warning', 'estadoLabel' => 'Pendiente'],
                    ['id' => '1003', 'cliente' => 'Cliente de ejemplo C', 'ruta' => 'Norte', 'monto' => 8400.00, 'estado' => 'error', 'estadoLabel' => 'Rechazado'],
                    ['id' => '1004', 'cliente' => 'Cliente de ejemplo D', 'ruta' => 'Este', 'monto' => 300.00, 'estado' => 'unknown', 'estadoLabel' => 'Inactivo'],
                ];
            @endphp

            <x-data-table :headers="['ID', 'Cliente', 'Ruta', 'Estado', 'Monto']" :items="$dummyData">
                <x-slot:row>
                    @foreach($dummyData as $item)
                        <tr class="border-b border-rutx-border hover:bg-rutx-secondary/10 transition-colors {{ $loop->even ? 'bg-rutx-surface-grey' : 'bg-rutx-surface' }}">
                            <td class="px-4 py-3 text-sm text-rutx-text-muted">{{ $item['id'] }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-rutx-text">{{ $item['cliente'] }}</td>
                            <td class="px-4 py-3 text-sm text-rutx-text">{{ $item['ruta'] }}</td>
                            <td class="px-4 py-3 text-sm">
                                <x-status-badge :status="$item['estado']" :label="$item['estadoLabel']" />
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-mono">
                                <x-currency :amount="$item['monto']" />
                            </td>
                        </tr>
                    @endforeach
                </x-slot:row>

                <x-slot:pagination>
                    <div class="flex justify-between items-center text-sm text-rutx-text-muted">
                        <span>Mostrando 1 a 4 de 4 registros</span>
                        <div class="flex space-x-2">
                            <button class="px-3 py-1 rounded border border-rutx-border opacity-50 cursor-not-allowed">Anterior</button>
                            <button class="px-3 py-1 rounded border border-rutx-border bg-white text-rutx-primary font-medium hover:bg-rutx-surface-grey">Siguiente</button>
                        </div>
                    </div>
                </x-slot:pagination>
            </x-data-table>

            <div class="mt-8">
                <h3 class="text-sm font-bold text-rutx-text-muted mb-2">Estado Vacío</h3>
                <x-data-table :headers="['Documento', 'Fecha', 'Referencia', 'Importe']" :items="[]" />
            </div>
        </section>

        <section>
            <h2 class="text-xl font-bold text-rutx-text mb-4 border-b border-rutx-border pb-2">4. Loading State</h2>
            <div class="bg-rutx-surface border border-rutx-border rounded-lg min-h-[300px] flex items-center justify-center relative">
                <x-loading-state message="Procesando solicitud, por favor espere..." />
            </div>
        </section>

    </div>
</x-app-layout>
