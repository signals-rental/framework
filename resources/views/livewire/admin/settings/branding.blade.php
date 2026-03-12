<?php

use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] #[Title('Branding')] class extends Component {
    use WithFileUploads;

    public string $primaryColour = '#1e3a5f';
    public string $accentColour = '#3b82f6';
    public $logo = null;
    public ?string $currentLogoPath = null;

    public function mount(): void
    {
        $this->primaryColour = (string) settings('branding.primary_colour', '#1e3a5f');
        $this->accentColour = (string) settings('branding.accent_colour', '#3b82f6');
        $this->currentLogoPath = settings('branding.logo_path');
    }

    public function save(): void
    {
        $this->validate([
            'primaryColour' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accentColour' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'image', 'max:2048', 'mimes:png,jpg,jpeg,svg,webp'],
        ]);

        $settings = [
            'branding.primary_colour' => $this->primaryColour,
            'branding.accent_colour' => $this->accentColour,
        ];

        if ($this->logo) {
            $path = $this->logo->store('branding', 'public');

            if ($path === false) {
                $this->addError('logo', 'Failed to upload logo. Please try again.');

                return;
            }

            $settings['branding.logo_path'] = $path;
            $this->currentLogoPath = $path;
            $this->logo = null;
        }

        settings()->setMany($settings);

        $this->dispatch('branding-settings-saved');
    }

    public function removeLogo(): void
    {
        if ($this->currentLogoPath) {
            Storage::disk('public')->delete($this->currentLogoPath);
        }

        settings()->set('branding.logo_path', null);
        $this->currentLogoPath = null;
    }
}; ?>

<section class="w-full">
    <x-admin.layout title="Branding" description="Customise your company's visual identity.">
        <x-signals.form-section title="Brand Colours">
            <form wire:submit="save" class="space-y-6">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--text-primary)]">Primary Colour</label>
                        <div class="flex items-center gap-3">
                            <input type="color" wire:model.live="primaryColour"
                                   class="h-9 w-9 cursor-pointer border border-[var(--card-border)] bg-transparent p-0.5">
                            <flux:input wire:model.live="primaryColour" class="flex-1" placeholder="#1e3a5f" />
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[var(--text-primary)]">Accent Colour</label>
                        <div class="flex items-center gap-3">
                            <input type="color" wire:model.live="accentColour"
                                   class="h-9 w-9 cursor-pointer border border-[var(--card-border)] bg-transparent p-0.5">
                            <flux:input wire:model.live="accentColour" class="flex-1" placeholder="#3b82f6" />
                        </div>
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[var(--text-primary)]">Company Logo</label>

                    @if($currentLogoPath)
                        <div class="mb-3 flex items-center gap-3">
                            <img src="{{ Storage::disk('public')->url($currentLogoPath) }}"
                                 alt="Company logo" class="h-12 max-w-[200px] object-contain">
                            <button type="button" wire:click="removeLogo" class="s-btn-ghost s-btn-xs text-[var(--red)]">
                                Remove
                            </button>
                        </div>
                    @endif

                    <label class="relative flex w-full cursor-pointer flex-col items-center justify-center gap-2 border-2 border-dashed border-[var(--s-border-sub)] bg-[var(--base)] p-6 transition-colors hover:border-[var(--green)] hover:bg-[var(--s-green-bg)]">
                        <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                               class="absolute inset-0 cursor-pointer opacity-0">
                        <flux:icon.arrow-up-tray class="!size-6 text-[var(--text-muted)]" />
                        <p class="text-sm text-[var(--text-secondary)]">
                            Drop your logo here or click to upload
                        </p>
                        <p class="text-xs text-[var(--text-muted)]">PNG, JPG, SVG or WebP. Max 2MB.</p>
                    </label>

                    <div wire:loading wire:target="logo" class="mt-2 text-sm text-[var(--text-muted)]">
                        Uploading...
                    </div>

                    @if($logo)
                        <p class="mt-2 text-sm text-[var(--text-secondary)]">
                            Selected: {{ $logo->getClientOriginalName() }}
                        </p>
                    @endif
                </div>

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit">Save Changes</flux:button>

                    <x-action-message on="branding-settings-saved">
                        Saved.
                    </x-action-message>
                </div>
            </form>
        </x-signals.form-section>
    </x-admin.layout>
</section>
