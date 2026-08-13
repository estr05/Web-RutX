@props(['paths' => []])

<nav class="flex text-[12px] text-rutx-text-muted mb-4" aria-label="Breadcrumb">
    <ol class="inline-flex items-center space-x-2">
        @foreach($paths as $index => $path)
            <li class="inline-flex items-center">
                @if(!$loop->last)
                    <span class="font-medium">{{ $path }}</span>
                    <span class="mx-2 text-rutx-border">·</span>
                @else
                    <span class="font-semibold text-rutx-text">{{ $path }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
