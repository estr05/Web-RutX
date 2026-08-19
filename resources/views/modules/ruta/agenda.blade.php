<x-app-layout>
    <x-slot:title>
        Agenda - Ruta - RutX Web
    </x-slot:title>

    <div class="h-full flex flex-col">
        <x-page-header title="Agenda" subtitle="GestiÃ³n semanal, programaciÃ³n y asignaciÃ³n de clientes">
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500 hidden sm:inline-block" title="Estado de sincronizaciÃ³n" wire:offline.class="text-red-500">
                        <span class="inline-block w-2 h-2 rounded-full bg-green-500 mr-1" wire:offline.class="bg-red-500"></span>
                        <span wire:offline.class="hidden">En lÃ­nea</span>
                        <span class="hidden" wire:offline.class.remove="hidden">Sin conexiÃ³n</span>
                    </span>
                    <!-- AquÃ­ pueden ir botones de acciÃ³n global, aunque la maqueta
                         indica que las acciones son los filtros y los batch -->
                </div>
            </x-slot:actions>
        </x-page-header>

        <!-- El componente Livewire encapsula el estado, el tablero y el panel lateral -->
        <livewire:routes.agenda />
    </div>
</x-app-layout>
