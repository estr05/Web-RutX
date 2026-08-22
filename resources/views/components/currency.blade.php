{{--
    <x-currency> — Monto MXN en JetBrains Mono, alineado a la derecha.

    Props:
      @prop float|int|string $amount  Monto a formatear (se castea a float).

    El formato vive en App\Support\Money::format() (fuente única).
--}}
@props(['amount' => null])

@if ($amount === null)
    <span class="font-mono text-[14px] text-right whitespace-nowrap text-rutx-text-muted" aria-label="No disponible">
        <span aria-hidden="true">---</span><span class="sr-only">No disponible</span>
    </span>
@else
    <span class="font-mono text-[14px] text-right whitespace-nowrap">
        {{ \App\Support\Money::format((float) $amount) }}
    </span>
@endif
