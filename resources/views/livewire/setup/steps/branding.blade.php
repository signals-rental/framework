<div class="flex flex-col gap-6">
    <div class="flex w-full flex-col gap-2">
        <h1 class="s-auth-heading">Branding</h1>
        <p class="s-auth-description">Customise the look and feel. Upload your logo and pick your brand colours.</p>
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
            <span class="s-auth-label">Preview</span>
        </div>
    @endif

    <div class="grid grid-cols-2 gap-4">
        <flux:field>
            <flux:label>Primary Colour</flux:label>
            <x-signals.colour-picker interactive name="primaryColour" :color="$primaryColour" />
            <flux:error name="primaryColour" />
        </flux:field>

        <flux:field>
            <flux:label>Accent Colour</flux:label>
            <x-signals.colour-picker interactive name="accentColour" :color="$accentColour" />
            <flux:error name="accentColour" />
        </flux:field>
    </div>

    <div class="flex items-center gap-3">
        <x-signals.colour-picker :color="$primaryColour" />
        <x-signals.colour-picker :color="$accentColour" />
        <span class="s-auth-label">Colour preview</span>
    </div>
</div>
