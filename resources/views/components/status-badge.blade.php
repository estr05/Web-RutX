@props(['status' => 'unknown', 'label'])

@php
    $classes = match($status) {
        'success' => 'text-rutx-status-success bg-[#2E7D32]/12',
        'warning' => 'text-rutx-status-warning bg-[#E65100]/12',
        'error' => 'text-rutx-status-error bg-[#C62828]/12',
        default => 'text-rutx-status-unknown bg-[#9E9E9E]/12'
    };
@endphp

<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[12px] font-semibold {{ $classes }}">
    {{ $label }}
</span>
