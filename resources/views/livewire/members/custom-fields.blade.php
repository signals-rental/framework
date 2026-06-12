<?php

use App\Models\CustomField;
use App\Models\Member;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links', 'organisations', 'contacts']);
    }

    public function with(): array
    {
        $fields = CustomField::query()
            ->forModule('Member')
            ->active()
            ->with('group')
            ->orderBy('sort_order')
            ->get();

        $values = $this->member->customFieldValues()
            ->with('customField')
            ->get()
            ->keyBy('custom_field_id');

        $grouped = $fields->groupBy(fn ($field) => $field->group?->name ?? 'General');

        return [
            'grouped' => $grouped,
            'values' => $values,
        ];
    }
}; ?>

<section class="w-full">
    @include('livewire.members.partials.member-header', ['member' => $member, 'subpage' => 'Custom Fields'])
    @include('livewire.members.partials.member-tabs', ['member' => $member, 'activeTab' => 'custom-fields'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="max-w-2xl space-y-8">
            <x-signals.custom-fields-display :grouped="$grouped" :values="$values" emptyMessage="No custom fields have been configured for members." />
        </div>
    </div>
</section>
