<?php

use App\Models\StockLevel;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Stock Levels')] class extends Component {
    #[Url(as: 'status')]
    public string $statusFilter = '';

    public function setStatusFilter(string $status): void
    {
        if (! in_array($status, ['', 'available', 'allocated', 'quarantined'])) {
            return;
        }

        $this->statusFilter = $status;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'columns' => [
                ['key' => 'checkbox', 'type' => 'checkbox'],
                ['key' => 'product', 'label' => 'Product', 'view' => 'livewire.stock-levels.partials.column-product'],
                ['key' => 'item_name', 'label' => 'Item Name', 'sortable' => true, 'filterable' => true, 'filter_type' => 'text'],
                ['key' => 'store', 'label' => 'Store', 'view' => 'livewire.stock-levels.partials.column-store'],
                ['key' => 'asset_number', 'label' => 'Asset #', 'sortable' => true, 'filterable' => true, 'filter_type' => 'text'],
                ['key' => 'serial_number', 'label' => 'Serial #', 'sortable' => true],
                ['key' => 'quantity_held', 'label' => 'Held', 'sortable' => true],
                ['key' => 'quantity_allocated', 'label' => 'Allocated', 'sortable' => true],
                ['key' => 'quantity_unavailable', 'label' => 'Unavailable', 'sortable' => true],
                ['key' => 'status', 'label' => 'Status', 'view' => 'livewire.stock-levels.partials.column-status'],
                ['key' => 'created_at', 'label' => 'Created', 'sortable' => true],
                ['key' => 'actions', 'type' => 'actions'],
            ],
            'scopes' => match ($this->statusFilter) {
                'available' => ['available' => true],
                'allocated' => [],
                'quarantined' => [],
                default => [],
            },
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header title="Stock Levels">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Inventory</span>
        </x-slot:meta>
    </x-signals.page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        {{-- Status filter chips --}}
        <div class="mb-4 flex flex-wrap items-center gap-1">
            <button wire:click="setStatusFilter('')" class="s-chip {{ $statusFilter === '' ? 'on' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                All
            </button>
            <button wire:click="setStatusFilter('available')" class="s-chip {{ $statusFilter === 'available' ? 'on' : '' }}">Available</button>
            <button wire:click="setStatusFilter('allocated')" class="s-chip {{ $statusFilter === 'allocated' ? 'on' : '' }}">Allocated</button>
            <button wire:click="setStatusFilter('quarantined')" class="s-chip {{ $statusFilter === 'quarantined' ? 'on' : '' }}">Quarantined</button>
        </div>

        {{-- Data table --}}
        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Models\StockLevel::class"
            :searchable="['item_name', 'asset_number', 'serial_number', 'barcode']"
            :with="['product', 'store']"
            :scopes="$scopes"
            default-sort="item_name"
            empty-message="No stock levels found."
            actions-view="livewire.stock-levels.partials.row-actions"
            toolbar-view="livewire.stock-levels.partials.toolbar"
            entity-type="stock_levels"
            :key="'stock-levels-table-' . $statusFilter"
        />
    </div>
</section>
