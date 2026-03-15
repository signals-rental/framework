<?php

use App\Models\CustomField;
use App\Models\Member;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links']);
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
    <x-signals.page-header :title="$member->name">
        <x-slot:breadcrumbs>
            <a href="{{ route('members.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Members</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <a href="{{ route('members.show', $member) }}" wire:navigate class="text-[var(--link)] hover:underline">{{ $member->name }}</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>Custom Fields</span>
        </x-slot:breadcrumbs>
    </x-signals.page-header>

    @include('livewire.members.partials.member-tabs', ['member' => $member, 'activeTab' => 'custom-fields'])

    <div class="flex-1 p-8 max-md:p-5 max-sm:p-3">
        @if($grouped->isEmpty())
            <div class="text-center text-[var(--text-muted)] py-12">
                No custom fields have been configured for members.
            </div>
        @else
            <div class="max-w-2xl space-y-8">
                @foreach($grouped as $groupName => $fields)
                    <x-signals.form-section :title="$groupName">
                        <dl class="space-y-3">
                            @foreach($fields as $field)
                                @php
                                    $cfv = $values->get($field->id);
                                    $column = $field->field_type->valueColumn();
                                    $value = $cfv?->{$column};
                                @endphp
                                <div wire:key="cf-{{ $field->id }}">
                                    <dt class="text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">
                                        {{ $field->display_name ?? $field->name }}
                                    </dt>
                                    <dd class="mt-0.5">
                                        @if($field->field_type === \App\Enums\CustomFieldType::Boolean)
                                            @if($value === true)
                                                <span class="s-badge s-badge-green">Yes</span>
                                            @elseif($value === false)
                                                <span class="s-badge s-badge-zinc">No</span>
                                            @else
                                                <span class="text-[var(--text-muted)]">Not set</span>
                                            @endif
                                        @elseif($field->field_type === \App\Enums\CustomFieldType::Website && $value)
                                            <a href="{{ $value }}" target="_blank" rel="noopener noreferrer" class="text-[var(--link)] hover:underline">{{ Str::limit($value, 60) }}</a>
                                        @elseif($field->field_type === \App\Enums\CustomFieldType::Email && $value)
                                            <a href="mailto:{{ $value }}" class="text-[var(--link)] hover:underline">{{ $value }}</a>
                                        @elseif($field->field_type === \App\Enums\CustomFieldType::MultiListOfValues && is_array($value))
                                            @if(count($value) > 0)
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach($value as $item)
                                                        <span class="s-badge s-badge-blue">{{ $item }}</span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-[var(--text-muted)]">Not set</span>
                                            @endif
                                        @elseif($value !== null && $value !== '')
                                            {{ $value }}
                                        @else
                                            <span class="text-[var(--text-muted)]">Not set</span>
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    </x-signals.form-section>
                @endforeach
            </div>
        @endif
    </div>
</section>
