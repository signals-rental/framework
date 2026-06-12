<?php

use App\Actions\Products\CreateProductGroup;
use App\Actions\Products\UpdateProductGroup;
use App\Data\Products\CreateProductGroupData;
use App\Data\Products\UpdateProductGroupData;
use App\Models\ProductGroup;
use App\Services\FileService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    public ?int $groupId = null;
    public string $name = '';
    public string $description = '';

    #[Validate('nullable|image|max:2048|mimes:jpeg,jpg,png,webp,gif')]
    public mixed $photo = null;

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
            $result = (new UpdateProductGroup)(
                $group,
                UpdateProductGroupData::from([
                    'name' => $this->name,
                    'description' => $this->description ?: null,
                ])
            );
        } else {
            $result = (new CreateProductGroup)(
                CreateProductGroupData::from([
                    'name' => $this->name,
                    'description' => $this->description ?: null,
                ])
            );

            if ($this->photo) {
                $group = ProductGroup::findOrFail($result->id);

                try {
                    $upload = app(FileService::class)->uploadIcon($this->photo, $group);
                    $group->update([
                        'icon_url' => $upload['icon_url'],
                        'icon_thumb_url' => $upload['icon_thumb_url'],
                    ]);
                } catch (\Throwable $e) {
                    report($e);
                    $this->addError('photo', 'Failed to upload image. You can add one from the edit screen.');

                    $this->redirect(route('product-groups.index'), navigate: true);

                    return;
                }
            }
        }

        $this->redirect(route('product-groups.index'), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'isEditing' => $this->groupId !== null,
            'group' => $this->groupId ? ProductGroup::find($this->groupId) : null,
        ];
    }
}; ?>

<section class="w-full">
    @if($isEditing)
        <x-signals.page-header title="Edit Product Group">
            <x-slot:breadcrumbs>
                <a href="{{ route('product-groups.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Product Groups</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <a href="{{ route('products.index', ['filters' => ['product_group_id' => $groupId]]) }}" wire:navigate class="text-[var(--link)] hover:underline">{{ $name }}</a>
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
                @if($isEditing && $group)
                    <x-signals.form-section title="Group Image">
                        <livewire:components.icon-upload :model="$group" :key="'icon-'.$group->id" />
                    </x-signals.form-section>
                @else
                    <x-signals.form-section title="Group Image">
                        <div class="flex items-center gap-4">
                            <div class="flex shrink-0 items-center justify-center size-16 overflow-hidden rounded" style="background: var(--s-subtle);">
                                @if($photo)
                                    <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-full object-cover" />
                                @else
                                    <svg viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="1.5" class="size-7"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                @endif
                            </div>
                            <div class="space-y-1">
                                <input type="file" wire:model="photo" accept="image/*" class="s-input text-sm" />
                                <p class="text-xs" style="color: var(--text-muted);">PNG, JPG, WEBP or GIF. Max 2MB.</p>
                                @error('photo')
                                    <p class="text-xs" style="color: var(--red);">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </x-signals.form-section>
                @endif

                <x-signals.form-section title="Group Details">
                    <div class="space-y-3">
                        <flux:input wire:model="name" label="Name" required />
                        <flux:textarea wire:model="description" label="Description" rows="3" />
                    </div>
                </x-signals.form-section>

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Create Group' }}</flux:button>
                    <flux:button variant="ghost" href="{{ route('product-groups.index') }}" wire:navigate>Cancel</flux:button>
                </div>
            </div>
        </form>
    </div>
</section>
