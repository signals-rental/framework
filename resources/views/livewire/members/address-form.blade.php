<?php

use App\Actions\Members\CreateAddress;
use App\Actions\Members\UpdateAddress;
use App\Data\Members\CreateAddressData;
use App\Data\Members\UpdateAddressData;
use App\Models\Address;
use App\Models\Country;
use App\Models\ListName;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Member $member;
    public ?int $addressId = null;
    public string $name = '';
    public string $street = '';
    public string $city = '';
    public string $county = '';
    public string $postcode = '';
    public ?int $countryId = null;
    public ?int $typeId = null;
    public bool $isPrimary = false;

    public function mount(Member $member, ?Address $address = null): void
    {
        $this->member = $member;

        if ($address?->exists) {
            $this->addressId = $address->id;
            $this->name = $address->name ?? '';
            $this->street = $address->street ?? '';
            $this->city = $address->city ?? '';
            $this->county = $address->county ?? '';
            $this->postcode = $address->postcode ?? '';
            $this->countryId = $address->country_id;
            $this->typeId = $address->type_id;
            $this->isPrimary = $address->is_primary;
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:255'],
            'countryId' => ['nullable', 'integer', 'exists:countries,id'],
            'typeId' => ['nullable', 'integer', 'exists:list_values,id'],
            'isPrimary' => ['boolean'],
        ]);

        DB::transaction(function () {
            if ($this->isPrimary) {
                $this->member->addresses()->update(['is_primary' => false]);
            }

            if ($this->addressId) {
                $address = $this->member->addresses()->findOrFail($this->addressId);
                (new UpdateAddress)($address, UpdateAddressData::from([
                    'street' => $this->street ?: null,
                    'name' => $this->name ?: null,
                    'city' => $this->city ?: null,
                    'county' => $this->county ?: null,
                    'postcode' => $this->postcode ?: null,
                    'country_id' => $this->countryId,
                    'type_id' => $this->typeId,
                    'is_primary' => $this->isPrimary,
                ]));
            } else {
                (new CreateAddress)($this->member, CreateAddressData::from([
                    'street' => $this->street,
                    'name' => $this->name ?: null,
                    'city' => $this->city ?: null,
                    'county' => $this->county ?: null,
                    'postcode' => $this->postcode ?: null,
                    'country_id' => $this->countryId,
                    'type_id' => $this->typeId,
                    'is_primary' => $this->isPrimary,
                ]));
            }
        });

        $this->redirect(route('members.addresses', $this->member), navigate: true);
    }

    public function with(): array
    {
        $addressTypes = ListName::where('name', 'AddressType')->first()?->values()->where('is_active', true)->orderBy('sort_order')->get() ?? collect();

        return [
            'isEditing' => $this->addressId !== null,
            'countries' => Country::query()->active()->orderBy('name')->get(),
            'addressTypes' => $addressTypes,
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header :title="$isEditing ? 'Edit Address' : 'Add Address'">
        <x-slot:breadcrumbs>
            <a href="{{ route('members.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Members</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <a href="{{ route('members.show', $member) }}" wire:navigate class="text-[var(--link)] hover:underline">{{ $member->name }}</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <a href="{{ route('members.addresses', $member) }}" wire:navigate class="text-[var(--link)] hover:underline">Addresses</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>{{ $isEditing ? 'Edit' : 'Add' }}</span>
        </x-slot:breadcrumbs>
    </x-signals.page-header>

    <div class="flex-1 p-8 max-md:p-5 max-sm:p-3">
        <form wire:submit="save" class="max-w-2xl space-y-8">
            <x-signals.form-section title="Address Details">
                <div class="space-y-4">
                    <flux:input wire:model="name" label="Label" placeholder="e.g. Head Office" />
                    <flux:textarea wire:model="street" label="Street" rows="2" />
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="city" label="City" />
                        <flux:input wire:model="county" label="County / State" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="postcode" label="Postcode / ZIP" />
                        <flux:select wire:model="countryId" label="Country">
                            <option value="">Select country...</option>
                            @foreach($countries as $country)
                                <option value="{{ $country->id }}">{{ $country->name }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Classification">
                <div class="space-y-4">
                    <flux:select wire:model="typeId" label="Type">
                        <option value="">Select type...</option>
                        @foreach($addressTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:checkbox wire:model="isPrimary" label="Primary address" />
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Add Address' }}</flux:button>
                <flux:button variant="ghost" href="{{ route('members.addresses', $member) }}" wire:navigate>Cancel</flux:button>
            </div>
        </form>
    </div>
</section>
