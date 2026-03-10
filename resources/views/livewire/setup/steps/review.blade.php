<div class="flex flex-col gap-6">
    <div class="flex w-full flex-col gap-2">
        <h1 class="s-auth-heading">Review &amp; Confirm</h1>
        <p class="s-auth-description">Check everything looks right before completing setup.</p>
    </div>

    {{-- Company --}}
    <x-signals.card>
        <x-slot:headerActions>
            <div class="flex items-center justify-between w-full">
                <span class="s-auth-label">Company</span>
                <flux:button wire:click="goToStep(1)" variant="ghost" size="xs">Edit</flux:button>
            </div>
        </x-slot:headerActions>

        <x-signals.data-list :items="[
            ['label' => 'Name', 'value' => $companyName],
            ['label' => 'Country', 'value' => $this->countryOptions()[$countryCode] ?? $countryCode],
            ['label' => 'Timezone', 'value' => $timezone],
            ['label' => 'Currency', 'value' => $currency],
            ['label' => 'Tax', 'value' => $taxRate . '% (' . $taxLabel . ')'],
            ['label' => 'Date / Time', 'value' => $dateFormat . ' · ' . $timeFormat],
        ]" />
    </x-signals.card>

    {{-- Stores --}}
    <x-signals.card>
        <x-slot:headerActions>
            <div class="flex items-center justify-between w-full">
                <span class="s-auth-label">Stores</span>
                <flux:button wire:click="goToStep(2)" variant="ghost" size="xs">Edit</flux:button>
            </div>
        </x-slot:headerActions>

        <div class="flex flex-col gap-1">
            @foreach ($stores as $store)
                <div class="flex items-center gap-2 text-sm">
                    <span>{{ $store['name'] }}</span>
                    @if ($store['is_default'])
                        <span class="s-badge s-badge-green">Default</span>
                    @endif
                    @if ($store['city'])
                        <span class="text-zinc-400">&mdash; {{ $store['city'] }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </x-signals.card>

    {{-- Profile --}}
    <x-signals.card>
        <x-slot:headerActions>
            <div class="flex items-center justify-between w-full">
                <span class="s-auth-label">Profile</span>
                <flux:button wire:click="goToStep(3)" variant="ghost" size="xs">Edit</flux:button>
            </div>
        </x-slot:headerActions>

        <div class="text-sm">
            @php $profileEnum = \App\Enums\FeatureProfile::from($profile); @endphp
            <p class="font-medium">{{ $profileEnum->label() }}</p>
            <p class="mt-1 text-zinc-500 dark:text-zinc-400">{{ $profileEnum->description() }}</p>
        </div>
    </x-signals.card>

    {{-- Branding --}}
    <x-signals.card>
        <x-slot:headerActions>
            <div class="flex items-center justify-between w-full">
                <span class="s-auth-label">Branding</span>
                <flux:button wire:click="goToStep(4)" variant="ghost" size="xs">Edit</flux:button>
            </div>
        </x-slot:headerActions>

        <div class="flex items-center gap-4 text-sm">
            <div class="flex gap-2">
                <x-signals.colour-picker :color="$primaryColour" />
                <x-signals.colour-picker :color="$accentColour" />
            </div>
            <span class="text-zinc-500 dark:text-zinc-400">
                {{ $logo ? 'Logo uploaded' : 'No logo uploaded' }}
            </span>
        </div>
    </x-signals.card>

    {{-- Admin --}}
    <x-signals.card>
        <x-slot:headerActions>
            <div class="flex items-center justify-between w-full">
                <span class="s-auth-label">Admin Account</span>
                <flux:button wire:click="goToStep(5)" variant="ghost" size="xs">Edit</flux:button>
            </div>
        </x-slot:headerActions>

        <x-signals.data-list :items="[
            ['label' => 'Name', 'value' => $adminName],
            ['label' => 'Email', 'value' => $adminEmail],
        ]" />
    </x-signals.card>
</div>
