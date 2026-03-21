<?php

use App\Models\ProductGroup;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Product Groups')] class extends Component {
    public function deleteGroup(int $groupId): void
    {
        $group = ProductGroup::findOrFail($groupId);
        (new \App\Actions\Products\DeleteProductGroup)($group);
        $this->dispatch('group-deleted');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'columns' => [
                ['key' => 'name', 'label' => 'Name', 'sortable' => true, 'filterable' => true, 'filter_type' => 'text'],
                ['key' => 'description', 'label' => 'Description'],
                ['key' => 'products_count', 'label' => 'Products', 'sortable' => true],
                ['key' => 'created_at', 'label' => 'Created', 'sortable' => true],
                ['key' => 'actions', 'type' => 'actions'],
            ],
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header title="Product Groups">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Catalogue</span>
        </x-slot:meta>
    </x-signals.page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Models\ProductGroup::class"
            :searchable="['name']"
            :with-counts="['products']"
            :refresh-events="['group-deleted']"
            default-sort="name"
            empty-message="No product groups found."
            actions-view="livewire.product-groups.partials.row-actions"
            toolbar-view="livewire.product-groups.partials.toolbar"
            entity-type="product_groups"
        />
    </div>
</section>
