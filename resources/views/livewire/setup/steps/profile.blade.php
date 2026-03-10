<div class="flex flex-col gap-6">
    <div class="flex w-full flex-col gap-2">
        <h1 class="s-auth-heading">Feature Profile</h1>
        <p class="s-auth-description">Choose a profile that matches your business. You can enable or disable modules later.</p>
    </div>

    <flux:radio.group wire:model.live="profile" variant="cards">
        @foreach ($this->profileOptions() as $value => $option)
            <flux:radio
                value="{{ $value }}"
                label="{{ $option['label'] }}"
                description="{{ $option['description'] }}"
            />
        @endforeach
    </flux:radio.group>

    <div class="flex flex-col gap-3">
        <span class="s-auth-label">Enabled Modules</span>
        <div class="flex flex-wrap gap-2">
            @foreach ($this->selectedProfileModules() as $module => $enabled)
                <span @class([
                    's-badge',
                    's-badge-green' => $enabled,
                    's-badge-draft line-through' => ! $enabled,
                ])>
                    {{ ucfirst($module) }}
                </span>
            @endforeach
        </div>
    </div>
</div>
