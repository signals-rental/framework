<?php

use App\Models\ProductGroup;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public ?int $groupId = null;
    public string $name = '';
    public string $description = '';

    public function mount(?ProductGroup $productGroup = null): void
    {
        if ($productGroup?->exists) {
            $this->groupId = $productGroup->id;
            $this->name = $productGroup->name;
            $this->description = $productGroup->description ?? '';
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($this->groupId) {
            $group = ProductGroup::findOrFail($this->groupId);
            $group->update([
                'name' => $this->name,
                'description' => $this->description ?: null,
            ]);
        } else {
            $group = ProductGroup::create([
                'name' => $this->name,
                'description' => $this->description ?: null,
            ]);
        }

        $this->redirect(route('product-groups.show', $group->id), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'isEditing' => $this->groupId !== null,
        ];
    }
}; ?>

<section class="w-full">
    @if($isEditing)
        <x-signals.page-header title="Edit Product Group">
            <x-slot:breadcrumbs>
                <a href="{{ route('product-groups.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Product Groups</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <a href="{{ route('product-groups.show', $groupId) }}" wire:navigate class="text-[var(--link)] hover:underline">{{ $name }}</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <span>Edit</span>
            </x-slot:breadcrumbs>
        </x-signals.page-header>
    @else
        <x-signals.page-header title="Create Product Group">
            <x-slot:breadcrumbs>
                <a href="{{ route('product-groups.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Product Groups</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <span>Create</span>
            </x-slot:breadcrumbs>
        </x-signals.page-header>
    @endif

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <form wire:submit="save" style="max-width: 480px;">
            <div class="space-y-6">
                <x-signals.form-section title="Group Details">
                    <div class="space-y-3">
                        <flux:input wire:model="name" label="Name" required />
                        <flux:textarea wire:model="description" label="Description" rows="3" />
                    </div>
                </x-signals.form-section>

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Create Group' }}</flux:button>
                    <flux:button variant="ghost" href="{{ $isEditing ? route('product-groups.show', $groupId) : route('product-groups.index') }}" wire:navigate>Cancel</flux:button>
                </div>
            </div>
        </form>
    </div>
</section>
