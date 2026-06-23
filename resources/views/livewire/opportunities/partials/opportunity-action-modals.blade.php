{{--
    Shared opportunity Actions modals (change-status picker + confirm-action).

    Included by the Show (overview) page AND every opportunity tab page so the
    Actions split-button in the shared header (showActions => true) works
    identically everywhere. The wire-methods + computed data behind these modals
    live in App\Livewire\Concerns\HasOpportunityActions, which each page `use`s.

    Expects the following from the using component's with():
      - $canChangeStatus  bool
      - $statusOptions    list<array{value:int,label:string}>
      - $pendingLabel     string  (public property on the trait)
      - $pendingMessage   string  (public property on the trait)
--}}

{{-- ============================================================ --}}
{{--  CHANGE-STATUS PICKER MODAL                                   --}}
{{--                                                               --}}
{{--  Offers the legal target statuses for the CURRENT state       --}}
{{--  (derived from the OpportunityStatus enum, never a hardcoded  --}}
{{--  matrix). Calls ChangeOpportunityStatus; an illegal move the  --}}
{{--  event guard rejects surfaces as a flashed 422.               --}}
{{-- ============================================================ --}}
@if($canChangeStatus ?? false)
    <x-signals.modal name="change-status" title="{{ __('Change status') }}">
        @if(empty($statusOptions))
            <p class="text-sm text-[var(--text-muted)]">{{ __('There are no other statuses available for the current state.') }}</p>
        @else
            <p class="mb-3 text-sm text-[var(--text-muted)]">
                {{ __('Move this :state to a different status.', ['state' => strtolower($opportunity->state->label())]) }}
            </p>
            <div class="space-y-2">
                @foreach($statusOptions as $option)
                    <button type="button"
                            wire:key="status-option-{{ $option['value'] }}"
                            wire:click="changeStatus({{ $option['value'] }})"
                            wire:loading.attr="disabled" wire:target="changeStatus"
                            class="s-btn s-btn-outline-blue w-full justify-start">
                        <span wire:loading.remove wire:target="changeStatus({{ $option['value'] }})">{{ $option['label'] }}</span>
                        <span wire:loading wire:target="changeStatus({{ $option['value'] }})" class="inline-flex items-center gap-1.5">
                            <x-signals.spinner size="xs" /> {{ __('Working…') }}
                        </span>
                    </button>
                @endforeach
            </div>
        @endif

        <x-slot:footer>
            <button type="button" x-on:click="$dispatch('close-modal', 'change-status')" class="s-btn s-btn-ghost">{{ __('Cancel') }}</button>
        </x-slot:footer>
    </x-signals.modal>
@endif

{{-- ============================================================ --}}
{{--  SHARED ACTION-CONFIRM MODAL (B1)                             --}}
{{--                                                               --}}
{{--  A single styled modal confirms EVERY Actions split-button    --}}
{{--  item (clone, archive, convert×2, reinstate, revert, unlock)  --}}
{{--  in place of per-item native `wire:confirm` dialogs. Each     --}}
{{--  header item stages the pending action (prepareAction) + opens --}}
{{--  this modal; Confirm calls confirmPendingAction(), which       --}}
{{--  dispatches to the staged, whitelisted wire method and closes  --}}
{{--  the modal. Mirrors the index-page archive-confirm styling.    --}}
{{-- ============================================================ --}}
<x-signals.modal name="confirm-action" title="{{ $pendingLabel ?: __('Confirm action') }}" size="sm">
    <p class="text-sm text-[var(--text-muted)]">{{ $pendingMessage ?: __('Are you sure?') }}</p>

    <x-slot:footer>
        <button type="button" x-on:click="$dispatch('close-modal', 'confirm-action')"
                wire:loading.attr="disabled" wire:target="confirmPendingAction"
                class="s-btn s-btn-sm">{{ __('Cancel') }}</button>
        <button type="button"
                wire:click="confirmPendingAction"
                wire:loading.attr="disabled" wire:target="confirmPendingAction"
                class="s-btn s-btn-sm s-btn-primary">
            <span wire:loading.remove wire:target="confirmPendingAction">{{ $pendingLabel ?: __('Confirm') }}</span>
            <span wire:loading wire:target="confirmPendingAction" class="inline-flex items-center gap-1.5">
                <x-signals.spinner size="xs" /> {{ __('Working…') }}
            </span>
        </button>
    </x-slot:footer>
</x-signals.modal>
