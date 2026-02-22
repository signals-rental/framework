<div class="flex flex-col gap-6">
    <div class="flex w-full flex-col gap-2">
        <h1 class="signals-setup-heading">Review &amp; Confirm</h1>
        <p class="signals-setup-description">Check everything looks right before completing setup.</p>
    </div>

    {{-- Company --}}
    <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between">
            <span class="signals-step-indicator">Company</span>
            <button wire:click="goToStep(1)" class="signals-step-indicator text-blue-500 hover:underline">Edit</button>
        </div>
        <div class="rounded border border-zinc-200 p-4 dark:border-zinc-700">
            <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <dt class="text-zinc-500 dark:text-zinc-400">Name</dt>
                <dd>{{ $companyName }}</dd>
                <dt class="text-zinc-500 dark:text-zinc-400">Country</dt>
                <dd>{{ $this->countryOptions()[$countryCode] ?? $countryCode }}</dd>
                <dt class="text-zinc-500 dark:text-zinc-400">Timezone</dt>
                <dd>{{ $timezone }}</dd>
                <dt class="text-zinc-500 dark:text-zinc-400">Currency</dt>
                <dd>{{ $currency }}</dd>
                <dt class="text-zinc-500 dark:text-zinc-400">Tax</dt>
                <dd>{{ $taxRate }}% ({{ $taxLabel }})</dd>
                <dt class="text-zinc-500 dark:text-zinc-400">Date / Time</dt>
                <dd>{{ $dateFormat }} &middot; {{ $timeFormat }}</dd>
            </dl>
        </div>
    </div>

    {{-- Stores --}}
    <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between">
            <span class="signals-step-indicator">Stores</span>
            <button wire:click="goToStep(2)" class="signals-step-indicator text-blue-500 hover:underline">Edit</button>
        </div>
        <div class="rounded border border-zinc-200 p-4 dark:border-zinc-700">
            <ul class="flex flex-col gap-1 text-sm">
                @foreach ($stores as $store)
                    <li class="flex items-center gap-2">
                        <span>{{ $store['name'] }}</span>
                        @if ($store['is_default'])
                            <span class="inline-flex rounded bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-medium text-emerald-600 dark:text-emerald-400">Default</span>
                        @endif
                        @if ($store['city'])
                            <span class="text-zinc-400">&mdash; {{ $store['city'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- Profile --}}
    <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between">
            <span class="signals-step-indicator">Profile</span>
            <button wire:click="goToStep(3)" class="signals-step-indicator text-blue-500 hover:underline">Edit</button>
        </div>
        <div class="rounded border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="text-sm">
                @php $profileEnum = \App\Enums\FeatureProfile::from($profile); @endphp
                <p class="font-medium">{{ $profileEnum->label() }}</p>
                <p class="mt-1 text-zinc-500 dark:text-zinc-400">{{ $profileEnum->description() }}</p>
            </div>
        </div>
    </div>

    {{-- Branding --}}
    <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between">
            <span class="signals-step-indicator">Branding</span>
            <button wire:click="goToStep(4)" class="signals-step-indicator text-blue-500 hover:underline">Edit</button>
        </div>
        <div class="rounded border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="flex items-center gap-4 text-sm">
                <div class="flex gap-2">
                    <div class="h-6 w-10 rounded" style="background-color: {{ $primaryColour }}"></div>
                    <div class="h-6 w-10 rounded" style="background-color: {{ $accentColour }}"></div>
                </div>
                <span class="text-zinc-500 dark:text-zinc-400">
                    {{ $logo ? 'Logo uploaded' : 'No logo uploaded' }}
                </span>
            </div>
        </div>
    </div>

    {{-- Admin --}}
    <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between">
            <span class="signals-step-indicator">Admin Account</span>
            <button wire:click="goToStep(5)" class="signals-step-indicator text-blue-500 hover:underline">Edit</button>
        </div>
        <div class="rounded border border-zinc-200 p-4 dark:border-zinc-700">
            <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <dt class="text-zinc-500 dark:text-zinc-400">Name</dt>
                <dd>{{ $adminName }}</dd>
                <dt class="text-zinc-500 dark:text-zinc-400">Email</dt>
                <dd>{{ $adminEmail }}</dd>
            </dl>
        </div>
    </div>
</div>
