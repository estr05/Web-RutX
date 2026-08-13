@props(['amount'])

<span class="font-mono text-[14px] text-right whitespace-nowrap">
    $ {{ number_format((float)$amount, 2, '.', ',') }}
</span>
