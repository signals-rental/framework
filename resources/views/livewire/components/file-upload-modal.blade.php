<div>
    @if($show)
    <div class="s-modal-backdrop"
        x-data="{ fileReady: false, uploading: false }"
        x-transition.opacity
        x-on:keydown.escape.window="$wire.close()"
        x-on:livewire-upload-start="uploading = true; fileReady = false"
        x-on:livewire-upload-finish="uploading = false; fileReady = true"
        x-on:livewire-upload-error="uploading = false; fileReady = false"
    >
        <div class="s-modal s-modal-sm" x-on:click.outside="$wire.close()">
            <div class="s-modal-header">
                <span class="s-modal-title">Upload File</span>
                <button class="s-modal-close" type="button" wire:click="close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="s-modal-body flex flex-col gap-4">
                <div class="s-field">
                    <label class="s-field-label" for="upload-attachment">File <span class="s-field-label-required">*</span></label>
                    <input type="file" wire:model="attachment" id="upload-attachment"
                        class="block w-full text-sm text-[var(--text-muted)] file:mr-3 file:rounded file:border-0 file:bg-[var(--s-subtle)] file:px-3 file:py-1.5 file:text-sm file:font-medium" />
                    <div class="mt-1 text-xs text-[var(--text-muted)]" x-show="uploading" x-cloak>Uploading file to server...</div>
                    @error('attachment') <div class="s-field-error">{{ $message }}</div> @enderror
                </div>
                <flux:select wire:model="category" label="Category">
                    <option value="">No category</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}">{{ $cat }}</option>
                    @endforeach
                </flux:select>
                <flux:textarea wire:model="description" label="Description" rows="2" placeholder="Optional description..." />
            </div>
            <div class="s-modal-footer">
                <button wire:click="close" class="s-btn s-btn-sm">Cancel</button>
                <button
                    wire:click="save"
                    x-bind:disabled="!fileReady || uploading"
                    x-bind:class="(!fileReady || uploading) ? 'opacity-50 cursor-not-allowed' : ''"
                    class="s-btn s-btn-sm s-btn-primary"
                >
                    <span wire:loading.remove wire:target="save">Upload</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
