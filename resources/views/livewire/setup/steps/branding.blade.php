<div class="flex flex-col gap-6">
    <div class="flex w-full flex-col gap-2">
        <h1 class="signals-setup-heading">Branding</h1>
        <p class="signals-setup-description">Customise the look and feel. Upload your logo and pick your brand colours.</p>
    </div>

    <flux:field>
        <flux:label>Logo</flux:label>
        <flux:input type="file" wire:model="logo" accept="image/*" />
        <flux:description>PNG, JPG, or SVG up to 2MB.</flux:description>
        <flux:error name="logo" />
    </flux:field>

    @if ($logo)
        <div class="flex items-center gap-3">
            <img src="{{ $logo->temporaryUrl() }}" alt="Logo preview" class="h-12 w-auto rounded border border-zinc-200 dark:border-zinc-700" />
            <span class="signals-step-indicator">Preview</span>
        </div>
    @endif

    <div class="grid grid-cols-2 gap-4">
        <flux:field>
            <flux:label>Primary Colour</flux:label>
            <div class="flex items-center gap-3">
                <input type="color" wire:model.live="primaryColour" class="h-9 w-9 cursor-pointer rounded border border-zinc-200 bg-transparent p-0.5 dark:border-zinc-700" />
                <flux:input wire:model.live="primaryColour" placeholder="#1e3a5f" maxlength="7" class="font-mono" />
            </div>
            <flux:error name="primaryColour" />
        </flux:field>

        <flux:field>
            <flux:label>Accent Colour</flux:label>
            <div class="flex items-center gap-3">
                <input type="color" wire:model.live="accentColour" class="h-9 w-9 cursor-pointer rounded border border-zinc-200 bg-transparent p-0.5 dark:border-zinc-700" />
                <flux:input wire:model.live="accentColour" placeholder="#3b82f6" maxlength="7" class="font-mono" />
            </div>
            <flux:error name="accentColour" />
        </flux:field>
    </div>

    <div class="flex gap-3">
        <div class="h-8 w-16 rounded" style="background-color: {{ $primaryColour }}"></div>
        <div class="h-8 w-16 rounded" style="background-color: {{ $accentColour }}"></div>
        <span class="signals-step-indicator self-center">Colour preview</span>
    </div>
</div>
