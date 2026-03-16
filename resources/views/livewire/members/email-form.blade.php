<?php

use App\Actions\Members\CreateEmail;
use App\Actions\Members\UpdateEmail;
use App\Data\Members\CreateEmailData;
use App\Data\Members\UpdateEmailData;
use App\Models\Email;
use App\Models\ListName;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Member $member;
    public ?int $emailId = null;
    public string $address = '';
    public ?int $typeId = null;
    public bool $isPrimary = false;

    public function mount(Member $member, ?Email $email = null): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links', 'organisations', 'contacts']);

        if ($email?->exists) {
            $this->emailId = $email->id;
            $this->address = $email->address;
            $this->typeId = $email->type_id;
            $this->isPrimary = $email->is_primary;
        }
    }

    public function save(): void
    {
        $this->validate([
            'address' => ['required', 'email', 'max:255'],
            'typeId' => ['nullable', 'integer', 'exists:list_values,id'],
            'isPrimary' => ['boolean'],
        ]);

        $data = [
            'address' => $this->address,
            'type_id' => $this->typeId,
            'is_primary' => $this->isPrimary,
        ];

        DB::transaction(function () use ($data) {
            if ($this->isPrimary) {
                $this->member->emails()->update(['is_primary' => false]);
            }

            if ($this->emailId) {
                $email = $this->member->emails()->findOrFail($this->emailId);
                (new UpdateEmail)($email, UpdateEmailData::from($data));
            } else {
                (new CreateEmail)($this->member, CreateEmailData::from($data));
            }
        });

        $this->redirect(route('members.information', $this->member), navigate: true);
    }

    public function with(): array
    {
        $emailTypes = ListName::where('name', 'EmailType')->first()?->values()->where('is_active', true)->orderBy('sort_order')->get() ?? collect();

        return [
            'isEditing' => $this->emailId !== null,
            'emailTypes' => $emailTypes,
        ];
    }
}; ?>

<section class="w-full">
    @include('livewire.members.partials.member-header', ['member' => $member, 'subpage' => $isEditing ? 'Edit Email' : 'Add Email'])
    @include('livewire.members.partials.member-tabs', ['member' => $member, 'activeTab' => 'information'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <form wire:submit="save" class="max-w-2xl space-y-8">
            <x-signals.form-section title="Email Details">
                <div class="space-y-4">
                    <flux:input wire:model="address" label="Email Address" type="email" required />
                    <flux:select wire:model="typeId" label="Type">
                        <option value="">Select type...</option>
                        @foreach($emailTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:checkbox wire:model="isPrimary" label="Primary email" />
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Add Email' }}</flux:button>
                <flux:button variant="ghost" href="{{ route('members.show', $member) }}" wire:navigate>Cancel</flux:button>
            </div>
        </form>
    </div>
</section>
