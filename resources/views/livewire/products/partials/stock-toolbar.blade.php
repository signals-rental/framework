<x-signals.column-toggle />
<a href="{{ route('stock-levels.create', ['product_id' => $this->scopes['forProduct'] ?? '']) }}" wire:navigate class="s-btn s-btn-sm s-btn-accent">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M12 5v14M5 12h14"/></svg>
    New Stock Level
</a>
