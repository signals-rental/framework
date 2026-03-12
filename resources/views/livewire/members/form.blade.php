<?php

use App\Actions\Members\CreateMember;
use App\Actions\Members\UpdateMember;
use App\Data\Members\CreateMemberData;
use App\Data\Members\UpdateMemberData;
use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\OrganisationTaxClass;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public ?int $memberId = null;
    public string $name = '';
    public string $membershipType = 'contact';
    public bool $isActive = true;
    public string $description = '';
    public ?string $locale = null;
    public ?string $defaultCurrencyCode = null;
    public ?int $organisationTaxClassId = null;

    public function mount(?Member $member = null): void
    {
        if ($member?->exists) {
            $this->memberId = $member->id;
            $this->name = $member->name;
            $this->membershipType = $member->membership_type->value;
            $this->isActive = $member->is_active;
            $this->description = $member->description ?? '';
            $this->locale = $member->locale;
            $this->defaultCurrencyCode = $member->default_currency_code;
            $this->organisationTaxClassId = $member->organisation_tax_class_id;
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'membershipType' => ['required', 'string', Rule::in(array_column(MembershipType::cases(), 'value'))],
        ]);

        if ($this->memberId) {
            $member = Member::findOrFail($this->memberId);
            $data = UpdateMemberData::from([
                'name' => $this->name,
                'membership_type' => $this->membershipType,
                'is_active' => $this->isActive,
                'description' => $this->description ?: null,
                'locale' => $this->locale,
                'default_currency_code' => $this->defaultCurrencyCode,
                'organisation_tax_class_id' => $this->organisationTaxClassId,
            ]);
            $result = (new UpdateMember)($member, $data);
        } else {
            $data = CreateMemberData::from([
                'name' => $this->name,
                'membership_type' => $this->membershipType,
                'is_active' => $this->isActive,
                'description' => $this->description ?: null,
                'locale' => $this->locale,
                'default_currency_code' => $this->defaultCurrencyCode,
                'organisation_tax_class_id' => $this->organisationTaxClassId,
            ]);
            $result = (new CreateMember)($data);
        }

        $this->redirect(route('members.show', $result->id), navigate: true);
    }

    public function with(): array
    {
        return [
            'isEditing' => $this->memberId !== null,
            'membershipTypes' => MembershipType::cases(),
            'taxClasses' => OrganisationTaxClass::query()->orderBy('name')->get(),
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header :title="$isEditing ? 'Edit Member' : 'Create Member'">
        <x-slot:breadcrumbs>
            <a href="{{ route('members.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Members</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>{{ $isEditing ? 'Edit' : 'Create' }}</span>
        </x-slot:breadcrumbs>
    </x-signals.page-header>

    <div class="flex-1 p-8 max-md:p-5 max-sm:p-3">
        <form wire:submit="save" class="max-w-2xl space-y-8">
            <x-signals.form-section title="Basic Info">
                <div class="space-y-4">
                    <flux:input wire:model="name" label="Name" required />

                    <flux:select wire:model="membershipType" label="Membership Type" required>
                        @foreach($membershipTypes as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </flux:select>

                    <flux:checkbox wire:model="isActive" label="Active" />
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Details">
                <div class="space-y-4">
                    <flux:textarea wire:model="description" label="Description" rows="3" />

                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="locale" label="Locale" placeholder="e.g. en-GB" />
                        <flux:input wire:model="defaultCurrencyCode" label="Default Currency" placeholder="e.g. GBP" maxlength="3" />
                    </div>

                    <flux:select wire:model="organisationTaxClassId" label="Organisation Tax Class">
                        <option value="">None</option>
                        @foreach($taxClasses as $taxClass)
                            <option value="{{ $taxClass->id }}">{{ $taxClass->name }}</option>
                        @endforeach
                    </flux:select>
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Create Member' }}</flux:button>
                <flux:button variant="ghost" href="{{ $isEditing ? route('members.show', $memberId) : route('members.index') }}" wire:navigate>Cancel</flux:button>
            </div>
        </form>
    </div>
</section>
