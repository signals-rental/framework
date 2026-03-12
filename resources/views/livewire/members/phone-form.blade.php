<?php

use App\Actions\Members\CreatePhone;
use App\Actions\Members\UpdatePhone;
use App\Data\Members\CreatePhoneData;
use App\Data\Members\UpdatePhoneData;
use App\Models\ListName;
use App\Models\Member;
use App\Models\Phone;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Member $member;
    public ?int $phoneId = null;
    public string $number = '';
    public ?int $typeId = null;
    public bool $isPrimary = false;

    public function mount(Member $member, ?Phone $phone = null): void
    {
        $this->member = $member;

        if ($phone?->exists) {
            $this->phoneId = $phone->id;
            $this->number = $phone->number;
            $this->typeId = $phone->type_id;
            $this->isPrimary = $phone->is_primary;
        }
    }

    public function save(): void
    {
        $this->validate([
            'number' => ['required', 'string', 'max:50'],
            'typeId' => ['nullable', 'integer', 'exists:list_values,id'],
            'isPrimary' => ['boolean'],
        ]);

        $data = [
            'number' => $this->number,
            'type_id' => $this->typeId,
            'is_primary' => $this->isPrimary,
        ];

        DB::transaction(function () use ($data) {
            if ($this->isPrimary) {
                $this->member->phones()->update(['is_primary' => false]);
            }

            if ($this->phoneId) {
                $phone = $this->member->phones()->findOrFail($this->phoneId);
                (new UpdatePhone)($phone, UpdatePhoneData::from($data));
            } else {
                (new CreatePhone)($this->member, CreatePhoneData::from($data));
            }
        });

        $this->redirect(route('members.phones', $this->member), navigate: true);
    }

    public function with(): array
    {
        $phoneTypes = ListName::where('name', 'PhoneType')->first()?->values()->where('is_active', true)->orderBy('sort_order')->get() ?? collect();

        return [
            'isEditing' => $this->phoneId !== null,
            'phoneTypes' => $phoneTypes,
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header :title="$isEditing ? 'Edit Phone' : 'Add Phone'">
        <x-slot:breadcrumbs>
            <a href="{{ route('members.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Members</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <a href="{{ route('members.show', $member) }}" wire:navigate class="text-[var(--link)] hover:underline">{{ $member->name }}</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <a href="{{ route('members.phones', $member) }}" wire:navigate class="text-[var(--link)] hover:underline">Phones</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>{{ $isEditing ? 'Edit' : 'Add' }}</span>
        </x-slot:breadcrumbs>
    </x-signals.page-header>

    <div class="flex-1 p-8 max-md:p-5 max-sm:p-3">
        <form wire:submit="save" class="max-w-2xl space-y-8">
            <x-signals.form-section title="Phone Details">
                <div class="space-y-4">
                    <flux:input wire:model="number" label="Number" type="tel" required />
                    <flux:select wire:model="typeId" label="Type">
                        <option value="">Select type...</option>
                        @foreach($phoneTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:checkbox wire:model="isPrimary" label="Primary phone number" />
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Add Phone' }}</flux:button>
                <flux:button variant="ghost" href="{{ route('members.phones', $member) }}" wire:navigate>Cancel</flux:button>
            </div>
        </form>
    </div>
</section>
