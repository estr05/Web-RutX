@props(['type' => 'success', 'message'])

@php
    $classes = match($type) {
        'success' => 'bg-[#2E7D32]/10 border-[#2E7D32]/20 text-rutx-status-success',
        'warning' => 'bg-[#E65100]/10 border-[#E65100]/20 text-rutx-status-warning',
        'error' => 'bg-[#C62828]/10 border-[#C62828]/20 text-rutx-status-error',
        default => 'bg-rutx-secondary/10 border-rutx-secondary/20 text-rutx-primary'
    };
@endphp

<div class="p-4 rounded-lg border {{ $classes }} flex items-start" role="alert" tabindex="0">
    <div class="flex-1 text-sm font-medium">
        {{ $message }}
    </div>
</div>
