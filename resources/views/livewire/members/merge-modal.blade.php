<div>
    <x-signals.modal name="merge-members" title="Merge Members" size="lg">
        @if($memberA && $memberB)
            <p class="text-sm text-[var(--text-secondary)] mb-4">
                Select which member to keep as the primary record. All data from the other member will be migrated, and the secondary member will be archived.
            </p>

            @if($memberA->membership_type !== $memberB->membership_type)
                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    Cannot merge members of different types.
                </div>
            @else
                <div class="grid grid-cols-2 gap-4">
                    {{-- Member A --}}
                    <button
                        type="button"
                        wire:click="$set('primaryId', {{ $memberA->id }})"
                        class="rounded-lg border-2 p-4 text-left transition-colors {{ $primaryId === $memberA->id ? 'border-[var(--green)] bg-green-50/50' : 'border-[var(--card-border)] hover:border-[var(--text-muted)]' }}"
                    >
                        @if($primaryId === $memberA->id)
                            <span class="s-badge s-badge-green mb-2">Primary</span>
                        @else
                            <span class="s-badge s-badge-zinc mb-2">Will be archived</span>
                        @endif
                        <div class="text-sm font-semibold" style="font-family: var(--font-display);">{{ $memberA->name }}</div>
                        <div class="mt-2 space-y-1 text-xs text-[var(--text-muted)]" style="font-family: var(--font-mono);">
                            <div>{{ $memberA->addresses_count }} addresses</div>
                            <div>{{ $memberA->emails_count }} emails</div>
                            <div>{{ $memberA->phones_count }} phones</div>
                            <div>{{ $memberA->links_count }} links</div>
                            <div>{{ $memberA->attachments_count }} files</div>
                        </div>
                    </button>

                    {{-- Member B --}}
                    <button
                        type="button"
                        wire:click="$set('primaryId', {{ $memberB->id }})"
                        class="rounded-lg border-2 p-4 text-left transition-colors {{ $primaryId === $memberB->id ? 'border-[var(--green)] bg-green-50/50' : 'border-[var(--card-border)] hover:border-[var(--text-muted)]' }}"
                    >
                        @if($primaryId === $memberB->id)
                            <span class="s-badge s-badge-green mb-2">Primary</span>
                        @else
                            <span class="s-badge s-badge-zinc mb-2">Will be archived</span>
                        @endif
                        <div class="text-sm font-semibold" style="font-family: var(--font-display);">{{ $memberB->name }}</div>
                        <div class="mt-2 space-y-1 text-xs text-[var(--text-muted)]" style="font-family: var(--font-mono);">
                            <div>{{ $memberB->addresses_count }} addresses</div>
                            <div>{{ $memberB->emails_count }} emails</div>
                            <div>{{ $memberB->phones_count }} phones</div>
                            <div>{{ $memberB->links_count }} links</div>
                            <div>{{ $memberB->attachments_count }} files</div>
                        </div>
                    </button>
                </div>
            @endif

            <x-slot:footer>
                <button class="s-btn s-btn-sm" type="button" x-on:click="$dispatch('close-modal', 'merge-members')">Cancel</button>
                @if($memberA->membership_type === $memberB->membership_type)
                    <button class="s-btn s-btn-sm s-btn-accent" type="button" wire:click="merge" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="merge">Merge Members</span>
                        <span wire:loading wire:target="merge">Merging...</span>
                    </button>
                @endif
            </x-slot:footer>
        @endif
    </x-signals.modal>
</div>
