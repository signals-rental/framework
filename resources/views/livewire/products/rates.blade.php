<?php

use App\Actions\Rates\DeleteProductRate;
use App\Models\Product;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Product $product;

    public function mount(Product $product): void
    {
        Gate::authorize('rates.view');

        $this->product = $product;
        $this->loadRates();
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name.' — Rates');
    }

    public function deleteRate(int $rateId): void
    {
        $rate = $this->product->rates()->findOrFail($rateId);

        (new DeleteProductRate)($rate);

        $this->loadRates();
        $this->dispatch('rate-deleted');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'rates' => $this->product->rates()->with(['rateDefinition', 'store'])->orderByDesc('priority')->orderBy('id')->get(),
        ];
    }

    private function loadRates(): void
    {
        $this->product->load(['rates.rateDefinition', 'rates.store'])->loadCount('rates');
    }
}; ?>

<section class="w-full">
    @include('livewire.products.partials.product-header', ['product' => $product, 'subpage' => 'Rates'])
    @include('livewire.products.partials.product-tabs', ['product' => $product, 'activeTab' => 'rates'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-[var(--text-secondary)]" style="font-family: var(--font-display); text-transform: uppercase; letter-spacing: 0.04em;">
                Rates ({{ $product->rates_count ?? $rates->count() }})
            </h3>
            <a href="{{ route('products.rates.create', $product) }}" wire:navigate class="s-btn s-btn-sm s-btn-accent">
                <flux:icon.plus class="w-4 h-4" />
                Assign Rate
            </a>
        </div>

        @if(session('rate-overlap-warning'))
            <div class="mb-4 rounded-lg border border-[var(--amber)] p-3 text-sm text-[var(--amber)]">
                Heads up: this assignment overlaps {{ session('rate-overlap-warning') }} other rate(s) at the same priority and date range. Pricing uses the highest-priority match — adjust priorities or dates if that isn't intended.
            </div>
        @endif

        <x-action-message on="rate-deleted">Rate assignment removed.</x-action-message>

        @if($rates->isNotEmpty())
            <div class="s-table-wrap">
                <table class="s-table s-table-compact w-full">
                    <thead>
                        <tr>
                            <th class="text-left">Rate Definition</th>
                            <th class="text-left">Transaction</th>
                            <th class="text-left">Unit Price</th>
                            <th class="text-left">Store</th>
                            <th class="text-left">Valid</th>
                            <th class="text-left">Priority</th>
                            <th style="width: 48px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rates as $rate)
                            @php
                                $txBadge = match($rate->transaction_type) {
                                    \App\Enums\RateTransactionType::Rental => 's-badge-blue',
                                    \App\Enums\RateTransactionType::Sale => 's-badge-green',
                                    \App\Enums\RateTransactionType::Service => 's-badge-amber',
                                    default => 's-badge-zinc',
                                };
                            @endphp
                            <tr wire:key="rate-{{ $rate->id }}">
                                <td class="font-medium">{{ $rate->rateDefinition?->name ?? '—' }}</td>
                                <td>
                                    <span class="s-badge {{ $txBadge }}" style="display: inline-flex; align-items: center; gap: 4px;">
                                        @if($rate->transaction_type === \App\Enums\RateTransactionType::Rental)
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
                                        @elseif($rate->transaction_type === \App\Enums\RateTransactionType::Sale)
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                        @elseif($rate->transaction_type === \App\Enums\RateTransactionType::Service)
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                                        @endif
                                        {{ $rate->transaction_type->label() }}
                                    </span>
                                </td>
                                <td class="s-cell-amount">{{ $rate->formatMoneyCost('price') }} {{ $rate->currency }}</td>
                                <td>{{ $rate->store?->name ?? 'All stores' }}</td>
                                <td class="text-sm text-[var(--text-secondary)]">
                                    {{ $rate->valid_from?->toDateString() ?? '—' }} → {{ $rate->valid_to?->toDateString() ?? '—' }}
                                </td>
                                <td class="s-cell-mono">{{ $rate->priority }}</td>
                                <td class="text-right">
                                    <div x-data="{ open: false }" class="relative inline-flex">
                                        <button type="button" x-on:click.stop="open = !open" class="s-btn-ghost s-btn-xs s-btn-icon">
                                            <flux:icon.ellipsis-vertical class="w-4 h-4" />
                                        </button>
                                        <div
                                            x-show="open"
                                            x-on:click.outside="open = false"
                                            x-transition:enter="transition ease-out duration-100"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            x-transition:leave="transition ease-in duration-75"
                                            x-transition:leave-start="opacity-100 scale-100"
                                            x-transition:leave-end="opacity-0 scale-95"
                                            x-cloak
                                            class="s-dropdown"
                                            style="position: fixed; z-index: 9999;"
                                            x-ref="dropdown"
                                            x-init="$watch('open', value => {
                                                if (value) {
                                                    $nextTick(() => {
                                                        const rect = $el.previousElementSibling.getBoundingClientRect();
                                                        $refs.dropdown.style.top = rect.bottom + 4 + 'px';
                                                        $refs.dropdown.style.right = (window.innerWidth - rect.right) + 'px';
                                                        $refs.dropdown.style.left = 'auto';
                                                    });
                                                }
                                            })"
                                        >
                                            <a href="{{ route('products.rates.edit', [$product, $rate]) }}" wire:navigate class="s-dropdown-item">
                                                <flux:icon.pencil-square class="w-3.5 h-3.5" />
                                                Edit
                                            </a>
                                            <div style="height: 1px; background: var(--card-border); margin: 4px 0;"></div>
                                            <button type="button" x-on:click="open = false; $dispatch('open-modal', 'remove-rate-{{ $rate->id }}')" class="s-dropdown-item" style="color: var(--red); width: 100%;">
                                                <flux:icon.trash class="w-3.5 h-3.5" />
                                                Remove
                                            </button>
                                        </div>
                                    </div>

                                    <x-signals.modal name="remove-rate-{{ $rate->id }}" title="Remove Rate" size="sm">
                                        <p>Remove the <strong>{{ $rate->rateDefinition?->name ?? 'rate' }}</strong> assignment from this product? It can be re-assigned later. This action cannot be undone.</p>

                                        <x-slot:footer>
                                            <button class="s-btn s-btn-sm" type="button" x-on:click="$dispatch('close-modal', 'remove-rate-{{ $rate->id }}')">Cancel</button>
                                            <button class="s-btn s-btn-sm s-btn-danger" type="button" wire:click="deleteRate({{ $rate->id }})" x-on:click="$dispatch('close-modal', 'remove-rate-{{ $rate->id }}')">Remove</button>
                                        </x-slot:footer>
                                    </x-signals.modal>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-signals.empty title="No Rates Assigned" description="Assign a rate definition and unit price so this product can be priced. Without a rate it falls back to default pricing.">
                <x-slot:icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-10 opacity-30"><path d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </x-slot:icon>
            </x-signals.empty>
        @endif
    </div>
</section>
