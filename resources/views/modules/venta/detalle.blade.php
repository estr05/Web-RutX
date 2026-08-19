<x-layouts.app>
    <x-slot:title>
        Detalle de Transacción - Ventas - RutX Web
    </x-slot:title>

    <div class="h-full flex flex-col">
        <x-page-header title="Detalle de Venta">
            <x-slot:breadcrumbs>
                <nav class="flex text-sm text-gray-500 space-x-2 whitespace-nowrap mb-2">
                    <a href="{{ route('venta.transacciones') }}" class="hover:text-gray-900 transition-colors">Transacciones</a>
                    <span>/</span>
                    <span class="text-gray-900">Ticket #{{ $saleId }}</span>
                </nav>
            </x-slot:breadcrumbs>
        </x-page-header>

        <livewire:sales.show :saleId="$saleId" />
    </div>
</x-layouts.app>
