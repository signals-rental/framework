<?php

use App\Models\StockLevel;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public StockLevel $stockLevel;

    public int $transactionType = 4;
    public string $transactionQuantity = '1';
    public ?string $transactionAt = null;
    public string $transactionDescription = '';

    public ?int $deletingTransactionId = null;

    public function mount(StockLevel $stockLevel): void
    {
        $this->stockLevel = $stockLevel->load(['product', 'store', 'member', 'stockTransactions']);
    }

    public function rendering(View $view): void
    {
        $title = $this->stockLevel->item_name ?? $this->stockLevel->product?->name ?? 'Stock Level';
        $view->title($title);
    }

    public function isSerialised(): bool
    {
        return $this->stockLevel->isSerialised();
    }

    public function openTransactionModal(): void
    {
        $this->resetValidation();
        $this->transactionType = \App\Enums\TransactionType::Buy->value;
        $this->transactionQuantity = '1';
        $this->transactionDescription = '';
        $this->transactionAt = now()->format('Y-m-d\TH:i');
    }

    public function addTransaction(): void
    {
        // Serialised stock moves a single unit per transaction (+1 / -1); bulk
        // stock accepts any positive whole number. Decimals are never allowed.
        $quantityRules = $this->isSerialised()
            ? ['required', 'integer', 'in:1']
            : ['required', 'integer', 'min:1'];

        $this->validate([
            'transactionQuantity' => $quantityRules,
            'transactionType' => ['required', 'integer', \Illuminate\Validation\Rule::in(\App\Enums\TransactionType::manualCreationValues())],
        ], [
            'transactionQuantity.integer' => 'Quantity must be a whole number.',
            'transactionQuantity.in' => 'Serialised stock can only move one unit per transaction.',
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
            $this->transactionQuantity = '1';
            $this->transactionDescription = '';
            $this->dispatch('transaction-saved');
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            $this->addError('transactionQuantity', 'You do not have permission to adjust stock.');
        }
    }

    public function confirmDeleteTransaction(int $transactionId): void
    {
        $this->deletingTransactionId = $transactionId;
    }

    public function deleteTransaction(): void
    {
        if ($this->deletingTransactionId === null) {
            return;
        }

        try {
            $transaction = $this->stockLevel->stockTransactions()->findOrFail($this->deletingTransactionId);
            (new \App\Actions\Products\DeleteStockTransaction)($transaction);

            $this->stockLevel->refresh();
            $this->stockLevel->load(['product', 'store', 'member', 'stockTransactions']);
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            $this->addError('transactionQuantity', 'You do not have permission to adjust stock.');
        }

        $this->deletingTransactionId = null;
        $this->dispatch('transaction-deleted');
    }
}; ?>

<section class="w-full">
    <x-signals.page-header :title="$stockLevel->item_name ?? $stockLevel->product?->name ?? 'Stock Level'">
        <x-slot:icon>
            <x-signals.entity-icon :model="$stockLevel->product ?? $stockLevel" :size="44" />
        </x-slot:icon>
        <x-slot:breadcrumbs>
            <a href="{{ route('stock-levels.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Stock Levels</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            @if($stockLevel->product)
                <a href="{{ route('products.show', $stockLevel->product) }}" wire:navigate class="text-[var(--link)] hover:underline">{{ $stockLevel->item_name ?? $stockLevel->product->name }}</a>
            @else
                <span>{{ $stockLevel->item_name ?? 'Stock Level' }}</span>
            @endif
        </x-slot:breadcrumbs>
        <x-slot:meta>
            {{-- Stock tracking: serialised vs bulk (from the product's stock method) --}}
            @php
                $isSerialised = $stockLevel->isSerialised();
            @endphp
            <span class="s-badge {{ $isSerialised ? 's-badge-violet' : 's-badge-cyan' }}" style="display: inline-flex; align-items: center; gap: 4px;">
                @if($isSerialised)
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/></svg>
                @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M12 2 2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                @endif
                {{ $isSerialised ? 'Serialised' : 'Bulk' }}
            </span>

            {{-- Product type + active flag — same colours/icons as the product page --}}
            @if($stockLevel->product)
                @php
                    $typeBadgeClass = match($stockLevel->product->product_type) {
                        \App\Enums\ProductType::Rental => 's-badge-blue',
                        \App\Enums\ProductType::Sale => 's-badge-green',
                        \App\Enums\ProductType::Service => 's-badge-amber',
                        \App\Enums\ProductType::LossAndDamage => 's-badge-red',
                        default => 's-badge-zinc',
                    };
                @endphp
                <span class="s-badge {{ $typeBadgeClass }}" style="display: inline-flex; align-items: center; gap: 4px;">
                    @if($stockLevel->product->product_type === \App\Enums\ProductType::Rental)
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
                    @elseif($stockLevel->product->product_type === \App\Enums\ProductType::Sale)
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    @elseif($stockLevel->product->product_type === \App\Enums\ProductType::Service)
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                    @elseif($stockLevel->product->product_type === \App\Enums\ProductType::LossAndDamage)
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    @endif
                    {{ $stockLevel->product->product_type->label() }}
                </span>

                @if($stockLevel->product->is_active)
                    <span class="s-badge s-badge-green"><span class="s-badge-dot"></span> Active</span>
                @else
                    <span class="s-badge s-badge-zinc"><span class="s-badge-dot"></span> Inactive</span>
                @endif
            @endif
        </x-slot:meta>
    </x-signals.page-header>

    <div class="flex flex-1 flex-col gap-6 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="grid grid-cols-3 gap-6 max-md:grid-cols-1 order-2">
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

            {{-- Details --}}
            <x-signals.panel title="Stock Level Details">
                <x-signals.data-list layout="vertical" :items="array_filter([
                    ['label' => 'Product', 'value' => $stockLevel->product?->name ?? '—', 'href' => $stockLevel->product ? route('products.show', $stockLevel->product) : null],
                    ['label' => 'Store', 'value' => $stockLevel->store?->name ?? '—', 'badge' => $stockLevel->store ? 's-badge-zinc' : null],
                    $stockLevel->item_name ? ['label' => 'Item Name', 'value' => $stockLevel->item_name] : null,
                    $stockLevel->asset_number ? ['label' => 'Asset / Barcode', 'value' => $stockLevel->asset_number, 'mono' => true] : null,
                    $stockLevel->serial_number ? ['label' => 'Serial Number', 'value' => $stockLevel->serial_number, 'mono' => true] : null,
                    $stockLevel->location ? ['label' => 'Location', 'value' => $stockLevel->location] : null,
                    $stockLevel->member ? ['label' => 'Member', 'value' => $stockLevel->member->name] : null,
                ])" />
            </x-signals.panel>

            {{-- Quantities --}}
            <x-signals.panel title="Quantities">
                @php
                    $available = (float) $stockLevel->quantity_held - (float) $stockLevel->quantity_allocated - (float) $stockLevel->quantity_unavailable;
                @endphp
                <div class="mb-4 pb-4 border-b border-[var(--card-border)]">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">Available</span>
                        <span class="text-lg font-bold" style="font-family: var(--font-display); color: {{ $available > 0 ? 'var(--green)' : 'var(--red)' }};">
                            <x-signals.skeleton-value width="3rem">{{ number_format($available, 2) }}</x-signals.skeleton-value>
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-4 gap-3">
                    <div>
                        <div class="text-[10px] font-medium uppercase tracking-wide text-[var(--text-muted)]">Held</div>
                        <div class="text-base font-bold" style="font-family: var(--font-display);"><x-signals.skeleton-value width="2rem">{{ $stockLevel->quantity_held }}</x-signals.skeleton-value></div>
                    </div>
                    <div>
                        <div class="text-[10px] font-medium uppercase tracking-wide text-[var(--text-muted)]">Allocated</div>
                        <div class="text-base font-bold" style="font-family: var(--font-display);"><x-signals.skeleton-value width="2rem">{{ $stockLevel->quantity_allocated }}</x-signals.skeleton-value></div>
                    </div>
                    <div>
                        <div class="text-[10px] font-medium uppercase tracking-wide text-[var(--text-muted)]">Unavailable</div>
                        <div class="text-base font-bold" style="font-family: var(--font-display);"><x-signals.skeleton-value width="2rem">{{ $stockLevel->quantity_unavailable }}</x-signals.skeleton-value></div>
                    </div>
                    <div>
                        <div class="text-[10px] font-medium uppercase tracking-wide text-[var(--text-muted)]">On Order</div>
                        <div class="text-base font-bold" style="font-family: var(--font-display);"><x-signals.skeleton-value width="2rem">{{ $stockLevel->quantity_on_order }}</x-signals.skeleton-value></div>
                    </div>
                </div>
            </x-signals.panel>
        </div>

        {{-- Transactions (top of view) --}}
        <div class="order-1">
            <x-signals.panel title="Transactions">
                <div class="flex justify-end mb-4">
                    <button type="button" wire:click="openTransactionModal" x-on:click="$dispatch('open-modal', 'add-transaction')" class="s-btn s-btn-sm s-btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add Transaction
                    </button>
                </div>

                <div x-data="{ ready: false }" x-init="$nextTick(() => ready = true)">
                    <div x-show="!ready"><x-signals.skeleton type="text" :lines="5" /></div>
                    <div x-show="ready" x-cloak>
                @if($stockLevel->stockTransactions->isNotEmpty())
                    @php
                        // Build a running balance chronologically (oldest first) so each
                        // row carries its brought-forward and carried-forward balance.
                        // The newest row's c/fwd always equals the held quantity.
                        $orderedTxns = $stockLevel->stockTransactions->sortBy([
                            ['transaction_at', 'asc'],
                            ['id', 'asc'],
                        ])->values();
                        $runningBalance = 0.0;
                        $txnBalances = [];
                        foreach ($orderedTxns as $orderedTxn) {
                            $opening = $runningBalance;
                            $runningBalance += (float) $orderedTxn->quantity_move;
                            $txnBalances[$orderedTxn->id] = ['opening' => $opening, 'closing' => $runningBalance];
                        }
                        $latestTxnId = $orderedTxns->last()?->id;
                    @endphp
                    <div class="s-table-wrap">
                        <table class="s-table w-full">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Opening b/fwd</th>
                                    <th>Quantity</th>
                                    <th>Move</th>
                                    <th>Closing c/fwd</th>
                                    <th>Description</th>
                                    <th>Manual</th>
                                    <th class="w-[60px]"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orderedTxns->reverse() as $txn)
                                    <tr wire:key="txn-{{ $txn->id }}">
                                        <td>{{ $txn->transaction_at->format('d M Y H:i') }}</td>
                                        <td>
                                            @php
                                                $txnType = $txn->transaction_type;
                                                $typeBadge = $txnType === \App\Enums\TransactionType::Opening
                                                    ? 's-badge-navy'
                                                    : ($txnType->quantitySign() >= 0 ? 's-badge-green' : 's-badge-red');
                                            @endphp
                                            <span class="s-badge {{ $typeBadge }}">{{ $txnType->label() }}</span>
                                        </td>
                                        <td>{{ number_format($txnBalances[$txn->id]['opening'] ?? 0, 1) }}</td>
                                        <td>{{ number_format((float) $txn->quantity, 1) }}</td>
                                        @php $move = (float) $txn->quantity_move; @endphp
                                        <td style="background: {{ $move >= 0 ? 'rgba(5, 150, 105, 0.07)' : 'rgba(220, 38, 38, 0.07)' }};">
                                            <span class="inline-flex items-center gap-1 font-medium {{ $move >= 0 ? 'text-[var(--green)]' : 'text-[var(--red)]' }}">
                                                @if($move >= 0)
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-3 h-3"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
                                                @else
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-3 h-3"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
                                                @endif
                                                {{ $move >= 0 ? '+' : '' }}{{ $txn->quantity_move }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($txn->id === $latestTxnId)
                                                <span class="font-bold text-[var(--text-primary)]" style="font-family: var(--font-display);">{{ number_format($txnBalances[$txn->id]['closing'] ?? 0, 1) }}</span>
                                            @else
                                                {{ number_format($txnBalances[$txn->id]['closing'] ?? 0, 1) }}
                                            @endif
                                        </td>
                                        <td>{{ $txn->description ?? '—' }}</td>
                                        <td>{{ $txn->manual ? 'Yes' : 'No' }}</td>
                                        <td class="text-right">
                                            <button type="button" wire:click="confirmDeleteTransaction({{ $txn->id }})" x-on:click="$dispatch('open-modal', 'delete-transaction')" class="s-btn s-btn-xs s-btn-ghost text-[var(--red)]" title="Delete transaction">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-[var(--text-muted)] text-center py-4">No transactions recorded yet.</p>
                @endif
                    </div>
                </div>
            </x-signals.panel>
        </div>
    </div>

    {{-- Add Transaction modal --}}
    <x-signals.modal name="add-transaction" title="Add Transaction" x-on:transaction-saved.window="open = false">
        <form id="add-transaction-form" wire:submit="addTransaction" class="flex flex-col gap-4">
            <flux:select wire:model="transactionType" label="Type">
                @foreach(\App\Enums\TransactionType::manualCreationValues() as $val)
                    <option value="{{ $val }}">{{ \App\Enums\TransactionType::from($val)->label() }}</option>
                @endforeach
            </flux:select>

            @if($this->isSerialised())
                <flux:input wire:model="transactionQuantity" label="Quantity" type="number" readonly
                    description="Serialised stock moves one unit (+1 / -1) per transaction." />
            @else
                <flux:input wire:model="transactionQuantity" label="Quantity" type="number" step="1" min="1" required
                    description="Whole numbers only." />
            @endif

            <flux:input wire:model="transactionAt" label="Date" type="datetime-local" />
            <flux:input wire:model="transactionDescription" label="Description" />
        </form>

        <x-slot:footer>
            <button type="button" class="s-btn s-btn-sm" x-on:click="$dispatch('close-modal', 'add-transaction')">Cancel</button>
            <button type="submit" form="add-transaction-form" class="s-btn s-btn-sm s-btn-primary">Save Transaction</button>
        </x-slot:footer>
    </x-signals.modal>

    {{-- Delete Transaction confirmation modal --}}
    <x-signals.modal name="delete-transaction" title="Delete Transaction" size="sm" x-on:transaction-deleted.window="open = false">
        <p>Are you sure you want to delete this transaction? The stock level quantity will be adjusted to reverse its effect. This cannot be undone.</p>

        <x-slot:footer>
            <button type="button" class="s-btn s-btn-sm" x-on:click="$dispatch('close-modal', 'delete-transaction')">Cancel</button>
            <button type="button" class="s-btn s-btn-sm s-btn-danger" wire:click="deleteTransaction" x-on:click="$dispatch('close-modal', 'delete-transaction')">Delete Transaction</button>
        </x-slot:footer>
    </x-signals.modal>
</section>
