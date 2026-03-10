<div class="flex flex-col gap-6">
    <div class="flex w-full flex-col gap-2">
        <h1 class="s-auth-heading">Stores &amp; Locations</h1>
        <p class="s-auth-description">Add at least one store or warehouse. You can add more later.</p>
    </div>

    @foreach ($stores as $index => $store)
        <div wire:key="store-{{ $index }}" class="flex flex-col gap-4 rounded border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="flex items-center justify-between">
                <span class="s-auth-label">Store {{ $index + 1 }}</span>
                <div class="flex items-center gap-3">
                    @if (! $store['is_default'])
                        <flux:button wire:click="setDefaultStore({{ $index }})" variant="ghost" size="xs">
                            Set as default
                        </flux:button>
                    @else
                        <span class="s-badge s-badge-green">Default</span>
                    @endif

                    @if (count($stores) > 1)
                        <flux:button wire:click="removeStore({{ $index }})" variant="ghost" size="sm" icon="x-mark" />
                    @endif
                </div>
            </div>

            <flux:input wire:model="stores.{{ $index }}.name" label="Store Name" placeholder="Main Warehouse" required />

            <flux:input wire:model="stores.{{ $index }}.street" label="Street" placeholder="123 High Street" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="stores.{{ $index }}.city" label="City" placeholder="London" />
                <flux:input wire:model="stores.{{ $index }}.county" label="County / State" placeholder="Greater London" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="stores.{{ $index }}.postcode" label="Postcode" placeholder="SW1A 1AA" />
                <flux:select wire:model="stores.{{ $index }}.country_code" label="Country">
                    <flux:select.option value="">—</flux:select.option>
                    @foreach ($this->countryOptions() as $code => $name)
                        <flux:select.option value="{{ $code }}">{{ $name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    @endforeach

    <flux:button wire:click="addStore" variant="ghost" size="sm" icon="plus" class="self-start">
        Add another store
    </flux:button>
</div>
