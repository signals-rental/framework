<div>
    <div class="flex items-center gap-4">
        {{-- Current icon preview --}}
        <div class="relative size-16 shrink-0 overflow-hidden rounded-full bg-[var(--bg-muted)]">
            @if($this->thumbDisplayUrl)
                <img
                    src="{{ $this->thumbDisplayUrl }}"
                    alt="Icon"
                    class="size-full object-cover"
                />
            @else
                <div class="flex size-full items-center justify-center text-[var(--text-muted)]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                </div>
            @endif

            {{-- Loading overlay --}}
            <div wire:loading wire:target="photo" class="absolute inset-0 flex items-center justify-center bg-black/50">
                <svg class="size-5 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </div>
        </div>

        {{-- Upload zone --}}
        <label class="s-upload-zone" style="width: 120px; height: 80px;">
            <svg class="s-upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
            </svg>
            <span class="s-upload-label" wire:loading.remove wire:target="photo">{{ $iconPath ? 'Replace' : 'Upload' }}</span>
            <span class="s-upload-label" wire:loading wire:target="photo">Uploading...</span>
            <input
                type="file"
                wire:model="photo"
                accept="image/jpeg,image/png,image/webp,image/gif"
                class="sr-only"
            />
        </label>

        @if($iconPath)
            <button
                type="button"
                x-on:click="$dispatch('open-modal', 'confirm-remove-icon')"
                class="s-btn s-btn-sm s-btn-ghost text-[var(--text-danger)]"
            >
                Remove
            </button>
        @endif
    </div>

    {{-- Validation error --}}
    @error('photo')
        <div class="mt-1 text-xs text-[var(--text-danger)]">{{ $message }}</div>
    @enderror

    {{-- Remove confirmation modal --}}
    @if($iconPath)
    <div
        x-data="{ open: false }"
        x-on:open-modal.window="if ($event.detail === 'confirm-remove-icon') open = true"
        x-show="open"
        x-cloak
    >
        <div class="s-modal-backdrop" x-transition.opacity>
            <div class="s-modal-sm s-modal" x-on:click.outside="open = false">
                <div class="s-modal-header">
                    <span class="s-modal-title">Remove Icon</span>
                </div>
                <div class="s-modal-body">
                    <p>Are you sure you want to remove the profile image?</p>
                </div>
                <div class="s-modal-footer">
                    <button x-on:click="open = false" class="s-btn s-btn-sm">Cancel</button>
                    <button
                        wire:click="removeIcon"
                        x-on:click="open = false"
                        class="s-btn s-btn-sm s-btn-danger"
                    >
                        Remove
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
