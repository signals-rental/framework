<div
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail === 'merge-products') open = true"
    x-on:close-modal.window="if ($event.detail === 'merge-products') open = false"
>
    <div
        class="s-modal-backdrop"
        x-show="open"
        x-cloak
        x-transition.opacity
        x-on:click.self="open = false"
        x-on:keydown.escape.window="open = false"
    >
        <div class="s-modal-lg s-modal" x-trap.noscroll="open">
            <div class="s-modal-header">
                <span class="s-modal-title">Merge Products</span>
                <button class="s-modal-close" type="button" x-on:click="open = false">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <div class="s-modal-body">
                @if(session('error'))
                    <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 mb-4">
                        {{ session('error') }}
                    </div>
                @endif

                @if($productA && !$productB)
                    <p class="text-sm text-[var(--text-secondary)] mb-4">
                        Search for a product to merge with <strong>{{ $productA->name }}</strong>.
                    </p>
                    <div class="relative">
                        <flux:input wire:model.live.debounce.300ms="mergeSearch" placeholder="Search products..." />
                        @if(count($mergeSearchResults) > 0)
                            <div class="s-dropdown" style="position: relative; margin-top: 4px;">
                                @foreach($mergeSearchResults as $result)
                                    <button wire:key="merge-result-{{ $result['id'] }}" wire:click="selectMergeTarget({{ $result['id'] }})" class="s-dropdown-item w-full text-left">
                                        {{ $result['name'] }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @elseif($productA && $productB)
                    <p class="text-sm text-[var(--text-secondary)] mb-4">
                        Select which product to keep as the primary record. All data from the other product will be migrated, and the secondary product will be archived.
                    </p>

                    @if($productA->product_type !== $productB->product_type)
                        <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                            Cannot merge products of different types.
                        </div>
                    @else
                        <div class="grid grid-cols-2 gap-4">
                            <button
                                type="button"
                                wire:click="$set('primaryId', {{ $productA->id }})"
                                class="rounded-lg border-2 p-4 text-left transition-colors {{ $primaryId === $productA->id ? 'border-[var(--blue)] bg-[color-mix(in_srgb,var(--blue)_8%,transparent)]' : 'border-[var(--card-border)] hover:border-[var(--text-muted)]' }}"
                            >
                                @if($primaryId === $productA->id)
                                    <span class="s-badge s-badge-green mb-2">Primary</span>
                                @else
                                    <span class="s-badge s-badge-zinc mb-2">Will be archived</span>
                                @endif
                                <div class="text-sm font-semibold" style="font-family: var(--font-display);">{{ $productA->name }}</div>
                                <div class="mt-2 space-y-1 text-xs text-[var(--text-muted)]" style="font-family: var(--font-mono);">
                                    <div>{{ $productA->stock_levels_count }} stock levels</div>
                                    <div>{{ $productA->accessories_count }} accessories</div>
                                    <div>{{ $productA->attachments_count }} files</div>
                                </div>
                            </button>

                            <button
                                type="button"
                                wire:click="$set('primaryId', {{ $productB->id }})"
                                class="rounded-lg border-2 p-4 text-left transition-colors {{ $primaryId === $productB->id ? 'border-[var(--blue)] bg-[color-mix(in_srgb,var(--blue)_8%,transparent)]' : 'border-[var(--card-border)] hover:border-[var(--text-muted)]' }}"
                            >
                                @if($primaryId === $productB->id)
                                    <span class="s-badge s-badge-green mb-2">Primary</span>
                                @else
                                    <span class="s-badge s-badge-zinc mb-2">Will be archived</span>
                                @endif
                                <div class="text-sm font-semibold" style="font-family: var(--font-display);">{{ $productB->name }}</div>
                                <div class="mt-2 space-y-1 text-xs text-[var(--text-muted)]" style="font-family: var(--font-mono);">
                                    <div>{{ $productB->stock_levels_count }} stock levels</div>
                                    <div>{{ $productB->accessories_count }} accessories</div>
                                    <div>{{ $productB->attachments_count }} files</div>
                                </div>
                            </button>
                        </div>
                    @endif
                @endif
            </div>

            <div class="s-modal-footer">
                @if($productA && !$productB)
                    <button class="s-btn s-btn-sm" type="button" x-on:click="open = false">Cancel</button>
                @elseif($productA && $productB)
                    <button class="s-btn s-btn-sm" type="button" wire:click="clearMergeTarget">Change Target</button>
                    <button class="s-btn s-btn-sm" type="button" x-on:click="open = false">Cancel</button>
                    @if($productA->product_type === $productB->product_type)
                        <button class="s-btn s-btn-sm s-btn-accent" type="button" wire:click="merge" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="merge">Merge Products</span>
                            <span wire:loading wire:target="merge">Merging...</span>
                        </button>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
