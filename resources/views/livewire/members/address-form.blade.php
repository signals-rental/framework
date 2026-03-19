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
    public ?string $what3words = null;
    public ?string $latitude = null;
    public ?string $longitude = null;

    public function mount(Member $member, ?Address $address = null): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links', 'organisations', 'contacts']);

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
            $this->latitude = $address->latitude ? (string) $address->latitude : null;
            $this->longitude = $address->longitude ? (string) $address->longitude : null;
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
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
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
                    'latitude' => $this->latitude ? (float) $this->latitude : null,
                    'longitude' => $this->longitude ? (float) $this->longitude : null,
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
                    'latitude' => $this->latitude ? (float) $this->latitude : null,
                    'longitude' => $this->longitude ? (float) $this->longitude : null,
                ]));
            }
        });

        $this->redirect(route('members.information', $this->member), navigate: true);
    }

    public function lookupWhat3words(): void
    {
        $this->validate([
            'what3words' => ['required', 'string', 'regex:/^[a-zA-Z]+\.[a-zA-Z]+\.[a-zA-Z]+$/'],
        ]);

        $service = app(\App\Services\What3WordsService::class);
        $result = $service->convertToCoordinates($this->what3words);

        if ($result === null) {
            $this->addError('what3words', 'Could not resolve what3words address. Check the address and your API key in Settings > Integrations.');

            return;
        }

        $this->latitude = (string) $result['lat'];
        $this->longitude = (string) $result['lng'];
    }

    public function updateCoordinates(string $lat, string $lng): void
    {
        if (is_numeric($lat) && is_numeric($lng)) {
            $this->latitude = $lat;
            $this->longitude = $lng;
        }
    }

    public function geocodeFromAddress(): void
    {
        $parts = array_filter([
            $this->street,
            $this->city,
            $this->county,
            $this->postcode,
            $this->countryId ? \App\Models\Country::find($this->countryId)?->name : null,
        ]);

        if (empty($parts)) {
            $this->addError('latitude', 'Enter an address before geocoding.');

            return;
        }

        $service = app(\App\Services\What3WordsService::class);
        $coords = $service->geocodeAddress(implode(', ', $parts));

        if ($coords === null) {
            $this->addError('latitude', 'Could not geocode this address. Try adding more detail.');

            return;
        }

        $this->latitude = (string) $coords['lat'];
        $this->longitude = (string) $coords['lng'];
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
    @include('livewire.members.partials.member-header', ['member' => $member, 'subpage' => $isEditing ? 'Edit Address' : 'Add Address'])
    @include('livewire.members.partials.member-tabs', ['member' => $member, 'activeTab' => 'information'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
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
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Country</label>
                            <x-signals.combobox
                                name="countryId"
                                :value="$countryId"
                                :options="$countries->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->values()->all()"
                                placeholder="Search countries..."
                                x-on:combobox-selected.window="if ($event.detail.name === 'countryId') $wire.set('countryId', $event.detail.value)"
                            />
                            @error('countryId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
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

            <x-signals.form-section title="Geocoding">
                <div class="space-y-4">
                    {{-- Geocode from address button --}}
                    <div>
                        <flux:button type="button" wire:click="geocodeFromAddress" variant="filled" class="w-full">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            Geocode from Address
                        </flux:button>
                        <p class="mt-1 text-xs text-[var(--text-muted)]">Looks up coordinates and what3words from the address fields above.</p>
                    </div>

                    {{-- what3words manual lookup --}}
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">what3words</label>
                        <div class="flex gap-2">
                            <div class="flex-1">
                                <flux:input wire:model.live="what3words" placeholder="e.g. filled.count.soap" />
                            </div>
                            <flux:button type="button" wire:click="lookupWhat3words" variant="filled">Lookup</flux:button>
                        </div>
                        @error('what3words') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Coordinates --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <flux:input wire:model.live="latitude" label="Latitude" placeholder="e.g. 51.5074" />
                            @error('latitude') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <flux:input wire:model.live="longitude" label="Longitude" placeholder="e.g. -0.1278" />
                            @error('longitude') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Interactive Map --}}
                    @if($latitude && $longitude)
                        <div
                            class="rounded-lg border border-[var(--card-border)] overflow-hidden"
                            wire:ignore
                            x-data="{
                                map: null,
                                marker: null,
                                lat: {{ (float) $latitude }},
                                lng: {{ (float) $longitude }},
                                init() {
                                    if (!document.getElementById('leaflet-css')) {
                                        const link = document.createElement('link');
                                        link.id = 'leaflet-css';
                                        link.rel = 'stylesheet';
                                        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                                        document.head.appendChild(link);
                                    }
                                    const loadLeaflet = () => {
                                        if (window.L) return Promise.resolve();
                                        return new Promise((resolve, reject) => {
                                            const s = document.createElement('script');
                                            s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                                            s.onload = resolve;
                                            s.onerror = () => reject(new Error('Failed to load Leaflet'));
                                            document.head.appendChild(s);
                                        });
                                    };
                                    loadLeaflet().then(() => {
                                        this.map = L.map(this.$refs.mapEl, { scrollWheelZoom: true }).setView([this.lat, this.lng], 16);
                                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                            attribution: '&copy; OpenStreetMap',
                                            maxZoom: 19,
                                        }).addTo(this.map);
                                        this.marker = L.marker([this.lat, this.lng], { draggable: true }).addTo(this.map);
                                        this.marker.on('dragend', () => {
                                            const pos = this.marker.getLatLng();
                                            this.lat = Math.round(pos.lat * 1000000) / 1000000;
                                            this.lng = Math.round(pos.lng * 1000000) / 1000000;
                                            $wire.updateCoordinates(String(this.lat), String(this.lng));
                                        });
                                        this.map.on('click', (e) => {
                                            this.lat = Math.round(e.latlng.lat * 1000000) / 1000000;
                                            this.lng = Math.round(e.latlng.lng * 1000000) / 1000000;
                                            this.marker.setLatLng([this.lat, this.lng]);
                                            $wire.updateCoordinates(String(this.lat), String(this.lng));
                                        });
                                    }).catch(() => {
                                        const el = this.$refs.mapEl;
                                        el.style.display = 'flex';
                                        el.style.alignItems = 'center';
                                        el.style.justifyContent = 'center';
                                        el.style.color = 'var(--text-muted)';
                                        el.style.fontSize = '0.875rem';
                                        el.textContent = 'Map could not be loaded. Please check your connection and refresh.';
                                    });
                                }
                            }"
                        >
                            <div x-ref="mapEl" style="height: 300px; width: 100%; z-index: 0;"></div>
                            <div class="flex items-center justify-between bg-[var(--s-subtle)] px-3 py-1.5">
                                <span class="text-[10px] text-[var(--text-muted)]" style="font-family: var(--font-mono);">
                                    <span x-text="lat"></span>, <span x-text="lng"></span>
                                </span>
                                <a x-bind:href="'https://www.openstreetmap.org/?mlat=' + lat + '&mlon=' + lng + '#map=16/' + lat + '/' + lng" target="_blank" rel="noopener" class="text-[10px] text-[var(--link)] hover:underline">
                                    Open in OSM
                                </a>
                            </div>
                        </div>
                        <p class="text-xs text-[var(--text-muted)]">Drag the marker or click the map to update the location.</p>
                    @endif
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Add Address' }}</flux:button>
                <flux:button variant="ghost" href="{{ route('members.show', $member) }}" wire:navigate>Cancel</flux:button>
            </div>
        </form>
    </div>
</section>
