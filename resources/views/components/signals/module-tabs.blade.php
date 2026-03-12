@props(['tabs' => [], 'active' => null])

<nav class="app-subnav">
    <div class="flex h-full items-center gap-0">
        @foreach($tabs as $tab)
            <a href="{{ $tab['route'] }}"
               class="subnav-link {{ $active === $tab['name'] ? 'active' : '' }}"
               wire:navigate>
                {{ $tab['label'] }}
                @if(isset($tab['count']))
                    <span class="ml-1 text-[10px] text-[var(--text-muted)]">({{ $tab['count'] }})</span>
                @endif
            </a>
        @endforeach
    </div>
</nav>
