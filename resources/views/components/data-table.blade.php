@props(['headers' => [], 'items' => []])

<div x-data="{ 
        page: 1, 
        perPage: 10, 
        total: {{ count($items ?? []) }}, 
        get totalPages() { return Math.max(1, Math.ceil(this.total / this.perPage)); } 
    }" 
    class="bg-rutx-surface border border-rutx-border rounded-lg overflow-hidden shadow-[var(--rutx-shadow-base)] flex flex-col">
    <div class="overflow-x-auto overflow-y-auto max-h-[500px]">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-rutx-bg border-b border-rutx-border">
                    @foreach($headers as $header)
                        @php
                            $label = is_array($header) ? ($header['label'] ?? '') : $header;
                            $align = is_array($header) ? ($header['align'] ?? 'left') : 'left';
                            $alignClass = match($align) {
                                'right' => 'text-right',
                                'center' => 'text-center',
                                default => 'text-left',
                            };
                        @endphp
                        <th class="px-4 py-3 text-xs font-semibold text-rutx-primary uppercase tracking-wider sticky top-0 bg-rutx-bg shadow-sm z-10 {{ $alignClass }}">
                            {{ $label }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @if(isset($items) && count($items) > 0)
                    @if(isset($row))
                        {{ $row }}
                    @endif
                @else
                    <tr>
                        <td colspan="{{ count($headers) ?: 1 }}" class="px-4 py-12 text-center text-rutx-text-muted">
                            @if(isset($empty))
                                {{ $empty }}
                            @else
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="w-12 h-12 mb-4 text-rutx-border" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                                    <span class="text-sm">Sin registros para este período</span>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endif
            </tbody>
            @if(isset($footer))
                <tfoot class="bg-rutx-accent-light font-bold sticky bottom-0 z-10 shadow-[0_-1px_3px_rgba(0,0,0,0.1)]">
                    {{ $footer }}
                </tfoot>
            @endif
        </table>
    </div>

    <div x-show="total > perPage" x-cloak class="px-4 py-3 border-t border-rutx-border bg-rutx-surface-grey flex justify-between items-center">
        <span class="text-sm font-medium text-rutx-text-muted" x-text="`Página ${page} de ${totalPages}`"></span>
        <div class="flex gap-2">
            <button type="button" @click="page--" :disabled="page === 1" class="px-3 py-1.5 text-xs font-semibold border border-rutx-border rounded-lg hover:bg-rutx-secondary/10 disabled:opacity-50 transition-colors">Anterior</button>
            <button type="button" @click="page++" :disabled="page === totalPages" class="px-3 py-1.5 text-xs font-semibold border border-rutx-border rounded-lg hover:bg-rutx-secondary/10 disabled:opacity-50 transition-colors">Siguiente</button>
        </div>
    </div>

    @if(isset($pagination))
        <div class="px-4 py-3 border-t border-rutx-border bg-rutx-bg">
            {{ $pagination }}
        </div>
    @endif
</div>
