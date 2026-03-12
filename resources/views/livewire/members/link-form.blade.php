<?php

use App\Actions\Members\CreateLink;
use App\Actions\Members\UpdateLink;
use App\Data\Members\CreateLinkData;
use App\Data\Members\UpdateLinkData;
use App\Models\Link;
use App\Models\ListName;
use App\Models\Member;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Member $member;
    public ?int $linkId = null;
    public string $url = '';
    public string $name = '';
    public ?int $typeId = null;

    public function mount(Member $member, ?Link $link = null): void
    {
        $this->member = $member;

        if ($link?->exists) {
            $this->linkId = $link->id;
            $this->url = $link->url;
            $this->name = $link->name ?? '';
            $this->typeId = $link->type_id;
        }
    }

    public function save(): void
    {
        $this->validate([
            'url' => ['required', 'url', 'max:2048'],
            'name' => ['nullable', 'string', 'max:255'],
            'typeId' => ['nullable', 'integer', 'exists:list_values,id'],
        ]);

        $data = [
            'url' => $this->url,
            'name' => $this->name ?: null,
            'type_id' => $this->typeId,
        ];

        if ($this->linkId) {
            $link = $this->member->links()->findOrFail($this->linkId);
            (new UpdateLink)($link, UpdateLinkData::from($data));
        } else {
            (new CreateLink)($this->member, CreateLinkData::from($data));
        }

        $this->redirect(route('members.links', $this->member), navigate: true);
    }

    public function with(): array
    {
        $linkTypes = ListName::where('name', 'LinkType')->first()?->values()->where('is_active', true)->orderBy('sort_order')->get() ?? collect();

        return [
            'isEditing' => $this->linkId !== null,
            'linkTypes' => $linkTypes,
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header :title="$isEditing ? 'Edit Link' : 'Add Link'">
        <x-slot:breadcrumbs>
            <a href="{{ route('members.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Members</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <a href="{{ route('members.show', $member) }}" wire:navigate class="text-[var(--link)] hover:underline">{{ $member->name }}</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <a href="{{ route('members.links', $member) }}" wire:navigate class="text-[var(--link)] hover:underline">Links</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>{{ $isEditing ? 'Edit' : 'Add' }}</span>
        </x-slot:breadcrumbs>
    </x-signals.page-header>

    <div class="flex-1 p-8 max-md:p-5 max-sm:p-3">
        <form wire:submit="save" class="max-w-2xl space-y-8">
            <x-signals.form-section title="Link Details">
                <div class="space-y-4">
                    <flux:input wire:model="url" label="URL" type="url" placeholder="https://example.com" required />
                    <flux:input wire:model="name" label="Label" placeholder="e.g. Company Website" />
                    <flux:select wire:model="typeId" label="Type">
                        <option value="">Select type...</option>
                        @foreach($linkTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </flux:select>
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Add Link' }}</flux:button>
                <flux:button variant="ghost" href="{{ route('members.links', $member) }}" wire:navigate>Cancel</flux:button>
            </div>
        </form>
    </div>
</section>
