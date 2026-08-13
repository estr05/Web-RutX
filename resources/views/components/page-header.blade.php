@props(['title'])

<div class="flex items-center justify-between mb-6">
    <h1 class="text-[30px] leading-[1.2] font-bold text-rutx-primary tracking-tight">{{ $title }}</h1>
    
    @if(isset($actions))
        <div class="flex items-center space-x-3">
            {{ $actions }}
        </div>
    @endif
</div>
