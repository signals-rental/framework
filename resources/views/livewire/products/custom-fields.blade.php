<?php

use App\Models\CustomField;
use App\Models\Product;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Product $product;

    public function mount(Product $product): void
    {
        $this->product = $product->loadCount(['stockLevels', 'accessories', 'attachments']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name . ' — Custom Fields');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $fields = CustomField::query()
            ->forModule('Product')
            ->active()
            ->with('group')
            ->orderBy('sort_order')
            ->get();

        $values = $this->product->customFieldValues()
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
    @include('livewire.products.partials.product-header', ['product' => $product, 'subpage' => 'Custom Fields'])
    @include('livewire.products.partials.product-tabs', ['product' => $product, 'activeTab' => 'custom-fields'])

    <div class="flex-1 p-8 max-md:p-5 max-sm:p-3">
        <div class="max-w-2xl space-y-8">
            <x-signals.custom-fields-display :grouped="$grouped" :values="$values" emptyMessage="No custom fields have been configured for products." />
        </div>
    </div>
</section>
