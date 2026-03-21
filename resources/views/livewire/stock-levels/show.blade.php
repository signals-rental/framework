<?php

use App\Models\StockLevel;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public StockLevel $stockLevel;

    public int $transactionType = 4;
    public string $transactionQuantity = '1.0';
    public ?string $transactionAt = null;
    public string $transactionDescription = '';
    public bool $showTransactionForm = false;

    public function mount(StockLevel $stockLevel): void
    {
        $this->stockLevel = $stockLevel->load(['product', 'store', 'member', 'stockTransactions']);
    }

    public function rendering(View $view): void
    {
        $title = $this->stockLevel->item_name ?? $this->stockLevel->product?->name ?? 'Stock Level';
        $view->title($title);
    }

    public function toggleTransactionForm(): void
    {
        $this->showTransactionForm = !$this->showTransactionForm;
        $this->transactionAt = now()->format('Y-m-d\TH:i');
    }

    public function addTransaction(): void
    {
        $this->validate([
            'transactionQuantity' => ['required', 'numeric', 'min:0.01'],
            'transactionType' => ['required', 'integer', \Illuminate\Validation\Rule::in(\App\Enums\TransactionType::manualCreationValues())],
        ]);

        try {
            $dto = \App\Data\Products\CreateStockTransactionData::from([
                'stock_level_id' => $this->stockLevel->id,
                'store_id' => $this->stockLevel->store_id,
                'transaction_type' => $this->transactionType,
                'transaction_at' => $this->transactionAt,
                'quantity' => $this->transactionQuantity,
                'description' => $this->transactionDescription ?: null,
            ]);

            (new \App\Actions\Products\CreateStockTransaction)($dto);

            $this->stockLevel->refresh();
            $this->stockLevel->load(['product', 'store', 'member', 'stockTransactions']);
            $this->showTransactionForm = false;
            $this->transactionQuantity = '1.0';
            $this->transactionDescription = '';
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            $this->addError('transactionQuantity', 'You do not have permission to adjust stock.');
        }
    }
}; ?>

<section class="w-full">
    <x-signals.page-header :title="$stockLevel->item_name ?? $stockLevel->product?->name ?? 'Stock Level'">
        <x-slot:breadcrumbs>
            <a href="{{ route('stock-levels.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Stock Levels</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>{{ $stockLevel->item_name ?? $stockLevel->product?->name ?? 'Stock Level' }}</span>
        </x-slot:breadcrumbs>
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Inventory</span>
        </x-slot:meta>
    </x-signals.page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="grid grid-cols-2 gap-6 max-md:grid-cols-1">
            {{-- Details --}}
            <x-signals.panel title="Stock Level Details">
                <x-signals.data-list layout="vertical" :items="array_filter([
                    ['label' => 'Product', 'value' => $stockLevel->product?->name ?? '—'],
                    ['label' => 'Store', 'value' => $stockLevel->store?->name ?? '—'],
                    $stockLevel->item_name ? ['label' => 'Item Name', 'value' => $stockLevel->item_name] : null,
                    $stockLevel->asset_number ? ['label' => 'Asset Number', 'value' => $stockLevel->asset_number] : null,
                    $stockLevel->serial_number ? ['label' => 'Serial Number', 'value' => $stockLevel->serial_number] : null,
                    $stockLevel->barcode ? ['label' => 'Barcode', 'value' => $stockLevel->barcode] : null,
                    $stockLevel->location ? ['label' => 'Location', 'value' => $stockLevel->location] : null,
                    $stockLevel->member ? ['label' => 'Member', 'value' => $stockLevel->member->name] : null,
                ])" />
            </x-signals.panel>

            {{-- Quantities --}}
            <x-signals.panel title="Quantities">
                <x-signals.data-list layout="vertical" :items="[
                    ['label' => 'Quantity Held', 'value' => (string) $stockLevel->quantity_held],
                    ['label' => 'Quantity Allocated', 'value' => (string) $stockLevel->quantity_allocated],
                    ['label' => 'Quantity Unavailable', 'value' => (string) $stockLevel->quantity_unavailable],
                    ['label' => 'Quantity on Order', 'value' => (string) $stockLevel->quantity_on_order],
                ]" />

                @php
                    $available = (float) $stockLevel->quantity_held - (float) $stockLevel->quantity_allocated - (float) $stockLevel->quantity_unavailable;
                @endphp
                <div class="mt-4 pt-4 border-t border-[var(--card-border)]">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">Available</span>
                        <span class="text-lg font-bold" style="font-family: var(--font-display); color: {{ $available > 0 ? 'var(--green)' : 'var(--red)' }};">
                            {{ number_format($available, 2) }}
                        </span>
                    </div>
                </div>
            </x-signals.panel>

            {{-- Dates --}}
            <x-signals.panel title="Dates">
                <x-signals.data-list layout="vertical" :items="array_filter([
                    $stockLevel->starts_at ? ['label' => 'Starts At', 'value' => $stockLevel->starts_at->format('d M Y H:i')] : null,
                    $stockLevel->ends_at ? ['label' => 'Ends At', 'value' => $stockLevel->ends_at->format('d M Y H:i')] : null,
                    $stockLevel->last_count_at ? ['label' => 'Last Count', 'value' => $stockLevel->last_count_at->format('d M Y H:i')] : null,
                    ['label' => 'Created', 'value' => $stockLevel->created_at?->format('d M Y H:i') ?? '—'],
                    ['label' => 'Updated', 'value' => $stockLevel->updated_at?->format('d M Y H:i') ?? '—'],
                ])" />
            </x-signals.panel>
        </div>

        {{-- Transactions --}}
        <div class="mt-6">
            <x-signals.panel title="Transactions">
                <div class="flex justify-end mb-4">
                    <button wire:click="toggleTransactionForm" class="s-btn s-btn-sm s-btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add Transaction
                    </button>
                </div>

                @if($showTransactionForm)
                    <div class="mb-6 p-4 border border-[var(--card-border)] bg-[var(--s-subtle)]">
                        <form wire:submit="addTransaction">
                            <div class="grid grid-cols-4 gap-4 max-md:grid-cols-2 max-sm:grid-cols-1">
                                <flux:select wire:model="transactionType" label="Type">
                                    @foreach(\App\Enums\TransactionType::manualCreationValues() as $val)
                                        <option value="{{ $val }}">{{ \App\Enums\TransactionType::from($val)->label() }}</option>
                                    @endforeach
                                </flux:select>
                                <flux:input wire:model="transactionQuantity" label="Quantity" type="number" step="0.01" min="0.01" required />
                                <flux:input wire:model="transactionAt" label="Date" type="datetime-local" />
                                <flux:input wire:model="transactionDescription" label="Description" />
                            </div>
                            <div class="flex items-center gap-2 mt-3">
                                <flux:button variant="primary" type="submit" size="sm">Save Transaction</flux:button>
                                <flux:button variant="ghost" size="sm" wire:click="toggleTransactionForm">Cancel</flux:button>
                            </div>
                        </form>
                    </div>
                @endif

                @if($stockLevel->stockTransactions->isNotEmpty())
                    <div class="s-table-wrap">
                        <table class="s-table w-full">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Quantity</th>
                                    <th>Move</th>
                                    <th>Description</th>
                                    <th>Manual</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stockLevel->stockTransactions->sortByDesc('transaction_at') as $txn)
                                    <tr wire:key="txn-{{ $txn->id }}">
                                        <td>{{ $txn->transaction_at->format('d M Y H:i') }}</td>
                                        <td><span class="s-badge s-badge-blue">{{ $txn->transaction_type->label() }}</span></td>
                                        <td>{{ number_format((float) $txn->quantity, 1) }}</td>
                                        <td>
                                            @php $move = (float) $txn->quantity_move; @endphp
                                            <span class="{{ $move >= 0 ? 'text-[var(--green)]' : 'text-[var(--red)]' }}">
                                                {{ $move >= 0 ? '+' : '' }}{{ $txn->quantity_move }}
                                            </span>
                                        </td>
                                        <td>{{ $txn->description ?? '—' }}</td>
                                        <td>{{ $txn->manual ? 'Yes' : 'No' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-[var(--text-muted)] text-center py-4">No transactions recorded yet.</p>
                @endif
            </x-signals.panel>
        </div>
    </div>
</section>
