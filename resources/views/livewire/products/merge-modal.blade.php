<div>
    <x-signals.modal name="merge-products" title="Merge Products" size="lg">
        @if($productA && $productB)
            <p class="text-sm text-[var(--text-secondary)] mb-4">
                Select which product to keep as the primary record. All data from the other product will be migrated, and the secondary product will be archived.
            </p>

            @if($productA->product_type !== $productB->product_type)
                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    Cannot merge products of different types.
                </div>
            @else
                <div class="grid grid-cols-2 gap-4">
                    {{-- Product A --}}
                    <button
                        type="button"
                        wire:click="$set('primaryId', {{ $productA->id }})"
                        class="rounded-lg border-2 p-4 text-left transition-colors {{ $primaryId === $productA->id ? 'border-[var(--green)] bg-green-50/50' : 'border-[var(--card-border)] hover:border-[var(--text-muted)]' }}"
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

                    {{-- Product B --}}
                    <button
                        type="button"
                        wire:click="$set('primaryId', {{ $productB->id }})"
                        class="rounded-lg border-2 p-4 text-left transition-colors {{ $primaryId === $productB->id ? 'border-[var(--green)] bg-green-50/50' : 'border-[var(--card-border)] hover:border-[var(--text-muted)]' }}"
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

            <x-slot:footer>
                <button class="s-btn s-btn-sm" type="button" x-on:click="$dispatch('close-modal', 'merge-products')">Cancel</button>
                @if($productA->product_type === $productB->product_type)
                    <button class="s-btn s-btn-sm s-btn-accent" type="button" wire:click="merge" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="merge">Merge Products</span>
                        <span wire:loading wire:target="merge">Merging...</span>
                    </button>
                @endif
            </x-slot:footer>
        @endif
    </x-signals.modal>
</div>
