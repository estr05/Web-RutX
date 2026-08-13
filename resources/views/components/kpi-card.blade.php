@props([
    'title',
    'value',
    'delta' => null,
    'status' => 'unknown', // success, warning, error, unknown
    'icon' => null
])

<div class="bg-rutx-surface border border-rutx-border rounded-lg p-4 shadow-[var(--rutx-shadow-base)] hover:shadow-[var(--rutx-shadow-hover)] transition-shadow">
    <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-medium text-rutx-text-muted uppercase tracking-wider">{{ $title }}</span>
        @if($icon)
            <div class="text-rutx-primary w-5 h-5">
                {{ $icon }}
            </div>
        @endif
    </div>
    
    <div class="flex items-baseline space-x-2">
        <span class="text-[28px] font-bold text-rutx-text">{{ $value }}</span>
        
        @if($delta)
            @php
                $statusColor = match($status) {
                    'success' => 'text-rutx-status-success',
                    'warning' => 'text-rutx-status-warning',
                    'error' => 'text-rutx-status-error',
                    default => 'text-rutx-status-unknown'
                };
            @endphp
            <span class="text-xs font-semibold {{ $statusColor }} bg-current/10 px-1.5 py-0.5 rounded">
                {{ $delta }}
            </span>
        @endif
    </div>
</div>
