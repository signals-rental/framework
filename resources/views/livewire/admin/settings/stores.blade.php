<?php

use App\Data\Reference\CountryData;
use App\Models\Store;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    /** @var \Illuminate\Database\Eloquent\Collection<int, Store> */
    public $stores;

    public ?int $editingStoreId = null;

    public string $storeName = '';
    public string $storeStreet = '';
    public string $storeCity = '';
    public string $storeCounty = '';
    public string $storePostcode = '';
    public string $storeCountryCode = '';

    public function mount(): void
    {
        $this->loadStores();
    }

    public function loadStores(): void
    {
        $this->stores = Store::query()->orderBy('name')->get();
    }

    public function openCreateModal(): void
    {
        $this->resetStoreForm();
        $this->editingStoreId = null;
        $this->storeCountryCode = (string) settings('company.country_code', '');
        $this->dispatch('open-store-modal');
    }

    public function openEditModal(int $storeId): void
    {
        $store = Store::findOrFail($storeId);
        $this->editingStoreId = $store->id;
        $this->storeName = $store->name;
        $this->storeStreet = $store->street ?? '';
        $this->storeCity = $store->city ?? '';
        $this->storeCounty = $store->county ?? '';
        $this->storePostcode = $store->postcode ?? '';
        $this->storeCountryCode = $store->country_code ?? '';
        $this->dispatch('open-store-modal');
    }

    public function saveStore(): void
    {
        $validated = $this->validate([
            'storeName' => ['required', 'string', 'max:255'],
            'storeStreet' => ['nullable', 'string', 'max:255'],
            'storeCity' => ['nullable', 'string', 'max:255'],
            'storeCounty' => ['nullable', 'string', 'max:255'],
            'storePostcode' => ['nullable', 'string', 'max:20'],
            'storeCountryCode' => ['required', 'string', 'size:2'],
        ]);

        $data = [
            'name' => $validated['storeName'],
            'street' => $validated['storeStreet'],
            'city' => $validated['storeCity'],
            'county' => $validated['storeCounty'],
            'postcode' => $validated['storePostcode'],
            'country_code' => $validated['storeCountryCode'],
        ];

        if ($this->editingStoreId) {
            Store::findOrFail($this->editingStoreId)->update($data);
        } else {
            $isFirst = Store::count() === 0;
            Store::create(array_merge($data, ['is_default' => $isFirst]));
        }

        $this->dispatch('close-store-modal');
        $this->resetStoreForm();
        $this->loadStores();
    }

    public function setDefault(int $storeId): void
    {
        $store = Store::findOrFail($storeId);

        $store->getConnection()->transaction(function () use ($store) {
            Store::query()->update(['is_default' => false]);
            $store->update(['is_default' => true]);
        });

        $this->loadStores();
    }

    public function deleteStore(int $storeId): void
    {
        $store = Store::findOrFail($storeId);

        if ($store->is_default) {
            $this->addError('deleteStore', 'The default store cannot be deleted.');

            return;
        }

        $store->delete();
        $this->loadStores();
    }

    private function resetStoreForm(): void
    {
        $this->storeName = '';
        $this->storeStreet = '';
        $this->storeCity = '';
        $this->storeCounty = '';
        $this->storePostcode = '';
        $this->storeCountryCode = '';
        $this->editingStoreId = null;
    }
}; ?>

<section class="w-full">
    <x-admin.layout title="Stores" description="Manage your store locations.">
        <x-slot:actions>
            <flux:button variant="primary" wire:click="openCreateModal">Add Store</flux:button>
        </x-slot:actions>

        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>City</th>
                        <th>Country</th>
                        <th>Default</th>
                        <th class="w-[120px]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stores as $store)
                        <tr wire:key="store-{{ $store->id }}">
                            <td class="font-medium">{{ $store->name }}</td>
                            <td>{{ $store->city }}</td>
                            <td>{{ $store->country_code }}</td>
                            <td>
                                @if($store->is_default)
                                    <span class="s-badge s-badge-green">Default</span>
                                @else
                                    <button wire:click="setDefault({{ $store->id }})" class="s-btn-ghost s-btn-xs">
                                        Set Default
                                    </button>
                                @endif
                            </td>
                            <td class="text-right">
                                <button wire:click="openEditModal({{ $store->id }})" class="s-btn s-btn-ghost s-btn-sm" title="Edit">
                                    <flux:icon.pencil-square class="w-4 h-4" />
                                </button>
                                @unless($store->is_default)
                                    <button wire:click="deleteStore({{ $store->id }})"
                                            wire:confirm="Are you sure you want to delete this store?"
                                            class="s-btn-ghost s-btn-xs text-[var(--red)]">
                                        Delete
                                    </button>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-[var(--text-muted)]">No stores configured.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Create / Edit Modal --}}
        <div x-data="{ open: false }"
             x-on:open-store-modal.window="open = true"
             x-on:close-store-modal.window="open = false">
            <template x-teleport="body">
                <div class="s-modal-backdrop"
                     x-show="open"
                     x-cloak
                     x-transition.opacity
                     x-on:click.self="open = false"
                     x-on:keydown.escape.window="open = false">
                    <div class="s-modal-md s-modal" x-trap.noscroll="open">
                        <div class="s-modal-header">
                            <span class="s-modal-title">
                                {{ $editingStoreId ? 'Edit Store' : 'Add Store' }}
                            </span>
                            <button class="s-modal-close" type="button" x-on:click="open = false">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>
                        <div class="s-modal-body">
                            <form wire:submit="saveStore" class="space-y-4">
                                <flux:input wire:model="storeName" label="Store Name" required />

                                <flux:input wire:model="storeStreet" label="Street Address" />

                                <div class="grid grid-cols-2 gap-4">
                                    <flux:input wire:model="storeCity" label="City" />
                                    <flux:input wire:model="storeCounty" label="County / State" />
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <flux:input wire:model="storePostcode" label="Postcode / ZIP" />
                                    <flux:select wire:model="storeCountryCode" label="Country" required>
                                        <option value="">Select country...</option>
                                        @foreach(CountryData::options() as $code => $countryName)
                                            <option value="{{ $code }}">{{ $countryName }}</option>
                                        @endforeach
                                    </flux:select>
                                </div>

                                <div class="flex justify-end gap-3 pt-2">
                                    <flux:button type="button" x-on:click="open = false">Cancel</flux:button>
                                    <flux:button variant="primary" type="submit">
                                        {{ $editingStoreId ? 'Update Store' : 'Add Store' }}
                                    </flux:button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </x-admin.layout>
</section>
