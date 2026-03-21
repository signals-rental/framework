<?php

use App\Actions\Products\CreateProduct;
use App\Actions\Products\UpdateProduct;
use App\Data\Products\CreateProductData;
use App\Data\Products\UpdateProductData;
use App\Enums\ProductType;
use App\Enums\StockMethod;
use App\Livewire\Concerns\LoadsCustomFieldValues;
use App\Livewire\Concerns\ReKeysCustomFieldErrors;
use App\Models\Country;
use App\Models\CostGroup;
use App\Models\CustomField;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductTaxClass;
use App\Models\RevenueGroup;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    use LoadsCustomFieldValues;
    use ReKeysCustomFieldErrors;
    public ?int $productId = null;
    public string $name = '';
    public string $productType = 'rental';
    public bool $isActive = true;
    public string $description = '';
    public ?int $productGroupId = null;
    public int $allowedStockType = 1;
    public int $stockMethod = 1;
    public ?string $weight = null;
    public ?string $barcode = null;
    public ?string $sku = null;
    public int $replacementCharge = 0;
    public string $bufferPercent = '0.0';
    public int $postRentUnavailability = 0;
    public bool $accessoryOnly = false;
    public bool $system = false;
    public bool $discountable = true;
    public ?int $taxClassId = null;
    public ?int $purchaseTaxClassId = null;
    public ?int $rentalRevenueGroupId = null;
    public ?int $saleRevenueGroupId = null;
    public ?int $subRentalCostGroupId = null;
    public int $subRentalPrice = 0;
    public ?int $purchaseCostGroupId = null;
    public int $purchasePrice = 0;
    public ?int $countryOfOriginId = null;
    public ?string $tagList = null;
    /** @var array<string, mixed> */
    public array $customFieldValues = [];

    public function mount(?Product $product = null): void
    {
        // Pre-populate product type from ?type= query param
        $type = request()->query('type');
        if (is_string($type) && in_array($type, array_column(ProductType::cases(), 'value'))) {
            $this->productType = $type;
        }

        if ($product?->exists) {
            $this->productId = $product->id;
            $this->name = $product->name;
            $this->productType = $product->product_type->value;
            $this->isActive = $product->is_active;
            $this->description = $product->description ?? '';
            $this->productGroupId = $product->product_group_id;
            $this->allowedStockType = $product->allowed_stock_type ?? 1;
            $this->stockMethod = $product->stock_method->value;
            $this->weight = $product->weight;
            $this->barcode = $product->barcode;
            $this->sku = $product->sku;
            $this->replacementCharge = $product->replacement_charge ?? 0;
            $this->bufferPercent = $product->buffer_percent ?? '0.0';
            $this->postRentUnavailability = $product->post_rent_unavailability ?? 0;
            $this->accessoryOnly = $product->accessory_only ?? false;
            $this->system = $product->system ?? false;
            $this->discountable = $product->discountable ?? true;
            $this->taxClassId = $product->tax_class_id;
            $this->purchaseTaxClassId = $product->purchase_tax_class_id;
            $this->rentalRevenueGroupId = $product->rental_revenue_group_id;
            $this->saleRevenueGroupId = $product->sale_revenue_group_id;
            $this->subRentalCostGroupId = $product->sub_rental_cost_group_id;
            $this->subRentalPrice = $product->sub_rental_price ?? 0;
            $this->purchaseCostGroupId = $product->purchase_cost_group_id;
            $this->purchasePrice = $product->purchase_price ?? 0;
            $this->countryOfOriginId = $product->country_of_origin_id;
            $this->tagList = $product->tag_list ? implode(', ', $product->tag_list) : null;

            // Load existing custom field values
            $this->customFieldValues = $this->loadCustomFieldValuesFrom($product);
        }
    }

    public function save(): void
    {
        $nameUniqueRule = Rule::unique('products', 'name');

        if ($this->productId) {
            $nameUniqueRule->ignore($this->productId);
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255', $nameUniqueRule],
        ]);

        $payload = [
            'name' => $this->name,
            'product_type' => $this->productType,
            'is_active' => $this->isActive,
            'description' => $this->description ?: null,
            'product_group_id' => $this->productGroupId,
            'allowed_stock_type' => $this->allowedStockType,
            'stock_method' => $this->stockMethod,
            'weight' => $this->weight,
            'barcode' => $this->barcode,
            'sku' => $this->sku,
            'replacement_charge' => $this->replacementCharge,
            'buffer_percent' => $this->bufferPercent,
            'post_rent_unavailability' => $this->postRentUnavailability,
            'accessory_only' => $this->accessoryOnly,
            'system' => $this->system,
            'discountable' => $this->discountable,
            'tax_class_id' => $this->taxClassId,
            'purchase_tax_class_id' => $this->purchaseTaxClassId,
            'rental_revenue_group_id' => $this->rentalRevenueGroupId,
            'sale_revenue_group_id' => $this->saleRevenueGroupId,
            'sub_rental_cost_group_id' => $this->subRentalCostGroupId,
            'sub_rental_price' => $this->subRentalPrice,
            'purchase_cost_group_id' => $this->purchaseCostGroupId,
            'purchase_price' => $this->purchasePrice,
            'country_of_origin_id' => $this->countryOfOriginId,
            'tag_list' => $this->tagList
                ? array_map('trim', explode(',', $this->tagList))
                : null,
            'custom_fields' => $this->customFieldValues,
        ];

        try {
            if ($this->productId) {
                $product = Product::findOrFail($this->productId);
                $result = (new UpdateProduct)($product, UpdateProductData::from($payload));
            } else {
                $result = (new CreateProduct)(CreateProductData::from($payload));
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->reKeyCustomFieldErrors($e, $this->referenceData['customFields']->pluck('name')->all());
        }

        $this->redirect(route('products.show', $result->id), navigate: true);
    }

    #[Computed]
    public function referenceData(): array
    {
        return [
            'productGroups' => ProductGroup::query()->orderBy('name')->get(['id', 'name']),
            'taxClasses' => ProductTaxClass::query()->orderBy('name')->get(),
            'revenueGroups' => RevenueGroup::query()->orderBy('name')->get(['id', 'name']),
            'costGroups' => CostGroup::query()->orderBy('name')->get(['id', 'name']),
            'countries' => Country::query()->orderBy('name')->get(['id', 'name']),
            'customFields' => CustomField::query()
                ->forModule('Product')
                ->active()
                ->with(['group', 'listName.values'])
                ->orderBy('sort_order')
                ->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $isEditing = $this->productId !== null;
        $ref = $this->referenceData;

        return [
            'isEditing' => $isEditing,
            'product' => $isEditing ? Product::find($this->productId)?->loadCount(['stockLevels', 'accessories', 'attachments']) : null,
            'productTypes' => ProductType::cases(),
            'stockMethods' => StockMethod::cases(),
            'productGroups' => $ref['productGroups'],
            'taxClasses' => $ref['taxClasses'],
            'revenueGroups' => $ref['revenueGroups'],
            'costGroups' => $ref['costGroups'],
            'countries' => $ref['countries'],
            'customFields' => $ref['customFields'],
            'groupedCustomFields' => $ref['customFields']->groupBy(fn ($f) => $f->group?->name ?? 'General'),
        ];
    }
}; ?>

<section class="w-full">
    @if($isEditing && $product)
        @include('livewire.products.partials.product-header', ['product' => $product, 'subpage' => 'Edit'])
        @include('livewire.products.partials.product-tabs', ['product' => $product, 'activeTab' => ''])
    @else
        <x-signals.page-header title="Create Product">
            <x-slot:breadcrumbs>
                <a href="{{ route('products.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Products</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <span>Create</span>
            </x-slot:breadcrumbs>
        </x-signals.page-header>
    @endif

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <form wire:submit="save">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; align-items: start;">

                {{-- ======================================== --}}
                {{-- LEFT COLUMN — Core fields --}}
                {{-- ======================================== --}}
                <div class="space-y-6">

                    {{-- Icon Upload (edit mode only) --}}
                    @if($isEditing && $product)
                        <x-signals.form-section title="Product Image">
                            <livewire:components.icon-upload :model="$product" :key="'icon-'.$product->id" />
                        </x-signals.form-section>
                    @endif

                    {{-- Basic Info --}}
                    <x-signals.form-section title="Basic Info">
                        <div class="space-y-3">
                            <flux:input wire:model="name" label="Name" required />

                            <flux:textarea wire:model="description" label="Description" rows="2" />

                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:select wire:model.live="productType" label="Product Type" required>
                                    @foreach($productTypes as $type)
                                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                    @endforeach
                                </flux:select>

                                <flux:select wire:model="productGroupId" label="Product Group">
                                    <option value="">None</option>
                                    @foreach($productGroups as $group)
                                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                        </div>
                    </x-signals.form-section>

                    {{-- Identification --}}
                    <x-signals.form-section title="Identification">
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:input wire:model="sku" label="SKU" />
                                <flux:input wire:model="barcode" label="Barcode" />
                            </div>
                            <flux:input wire:model="weight" label="Weight" type="number" step="any" min="0" />
                        </div>
                    </x-signals.form-section>

                    {{-- Stock --}}
                    <x-signals.form-section title="Stock">
                        <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                            <flux:select wire:model="stockMethod" label="Stock Method">
                                @foreach($stockMethods as $method)
                                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="allowedStockType" label="Allowed Stock Type">
                                <option value="1">Rental</option>
                                <option value="2">Sale</option>
                                <option value="3">Both</option>
                            </flux:select>
                        </div>
                    </x-signals.form-section>

                    {{-- Pricing --}}
                    <x-signals.form-section title="Pricing">
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:input wire:model.number="replacementCharge" label="Replacement Charge" type="number" min="0" />
                                <flux:input wire:model.number="subRentalPrice" label="Sub-Rental Price" type="number" min="0" />
                            </div>
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:input wire:model.number="purchasePrice" label="Purchase Price" type="number" min="0" />
                                <flux:input wire:model="bufferPercent" label="Buffer %" type="number" step="any" min="0" max="100" />
                            </div>
                            <flux:input wire:model.number="postRentUnavailability" label="Post-Rent Unavailability (days)" type="number" min="0" />
                        </div>
                    </x-signals.form-section>

                    {{-- Tax & Revenue --}}
                    <x-signals.form-section title="Tax & Revenue">
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:select wire:model="taxClassId" label="Tax Class">
                                    <option value="">None</option>
                                    @foreach($taxClasses as $taxClass)
                                        <option value="{{ $taxClass->id }}">{{ $taxClass->name }}</option>
                                    @endforeach
                                </flux:select>

                                <flux:select wire:model="purchaseTaxClassId" label="Purchase Tax Class">
                                    <option value="">None</option>
                                    @foreach($taxClasses as $taxClass)
                                        <option value="{{ $taxClass->id }}">{{ $taxClass->name }}</option>
                                    @endforeach
                                </flux:select>
                            </div>

                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:select wire:model="rentalRevenueGroupId" label="Rental Revenue Group">
                                    <option value="">None</option>
                                    @foreach($revenueGroups as $revGroup)
                                        <option value="{{ $revGroup->id }}">{{ $revGroup->name }}</option>
                                    @endforeach
                                </flux:select>

                                <flux:select wire:model="saleRevenueGroupId" label="Sale Revenue Group">
                                    <option value="">None</option>
                                    @foreach($revenueGroups as $revGroup)
                                        <option value="{{ $revGroup->id }}">{{ $revGroup->name }}</option>
                                    @endforeach
                                </flux:select>
                            </div>

                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:select wire:model="subRentalCostGroupId" label="Sub-Rental Cost Group">
                                    <option value="">None</option>
                                    @foreach($costGroups as $cGroup)
                                        <option value="{{ $cGroup->id }}">{{ $cGroup->name }}</option>
                                    @endforeach
                                </flux:select>

                                <flux:select wire:model="purchaseCostGroupId" label="Purchase Cost Group">
                                    <option value="">None</option>
                                    @foreach($costGroups as $cGroup)
                                        <option value="{{ $cGroup->id }}">{{ $cGroup->name }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                        </div>
                    </x-signals.form-section>

                    {{-- Other --}}
                    <x-signals.form-section title="Other">
                        <div class="space-y-3">
                            <flux:select wire:model="countryOfOriginId" label="Country of Origin">
                                <option value="">None</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}">{{ $country->name }}</option>
                                @endforeach
                            </flux:select>

                            <flux:input wire:model="tagList" label="Tags" placeholder="Comma-separated tags" />
                        </div>
                    </x-signals.form-section>

                    {{-- Checkboxes --}}
                    <x-signals.form-section title="Options">
                        <div class="flex items-center gap-6 pt-1 flex-wrap">
                            <flux:checkbox wire:model="isActive" label="Active" />
                            <flux:checkbox wire:model="accessoryOnly" label="Accessory Only" />
                            <flux:checkbox wire:model="system" label="System" />
                            <flux:checkbox wire:model="discountable" label="Discountable" />
                        </div>
                    </x-signals.form-section>

                    {{-- Actions --}}
                    <div class="flex items-center gap-4 pt-2">
                        <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Create Product' }}</flux:button>
                        <flux:button variant="ghost" href="{{ $isEditing ? route('products.show', $productId) : route('products.index') }}" wire:navigate>Cancel</flux:button>
                    </div>
                </div>

                {{-- ======================================== --}}
                {{-- RIGHT COLUMN — Custom fields --}}
                {{-- ======================================== --}}
                <div class="space-y-6" style="position: sticky; top: 24px;">
                    <h2 style="font-family: var(--font-display); font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); padding-bottom: 4px; border-bottom: 1px solid var(--card-border);">Custom Fields</h2>

                    <x-signals.custom-fields-editor :groupedCustomFields="$groupedCustomFields" />
                </div>
            </div>
        </form>
    </div>
</section>
