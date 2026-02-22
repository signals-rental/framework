<div class="flex flex-col gap-6">
    <div class="flex w-full flex-col gap-2">
        <h1 class="signals-setup-heading">Feature Profile</h1>
        <p class="signals-setup-description">Choose a profile that matches your business. You can enable or disable modules later.</p>
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
        <span class="signals-step-indicator">Enabled Modules</span>
        <div class="flex flex-wrap gap-2">
            @foreach ($this->selectedProfileModules() as $module => $enabled)
                <span @class([
                    'inline-flex items-center rounded px-2 py-1 text-xs font-medium',
                    'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' => $enabled,
                    'bg-zinc-100 text-zinc-400 line-through dark:bg-zinc-800 dark:text-zinc-500' => ! $enabled,
                ])>
                    {{ ucfirst($module) }}
                </span>
            @endforeach
        </div>
    </div>
</div>
