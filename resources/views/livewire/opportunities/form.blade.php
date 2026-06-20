<?php

use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\UpdateOpportunity;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\UpdateOpportunityData;
use App\Livewire\Concerns\LoadsCustomFieldValues;
use App\Livewire\Concerns\ReKeysCustomFieldErrors;
use App\Models\Currency;
use App\Models\CustomField;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use App\Services\Api\RansackFilter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Opportunity create/edit form — HEADER fields only (M8-3a).
 *
 * Serves both opportunities.create and opportunities.edit. Builds a Create/Update
 * DTO from the form state and calls the SAME action classes the API uses
 * (CreateOpportunity / UpdateOpportunity), which own the Gate::authorize() check.
 * The line-item editor is a separate chunk (M8-3b) and attaches to the Show /
 * Line Items tab, not this form.
 *
 * Field → DTO mapping (only fields the DTOs accept are surfaced):
 *   subject               → subject
 *   member picker         → member_id
 *   venue picker          → venue_id
 *   owner select          → owned_by
 *   store select          → store_id
 *   currency select       → currency            (CREATE-ONLY; UpdateOpportunityData has no currency)
 *   reference             → reference
 *   description           → description
 *   external_description  → external_description
 *   starts_at/ends_at     → starts_at / ends_at
 *   charge dates          → charge_starts_at / charge_ends_at
 *   tag input             → tag_list
 *   prices_include_tax    → prices_include_tax  (CREATE-ONLY; rendered read-only on edit)
 *   custom fields editor  → custom_fields
 *
 * The opportunity NUMBER is auto-generated (OpportunityNumberGenerator /
 * OpportunitySettings) and is never exposed as an input.
 */
new #[Layout('components.layouts.app')] class extends Component
{
    use LoadsCustomFieldValues;
    use ReKeysCustomFieldErrors;

    public ?int $opportunityId = null;

    public string $subject = '';

    public ?int $memberId = null;

    public ?int $venueId = null;

    public ?int $storeId = null;

    public ?int $ownedBy = null;

    public string $currency = 'GBP';

    public ?string $reference = null;

    public string $description = '';

    public string $externalDescription = '';

    public ?string $startsAt = null;

    public ?string $endsAt = null;

    public ?string $chargeStartsAt = null;

    public ?string $chargeEndsAt = null;

    public bool $pricesIncludeTax = false;

    /** @var array<int, string> */
    public array $tags = [];

    // Member-picker autocomplete state.
    public string $memberSearch = '';

    public string $memberSelectedName = '';

    // Venue-picker autocomplete state.
    public string $venueSearch = '';

    public string $venueSelectedName = '';

    /** @var array<string, mixed> */
    public array $customFieldValues = [];

    public function mount(?Opportunity $opportunity = null): void
    {
        if ($opportunity?->exists) {
            Gate::authorize('opportunities.edit');

            $this->opportunityId = $opportunity->id;
            $this->subject = $opportunity->subject;
            $this->memberId = $opportunity->member_id;
            $this->venueId = $opportunity->venue_id;
            $this->storeId = $opportunity->store_id;
            $this->ownedBy = $opportunity->owned_by;
            $this->currency = $opportunity->currency_code ?? 'GBP';
            $this->reference = $opportunity->reference;
            $this->description = $opportunity->description ?? '';
            $this->externalDescription = $opportunity->external_description ?? '';
            $this->startsAt = $opportunity->starts_at?->format('Y-m-d H:i');
            $this->endsAt = $opportunity->ends_at?->format('Y-m-d H:i');
            $this->chargeStartsAt = $opportunity->charge_starts_at?->format('Y-m-d H:i');
            $this->chargeEndsAt = $opportunity->charge_ends_at?->format('Y-m-d H:i');
            $this->pricesIncludeTax = (bool) $opportunity->prices_include_tax;
            $this->tags = $opportunity->tag_list ?? [];

            $this->customFieldValues = $this->loadCustomFieldValuesFrom($opportunity);
        } else {
            Gate::authorize('opportunities.create');

            /** @var User|null $currentUser */
            $currentUser = auth()->user();
            $this->ownedBy = $currentUser?->member_id;
            $this->currency = settings('company.base_currency', 'GBP');

            $defaultStore = Store::query()->where('is_default', true)->value('id');
            if (is_int($defaultStore)) {
                $this->storeId = $defaultStore;
            }
        }

        $this->resolveMemberName();
        $this->resolveVenueName();
    }

    /**
     * Member autocomplete results (organisations/contacts).
     *
     * @return Collection<int, Member>
     */
    public function getMemberOptionsProperty(): Collection
    {
        if (trim($this->memberSearch) === '') {
            return collect();
        }

        $like = Member::query()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return Member::query()
            ->where('is_active', true)
            ->where('name', $like, '%'.RansackFilter::escapeLike($this->memberSearch).'%')
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name']);
    }

    public function selectMember(int $id): void
    {
        $member = Member::query()->where('is_active', true)->find($id);

        if ($member === null) {
            return;
        }

        $this->memberId = $member->id;
        $this->memberSelectedName = $member->name;
        $this->memberSearch = '';
    }

    public function clearMember(): void
    {
        $this->memberId = null;
        $this->memberSelectedName = '';
        $this->memberSearch = '';
    }

    /**
     * Venue autocomplete results.
     *
     * @return Collection<int, Member>
     */
    public function getVenueOptionsProperty(): Collection
    {
        if (trim($this->venueSearch) === '') {
            return collect();
        }

        $like = Member::query()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return Member::query()
            ->where('is_active', true)
            ->where('name', $like, '%'.RansackFilter::escapeLike($this->venueSearch).'%')
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name']);
    }

    public function selectVenue(int $id): void
    {
        $venue = Member::query()->where('is_active', true)->find($id);

        if ($venue === null) {
            return;
        }

        $this->venueId = $venue->id;
        $this->venueSelectedName = $venue->name;
        $this->venueSearch = '';
    }

    public function clearVenue(): void
    {
        $this->venueId = null;
        $this->venueSelectedName = '';
        $this->venueSearch = '';
    }

    public function save(): void
    {
        $this->validate([
            'subject' => ['required', 'string', 'max:255'],
        ]);

        try {
            if ($this->opportunityId) {
                $opportunity = Opportunity::findOrFail($this->opportunityId);
                $result = (new UpdateOpportunity)($opportunity, UpdateOpportunityData::from($this->updatePayload()));
            } else {
                $result = (new CreateOpportunity)(CreateOpportunityData::from($this->createPayload()));
            }

            $this->redirect(route('opportunities.show', $result->id), navigate: true);
        } catch (ValidationException $e) {
            $this->reKeyCustomFieldErrors($e, $this->customFields->pluck('name')->all());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function createPayload(): array
    {
        return [
            'subject' => $this->subject,
            'member_id' => $this->memberId,
            'venue_id' => $this->venueId,
            'store_id' => $this->storeId,
            'owned_by' => $this->ownedBy,
            'reference' => $this->reference ?: null,
            'description' => $this->description ?: null,
            'external_description' => $this->externalDescription ?: null,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'charge_starts_at' => $this->chargeStartsAt,
            'charge_ends_at' => $this->chargeEndsAt,
            'currency' => $this->currency,
            'prices_include_tax' => $this->pricesIncludeTax,
            'tag_list' => $this->tags !== [] ? array_values($this->tags) : null,
            'custom_fields' => $this->customFieldValues,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updatePayload(): array
    {
        // currency + prices_include_tax are create-only on the write surface, so
        // they are deliberately omitted from the update payload.
        return [
            'subject' => $this->subject,
            'member_id' => $this->memberId,
            'venue_id' => $this->venueId,
            'store_id' => $this->storeId,
            'owned_by' => $this->ownedBy,
            'reference' => $this->reference ?: null,
            'description' => $this->description ?: null,
            'external_description' => $this->externalDescription ?: null,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'charge_starts_at' => $this->chargeStartsAt,
            'charge_ends_at' => $this->chargeEndsAt,
            'tag_list' => array_values($this->tags),
            'custom_fields' => $this->customFieldValues,
        ];
    }

    /**
     * Active custom field definitions for the Opportunity module.
     *
     * @return Collection<int, CustomField>
     */
    #[Computed]
    public function customFields(): Collection
    {
        return CustomField::query()
            ->forModule('Opportunity')
            ->active()
            ->with(['group', 'listName.values'])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'isEditing' => $this->opportunityId !== null,
            'opportunity' => $this->opportunityId ? Opportunity::find($this->opportunityId) : null,
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
            'currencies' => Currency::query()->where('is_enabled', true)->orderBy('code')->get(['code', 'name']),
            'owners' => Member::query()->ofType(\App\Enums\MembershipType::User)->orderBy('name')->get(['id', 'name']),
            'groupedCustomFields' => $this->customFields->groupBy(fn (CustomField $f) => $f->group?->name ?? 'General'),
        ];
    }

    /**
     * Resolve the display name for the currently selected member.
     */
    private function resolveMemberName(): void
    {
        if ($this->memberId === null) {
            return;
        }

        $this->memberSelectedName = Member::query()->where('id', $this->memberId)->value('name') ?? '';
    }

    /**
     * Resolve the display name for the currently selected venue.
     */
    private function resolveVenueName(): void
    {
        if ($this->venueId === null) {
            return;
        }

        $this->venueSelectedName = Member::query()->where('id', $this->venueId)->value('name') ?? '';
    }
}; ?>

<section class="w-full">
    @if($isEditing && $opportunity)
        @include('livewire.opportunities.partials.opportunity-header', ['opportunity' => $opportunity, 'subpage' => 'Edit'])
        @include('livewire.opportunities.partials.opportunity-tabs', ['opportunity' => $opportunity, 'activeTab' => ''])
    @else
        <x-signals.page-header title="New Opportunity">
            <x-slot:breadcrumbs>
                <a href="{{ route('opportunities.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Opportunities</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <span>Create</span>
            </x-slot:breadcrumbs>
        </x-signals.page-header>
    @endif

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <form wire:submit="save">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; align-items: start;">

                {{-- ======================================== --}}
                {{-- LEFT COLUMN — Core header fields --}}
                {{-- ======================================== --}}
                <div class="space-y-6">
                    <x-signals.form-section title="Basic Info">
                        <div class="space-y-3">
                            <flux:input wire:model="subject" label="Subject" required />
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:select wire:model="storeId" label="Store">
                                    <option value="">None</option>
                                    @foreach($stores as $store)
                                        <option value="{{ $store->id }}">{{ $store->name }}</option>
                                    @endforeach
                                </flux:select>
                                <flux:input wire:model="reference" label="Reference" />
                            </div>
                            <flux:textarea wire:model="description" label="Description" rows="2" />
                            <flux:textarea wire:model="externalDescription" label="External Description" rows="2"
                                description="Shown on customer-facing documents." />
                        </div>
                    </x-signals.form-section>

                    <x-signals.form-section title="Hire Period">
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <div>
                                    <label class="s-field-label mb-1 block">Starts At</label>
                                    <x-signals.datetime-input
                                        :value="$startsAt"
                                        placeholder="Select date & time"
                                        x-on:input="if (typeof $event.detail === 'string' || $event.detail === null) $wire.set('startsAt', $event.detail)"
                                    />
                                </div>
                                <div>
                                    <label class="s-field-label mb-1 block">Ends At</label>
                                    <x-signals.datetime-input
                                        :value="$endsAt"
                                        placeholder="Select date & time"
                                        x-on:input="if (typeof $event.detail === 'string' || $event.detail === null) $wire.set('endsAt', $event.detail)"
                                    />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <div>
                                    <label class="s-field-label mb-1 block">Charge Starts At</label>
                                    <x-signals.datetime-input
                                        :value="$chargeStartsAt"
                                        placeholder="Select date & time"
                                        x-on:input="if (typeof $event.detail === 'string' || $event.detail === null) $wire.set('chargeStartsAt', $event.detail)"
                                    />
                                </div>
                                <div>
                                    <label class="s-field-label mb-1 block">Charge Ends At</label>
                                    <x-signals.datetime-input
                                        :value="$chargeEndsAt"
                                        placeholder="Select date & time"
                                        x-on:input="if (typeof $event.detail === 'string' || $event.detail === null) $wire.set('chargeEndsAt', $event.detail)"
                                    />
                                </div>
                            </div>
                        </div>
                    </x-signals.form-section>

                    <x-signals.form-section title="Pricing">
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                @if($isEditing)
                                    <div>
                                        <label class="s-field-label mb-1 block">Currency</label>
                                        <div class="flex h-9 items-center">
                                            <span class="s-badge s-badge-zinc">{{ $currency }}</span>
                                        </div>
                                    </div>
                                @else
                                    <flux:select wire:model="currency" label="Currency">
                                        @foreach($currencies as $currencyOption)
                                            <option value="{{ $currencyOption->code }}">{{ $currencyOption->code }} — {{ $currencyOption->name }}</option>
                                        @endforeach
                                    </flux:select>
                                @endif
                            </div>

                            @if($isEditing)
                                <div class="flex items-center gap-2 pt-1">
                                    <span class="s-field-label">Prices include tax:</span>
                                    <span class="s-badge {{ $pricesIncludeTax ? 's-badge-green' : 's-badge-zinc' }}">{{ $pricesIncludeTax ? 'Yes' : 'No' }}</span>
                                </div>
                            @else
                                <flux:checkbox wire:model="pricesIncludeTax" label="Prices include tax"
                                    description="Whether entered prices are tax-inclusive. Set at creation only." />
                            @endif
                        </div>
                    </x-signals.form-section>

                    {{-- Actions --}}
                    <div class="flex items-center gap-4 pt-2">
                        <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Create Opportunity' }}</flux:button>
                        <flux:button variant="ghost" href="{{ $isEditing ? route('opportunities.show', $opportunityId) : route('opportunities.index') }}" wire:navigate>Cancel</flux:button>
                    </div>
                </div>

                {{-- ======================================== --}}
                {{-- RIGHT COLUMN — Parties, tags, custom fields --}}
                {{-- ======================================== --}}
                <div class="space-y-6" style="position: sticky; top: 24px;">
                    <x-signals.form-section title="Parties">
                        <div class="space-y-3">
                            {{-- Member picker --}}
                            <div>
                                <label class="block text-sm font-medium mb-1">Member</label>
                                @if($memberSelectedName)
                                    <div class="flex items-center gap-2 rounded-lg border border-[var(--border)] bg-[var(--bg-secondary)] px-3 py-2">
                                        <span class="flex-1 truncate">{{ $memberSelectedName }}</span>
                                        <button type="button" wire:click="clearMember" class="text-[var(--text-muted)] hover:text-[var(--text-primary)] shrink-0">&times;</button>
                                    </div>
                                @else
                                    <div x-data="{ open: false }" x-on:click.away="open = false" class="relative">
                                        <flux:input
                                            wire:model.live.debounce.300ms="memberSearch"
                                            placeholder="Search members..."
                                            x-on:focus="open = true"
                                            x-on:input="open = true"
                                            autocomplete="off"
                                        />
                                        @if($this->memberOptions->isNotEmpty())
                                            <div x-show="open" x-cloak class="absolute z-50 mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--bg-primary)] shadow-lg max-h-60 overflow-y-auto">
                                                @foreach($this->memberOptions as $option)
                                                    <button
                                                        type="button"
                                                        wire:key="member-{{ $option->id }}"
                                                        wire:click="selectMember({{ $option->id }})"
                                                        x-on:click="open = false"
                                                        class="block w-full px-3 py-2 text-left text-sm hover:bg-[var(--bg-secondary)] transition-colors"
                                                    >
                                                        {{ $option->name }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- Venue picker --}}
                            <div>
                                <label class="block text-sm font-medium mb-1">Venue</label>
                                @if($venueSelectedName)
                                    <div class="flex items-center gap-2 rounded-lg border border-[var(--border)] bg-[var(--bg-secondary)] px-3 py-2">
                                        <span class="flex-1 truncate">{{ $venueSelectedName }}</span>
                                        <button type="button" wire:click="clearVenue" class="text-[var(--text-muted)] hover:text-[var(--text-primary)] shrink-0">&times;</button>
                                    </div>
                                @else
                                    <div x-data="{ open: false }" x-on:click.away="open = false" class="relative">
                                        <flux:input
                                            wire:model.live.debounce.300ms="venueSearch"
                                            placeholder="Search venues..."
                                            x-on:focus="open = true"
                                            x-on:input="open = true"
                                            autocomplete="off"
                                        />
                                        @if($this->venueOptions->isNotEmpty())
                                            <div x-show="open" x-cloak class="absolute z-50 mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--bg-primary)] shadow-lg max-h-60 overflow-y-auto">
                                                @foreach($this->venueOptions as $option)
                                                    <button
                                                        type="button"
                                                        wire:key="venue-{{ $option->id }}"
                                                        wire:click="selectVenue({{ $option->id }})"
                                                        x-on:click="open = false"
                                                        class="block w-full px-3 py-2 text-left text-sm hover:bg-[var(--bg-secondary)] transition-colors"
                                                    >
                                                        {{ $option->name }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <flux:select wire:model="ownedBy" label="Owner">
                                <option value="">None</option>
                                @foreach($owners as $owner)
                                    <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                                @endforeach
                            </flux:select>
                        </div>
                    </x-signals.form-section>

                    <x-signals.form-section title="Tags">
                        <x-signals.tag-input :value="$tags" placeholder="Add tag..." x-on:tags-changed="$wire.set('tags', $event.detail)" />
                    </x-signals.form-section>

                    @if($groupedCustomFields->isNotEmpty())
                        <x-signals.form-section title="Custom Fields">
                            <x-signals.custom-fields-editor :groupedCustomFields="$groupedCustomFields" />
                        </x-signals.form-section>
                    @endif
                </div>
            </div>
        </form>
    </div>
</section>
