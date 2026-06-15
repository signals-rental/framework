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
     * Bulk-delete the selected product groups.
     *
     * @param  array<int, int>  $ids
     */
    public function deleteSelected(array $ids): void
    {
        $action = new \App\Actions\Products\DeleteProductGroup;

        foreach ($ids as $id) {
            $group = ProductGroup::find($id);

            if ($group) {
                $action($group);
            }
        }

        $this->dispatch('group-deleted');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $parentOptions = ProductGroup::query()->orderBy('name')->pluck('name', 'id')->all();

        return [
            'columns' => [
                ['key' => 'checkbox', 'type' => 'checkbox'],
                ['key' => 'name', 'label' => __('Name'), 'sortable' => true, 'filterable' => true, 'filter_type' => 'text', 'view' => 'livewire.product-groups.partials.column-name'],
                ['key' => 'description', 'label' => __('Description')],
                ['key' => 'parent_id', 'label' => __('Parent Group'), 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => $parentOptions, 'view' => 'livewire.product-groups.partials.column-parent'],
                ['key' => 'products_count', 'label' => __('Products'), 'sortable' => true, 'view' => 'livewire.product-groups.partials.column-products-count'],
                ['key' => 'created_at', 'label' => __('Created'), 'sortable' => true],
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
            :with="['parent']"
            :with-counts="['products']"
            :refresh-events="['group-deleted']"
            default-sort="name"
            empty-message="No product groups found."
            actions-view="livewire.product-groups.partials.row-actions"
            bulk-actions-view="livewire.product-groups.partials.bulk-actions"
            toolbar-view="livewire.product-groups.partials.toolbar"
            entity-type="product_groups"
        />
    </div>
</section>
