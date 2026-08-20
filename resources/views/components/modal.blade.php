{{--
    <x-modal> — Ventana flotante accesible con fondo oscurecido.

    Props:
      @prop string $id              ID único opcional para atributos aria.
      @prop string $maxWidth        Ancho máximo (sm, md, lg, xl, 2xl). Default: '2xl'.
      @prop bool   $closeable       Permitir cerrar pulsando fuera o Esc. Default: true.

    Nota: En Livewire 3 se suele controlar con Alpine x-data o con wire:model.
    Este componente usa x-data de Alpine para vincularse al modelo de Livewire
    (entrapment) si se usa {{ $attributes->wire('model') }}.
--}}
@props(['id' => null, 'maxWidth' => '2xl', 'closeable' => true])

@php
$id = $id ?? md5($attributes->wire('model'));

$maxWidthClass = match ($maxWidth) {
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
    default => 'sm:max-w-2xl',
};
@endphp

<div
    x-data="{
        show: @entangle($attributes->wire('model')),
        focusables() {
            let selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])'
            return [...$el.querySelectorAll(selector)]
                .filter(el => ! el.hasAttribute('disabled'))
        },
        firstFocusable() { return this.focusables()[0] },
        lastFocusable() { return this.focusables().slice(-1)[0] },
        nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable() },
        prevFocusable() { return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable() },
        nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length || 1) },
        prevFocusableIndex() { return (this.focusables().indexOf(document.activeElement) - 1 + this.focusables().length) % (this.focusables().length || 1) },
    }"
    x-init="$watch('show', value => {
        if (value) {
            document.body.classList.add('overflow-y-hidden');
            setTimeout(() => { if (firstFocusable()) firstFocusable().focus() }, 100);
        } else {
            document.body.classList.remove('overflow-y-hidden');
        }
    })"
    x-on:close.stop="show = false"
    x-on:keydown.escape.window="if(show && {{ $closeable ? 'true' : 'false' }}) show = false"
    x-on:keydown.tab.prevent="$event.shiftKey || nextFocusable().focus()"
    x-on:keydown.shift.tab.prevent="prevFocusable().focus()"
    x-show="show"
    id="{{ $id }}"
    class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50 flex items-center justify-center"
    style="display: none;"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $id }}-title"
>
    <!-- Overlay oscurecido -->
    <div 
        x-show="show" 
        class="fixed inset-0 transform transition-all" 
        x-on:click="if({{ $closeable ? 'true' : 'false' }}) show = false"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div class="absolute inset-0 bg-rutx-backdrop"></div>
    </div>

    <!-- Contenedor del Modal -->
    <div 
        x-show="show" 
        class="mb-6 bg-rutx-surface rounded-[var(--rutx-radius-lg)] overflow-hidden shadow-[var(--rutx-shadow-lg)] transform transition-all sm:w-full {{ $maxWidthClass }} sm:mx-auto"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
        {{ $slot }}
    </div>
</div>
