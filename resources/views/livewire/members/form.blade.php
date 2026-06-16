<?php

use App\Actions\Members\CreateMember;
use App\Actions\Members\UpdateMember;
use App\Data\Members\CreateMemberData;
use App\Data\Members\UpdateMemberData;
use App\Enums\MembershipType;
use App\Livewire\Concerns\LoadsCustomFieldValues;
use App\Livewire\Concerns\ReKeysCustomFieldErrors;
use App\Models\CustomField;
use App\Models\ListName;
use App\Models\ListValue;
use App\Models\Member;
use App\Models\OrganisationTaxClass;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    use LoadsCustomFieldValues;
    use ReKeysCustomFieldErrors;

    public ?int $memberId = null;

    // Basic info
    public string $name = '';

    public string $membershipType = 'contact';

    public bool $isActive = true;

    public string $description = '';

    public ?string $locale = null;

    public ?string $defaultCurrencyCode = null;

    // Resource fields
    public bool $bookable = false;

    public ?int $locationTypeId = null;

    public int $dayCost = 0;

    public int $hourCost = 0;

    public int $distanceCost = 0;

    public int $flatRateCost = 0;

    // Tax & legal
    public ?int $lawfulBasisTypeId = null;

    public ?int $saleTaxClassId = null;

    public ?int $purchaseTaxClassId = null;

    // Organisation membership fields
    public ?string $accountNumber = null;

    public ?string $taxNumber = null;

    public bool $isCash = false;

    public bool $isOnStop = false;

    public ?int $ratingId = null;

    public ?int $ownedBy = null;

    public ?int $priceCategoryId = null;

    public ?int $discountCategoryId = null;

    public ?int $invoiceTermId = null;

    public int $invoiceTermLength = 0;

    // Contact membership fields
    public ?string $title = null;

    public ?string $department = null;

    // Custom fields
    /** @var array<string, mixed> */
    public array $customFieldValues = [];

    public function mount(?Member $member = null): void
    {
        /** @var User|null $currentUser */
        $currentUser = auth()->user();
        $this->ownedBy = $currentUser?->member_id;

        // Pre-populate membership type from ?type= query param. User-type
        // members are created via invitation only, so 'user' is not accepted.
        $type = request()->query('type');
        if (is_string($type) && in_array($type, ['organisation', 'contact', 'venue'])) {
            $this->membershipType = $type;
        }

        if ($member?->exists) {
            $this->memberId = $member->id;
            $this->name = $member->name;
            $this->membershipType = $member->membership_type->value;
            $this->isActive = $member->is_active;
            $this->description = $member->description ?? '';
            $this->locale = $member->locale;
            $this->defaultCurrencyCode = $member->default_currency_code;
            $this->bookable = $member->bookable ?? false;
            $this->locationTypeId = $member->location_type;
            $this->dayCost = $member->day_cost ?? 0;
            $this->hourCost = $member->hour_cost ?? 0;
            $this->distanceCost = $member->distance_cost ?? 0;
            $this->flatRateCost = $member->flat_rate_cost ?? 0;
            $this->lawfulBasisTypeId = $member->lawful_basis_type_id;
            $this->saleTaxClassId = $member->sale_tax_class_id;
            $this->purchaseTaxClassId = $member->purchase_tax_class_id;
            $this->accountNumber = $member->account_number;
            $this->taxNumber = $member->tax_number;
            $this->isCash = $member->is_cash ?? false;
            $this->isOnStop = $member->is_on_stop ?? false;
            $this->ratingId = $member->rating;
            $this->ownedBy = $member->owned_by;
            $this->priceCategoryId = $member->price_category_id;
            $this->discountCategoryId = $member->discount_category_id;
            $this->invoiceTermId = $member->invoice_term_id;
            $this->invoiceTermLength = $member->invoice_term_length ?? 0;
            $this->title = $member->title;
            $this->department = $member->department;

            // Load existing custom field values
            $this->customFieldValues = $this->loadCustomFieldValuesFrom($member);
        }
    }

    public function save(): void
    {
        $nameUniqueRule = Rule::unique('members', 'name')
            ->where('membership_type', $this->membershipType);

        if ($this->memberId) {
            $nameUniqueRule->ignore($this->memberId);
        }

        // Creatable types exclude User — users are created via invitation only.
        $creatableTypes = array_values(array_filter(
            array_column(MembershipType::cases(), 'value'),
            fn (string $value): bool => $value !== MembershipType::User->value,
        ));

        $member = $this->memberId ? Member::find($this->memberId) : null;

        // When editing, the existing (immutable) type is always allowed even if
        // it is User — only the create surface forbids selecting User.
        $allowedTypes = $member
            ? array_values(array_unique([...$creatableTypes, $member->membership_type->value]))
            : $creatableTypes;

        $rules = [
            'name' => ['required', 'string', 'max:255', $nameUniqueRule],
            'membershipType' => ['required', 'string', Rule::in($allowedTypes)],
        ];

        // Membership type cannot be changed after creation
        if ($member) {
            if ($member->membership_type->value !== $this->membershipType) {
                throw ValidationException::withMessages([
                    'membershipType' => ['Membership type cannot be changed after creation.'],
                ]);
            }

            // A user-type member's name, profile image, active state and location
            // type are managed from the user's own profile — not from this internal
            // edit screen. Ignore any submitted changes so they stay read-only here.
            if ($member->membership_type === MembershipType::User) {
                $this->name = $member->name;
                $this->isActive = $member->is_active;
                $this->locationTypeId = $member->location_type;
            }
        }

        $this->validate($rules);

        $payload = [
            'name' => $this->name,
            'membership_type' => $this->membershipType,
            'is_active' => $this->isActive,
            'description' => $this->description ?: null,
            'locale' => $this->locale,
            'default_currency_code' => $this->defaultCurrencyCode,
            'bookable' => $this->bookable,
            'location_type' => (int) ($this->locationTypeId ?? 0),
            'day_cost' => $this->dayCost,
            'hour_cost' => $this->hourCost,
            'distance_cost' => $this->distanceCost,
            'flat_rate_cost' => $this->flatRateCost,
            'lawful_basis_type_id' => $this->lawfulBasisTypeId,
            'sale_tax_class_id' => $this->saleTaxClassId,
            'purchase_tax_class_id' => $this->purchaseTaxClassId,
            'account_number' => $this->accountNumber,
            'tax_number' => $this->taxNumber,
            'is_cash' => $this->isCash,
            'is_on_stop' => $this->isOnStop,
            'rating' => (int) ($this->ratingId ?? 0),
            'owned_by' => $this->ownedBy,
            'price_category_id' => $this->priceCategoryId,
            'discount_category_id' => $this->discountCategoryId,
            'invoice_term_id' => $this->invoiceTermId,
            'invoice_term_length' => $this->invoiceTermLength,
            'title' => $this->title,
            'department' => $this->department,
            'custom_fields' => $this->customFieldValues,
        ];

        try {
            if ($this->memberId) {
                $member = Member::findOrFail($this->memberId);
                $result = (new UpdateMember)($member, UpdateMemberData::from($payload));
            } else {
                $result = (new CreateMember)(CreateMemberData::from($payload));
            }

            $this->redirect(route('members.show', $result->id), navigate: true);
        } catch (ValidationException $e) {
            $this->reKeyCustomFieldErrors($e, $this->referenceData['customFields']->pluck('name')->all());
        }
    }

    /**
     * @return Collection<int, ListValue>
     */
    private function listValues(string $listName): Collection
    {
        return ListName::query()
            ->where('name', $listName)
            ->first()
            ?->values()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get() ?? collect();
    }

    #[Computed]
    public function referenceData(): array
    {
        return [
            'taxClasses' => OrganisationTaxClass::query()->orderBy('name')->get(),
            'ownerOptions' => Member::query()->ofType(MembershipType::User)->orderBy('name')->get(['id', 'name']),
            'lawfulBasisTypes' => $this->listValues('Lawful Basis Type'),
            'locationTypes' => $this->listValues('Location Type'),
            'ratings' => $this->listValues('Rating'),
            'invoiceTerms' => $this->listValues('Invoice Term'),
            'locales' => $this->listValues('Locale'),
            'currencies' => $this->listValues('Currency'),
            'customFields' => CustomField::query()
                ->forModule('Member')
                ->active()
                ->with(['group', 'listName.values'])
                ->orderBy('sort_order')
                ->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $isEditing = $this->memberId !== null;
        $ref = $this->referenceData;

        return [
            'isEditing' => $isEditing,
            'member' => $isEditing ? Member::find($this->memberId)?->loadCount(['addresses', 'emails', 'phones', 'links', 'organisations', 'contacts']) : null,
            // User-type members are created via invitation, so 'User' is never
            // offered as a selectable type in the create/edit form.
            'membershipTypes' => array_values(array_filter(
                MembershipType::cases(),
                fn (MembershipType $t): bool => $t !== MembershipType::User,
            )),
            'isUserMember' => $this->membershipType === MembershipType::User->value,
            'taxClasses' => $ref['taxClasses'],
            'ownerOptions' => $ref['ownerOptions'],
            'lawfulBasisTypes' => $ref['lawfulBasisTypes'],
            'locationTypes' => $ref['locationTypes'],
            'ratings' => $ref['ratings'],
            'invoiceTerms' => $ref['invoiceTerms'],
            'locales' => $ref['locales'],
            'currencies' => $ref['currencies'],
            'isOrg' => in_array($this->membershipType, ['organisation', 'venue']),
            'isContact' => $this->membershipType === 'contact',
            'customFields' => $ref['customFields'],
            'groupedCustomFields' => $ref['customFields']->groupBy(fn ($f) => $f->group?->name ?? 'General'),
        ];
    }
}; ?>

<section class="w-full">
    @if($isEditing && $member)
        @include('livewire.members.partials.member-header', ['member' => $member, 'subpage' => 'Edit'])
        @include('livewire.members.partials.member-tabs', ['member' => $member, 'activeTab' => ''])
    @else
        <x-signals.page-header :title="'Create ' . (\App\Enums\MembershipType::tryFrom($membershipType)?->label() ?? 'Member')">
            <x-slot:breadcrumbs>
                <a href="{{ route('members.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Members</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <span>Create</span>
            </x-slot:breadcrumbs>
        </x-signals.page-header>
    @endif

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <form wire:submit="save">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; align-items: start;">

                {{-- ======================================== --}}
                {{-- LEFT COLUMN — Core fields --}}
                {{-- ======================================== --}}
                <div class="space-y-6">

                    {{-- Icon Upload (edit mode only) --}}
                    @if($isEditing && $member)
                        <x-signals.form-section title="Profile Image">
                            @if($isUserMember)
                                <div class="flex items-center gap-4">
                                    <x-signals.entity-icon :model="$member" :size="56" />
                                    <div class="flex flex-col gap-1">
                                        <p class="text-[12px] text-[var(--text-muted)]">Managed from the user's profile.</p>
                                        <a href="{{ route('settings.profile') }}" wire:navigate class="text-[12px] text-[var(--link)] hover:underline">Edit in profile settings &rarr;</a>
                                    </div>
                                </div>
                            @else
                                <livewire:components.icon-upload :model="$member" :key="'icon-'.$member->id" />
                            @endif
                        </x-signals.form-section>
                    @endif

                    {{-- Name & Type --}}
                    <x-signals.form-section title="Basic Info">
                        <div class="space-y-3">
                            @if($isUserMember)
                                <div>
                                    <flux:input wire:model="name" label="Name" readonly disabled
                                        description="Managed from the user's profile" />
                                    <a href="{{ route('settings.profile') }}" wire:navigate class="mt-1 inline-block text-[12px] text-[var(--link)] hover:underline">Edit in profile settings &rarr;</a>
                                </div>
                            @else
                                <flux:input wire:model="name" label="Name" required />
                            @endif

                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:select wire:model.live="membershipType" label="Membership Type" required :disabled="$isEditing">
                                    @if($isUserMember)
                                        <option value="{{ \App\Enums\MembershipType::User->value }}">{{ \App\Enums\MembershipType::User->label() }}</option>
                                    @endif
                                    @foreach($membershipTypes as $type)
                                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                    @endforeach
                                </flux:select>

                                @if($isContact)
                                    <flux:input wire:model="title" label="Title" placeholder="e.g. Mr, Mrs, Dr" />
                                @endif
                            </div>

                            @if($isContact)
                                <flux:input wire:model="department" label="Department" />
                            @endif

                            <flux:textarea wire:model="description" label="Description" rows="2" />
                        </div>
                    </x-signals.form-section>

                    {{-- Account & Billing (Org/Venue) --}}
                    @if($isOrg)
                        <x-signals.form-section title="Account">
                            <div class="space-y-3">
                                <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                    <flux:input wire:model="accountNumber" label="Account Number" />
                                    <flux:select wire:model="ratingId" label="Rating">
                                        <option value="">None</option>
                                        @foreach($ratings as $rating)
                                            <option value="{{ $rating->sort_order }}">{{ $rating->name }}</option>
                                        @endforeach
                                    </flux:select>
                                </div>

                                <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                    <flux:input wire:model="taxNumber" label="Tax Number" />
                                    <flux:select wire:model="ownedBy" label="Owner">
                                        <option value="">None</option>
                                        @foreach($ownerOptions as $ownerMember)
                                            <option value="{{ $ownerMember->id }}">{{ $ownerMember->name }}</option>
                                        @endforeach
                                    </flux:select>
                                </div>

                                <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                    <flux:select wire:model="lawfulBasisTypeId" label="Legal Basis for Processing">
                                        <option value="">None</option>
                                        @foreach($lawfulBasisTypes as $basis)
                                            <option value="{{ $basis->id }}">{{ $basis->name }}</option>
                                        @endforeach
                                    </flux:select>
                                    <flux:input wire:model.number="invoiceTermLength" label="Invoice Term Length" type="number" min="0" />
                                </div>

                                <div class="flex items-center gap-6 pt-1">
                                    <flux:checkbox wire:model="isActive" label="Active" />
                                    <flux:checkbox wire:model="isCash" label="Cash Customer" />
                                    <flux:checkbox wire:model="isOnStop" label="On Stop" />
                                </div>
                            </div>
                        </x-signals.form-section>

                        <x-signals.form-section title="Tax & Pricing">
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:select wire:model="saleTaxClassId" label="Sale Tax Class">
                                    <option value="">None</option>
                                    @foreach($taxClasses as $taxClass)
                                        <option value="{{ $taxClass->id }}">{{ $taxClass->name }}</option>
                                    @endforeach
                                </flux:select>

                                <flux:select wire:model="purchaseTaxClassId" label="Purchase Tax Class">
                                    <option value="">None</option>
                                    @foreach($taxClasses as $taxClass)
                                        <option value="{{ $taxClass->id }}">{{ $taxClass->name }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                        </x-signals.form-section>
                    @else
                        {{-- Non-org: Active + Legal basis --}}
                        <x-signals.form-section title="Status">
                            <div class="space-y-3">
                                <div>
                                    <flux:checkbox wire:model="isActive" label="Active" :disabled="$isUserMember" />
                                    @if($isUserMember)
                                        <p class="mt-1 text-[12px] text-[var(--text-muted)]">A user's active state is managed from their user account.</p>
                                    @endif
                                </div>
                                <flux:select wire:model="lawfulBasisTypeId" label="Legal Basis for Processing">
                                    <option value="">None</option>
                                    @foreach($lawfulBasisTypes as $basis)
                                        <option value="{{ $basis->id }}">{{ $basis->name }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                        </x-signals.form-section>
                    @endif

                    {{-- Resource (bookable members) --}}
                    <x-signals.form-section title="Resource">
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <div class="flex items-center gap-6 pt-1">
                                    <flux:checkbox wire:model.live="bookable" label="Bookable" />
                                </div>
                                <flux:select wire:model="locationTypeId" label="Location Type" :disabled="$isUserMember"
                                    :description="$isUserMember ? 'Managed from the user profile' : null">
                                    <option value="">None</option>
                                    @foreach($locationTypes as $lt)
                                        <option value="{{ $lt->sort_order }}">{{ $lt->name }}</option>
                                    @endforeach
                                </flux:select>
                            </div>

                            @if($bookable)
                                <div class="grid grid-cols-4 gap-4 max-sm:grid-cols-2">
                                    <flux:input wire:model.number="dayCost" label="Day Cost" type="number" min="0" />
                                    <flux:input wire:model.number="hourCost" label="Hour Cost" type="number" min="0" />
                                    <flux:input wire:model.number="distanceCost" label="Distance Cost" type="number" min="0" />
                                    <flux:input wire:model.number="flatRateCost" label="Flat Rate" type="number" min="0" />
                                </div>
                            @endif
                        </div>
                    </x-signals.form-section>

                    {{-- Regional --}}
                    <x-signals.form-section title="Regional">
                        <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                            <flux:select wire:model="locale" label="Locale">
                                <option value="">None</option>
                                @foreach($locales as $loc)
                                    <option value="{{ $loc->name }}">{{ $loc->name }}</option>
                                @endforeach
                            </flux:select>
                            <flux:select wire:model="defaultCurrencyCode" label="Default Currency">
                                <option value="">None</option>
                                @foreach($currencies as $currency)
                                    <option value="{{ $currency->name }}">{{ $currency->name }}</option>
                                @endforeach
                            </flux:select>
                        </div>
                    </x-signals.form-section>

                    {{-- Actions --}}
                    <div class="flex items-center gap-4 pt-2">
                        <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Create ' . (\App\Enums\MembershipType::tryFrom($membershipType)?->label() ?? 'Member') }}</flux:button>
                        <flux:button variant="ghost" href="{{ $isEditing ? route('members.show', $memberId) : route('members.index') }}" wire:navigate>Cancel</flux:button>
                    </div>
                </div>

                {{-- ======================================== --}}
                {{-- RIGHT COLUMN — Custom fields --}}
                {{-- ======================================== --}}
                <div class="space-y-6" style="position: sticky; top: 24px;">
                    <h2 style="font-family: var(--font-display); font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); padding-bottom: 4px; border-bottom: 1px solid var(--card-border);">Custom Fields</h2>

                    <x-signals.custom-fields-editor :groupedCustomFields="$groupedCustomFields" />
                </div>
            </div>
        </form>
    </div>
</section>
