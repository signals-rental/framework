<?php

use App\Actions\TaxClasses\CreateProductTaxClass;
use App\Actions\TaxClasses\UpdateProductTaxClass;
use App\Data\TaxClasses\CreateTaxClassData;
use App\Data\TaxClasses\UpdateTaxClassData;
use App\Models\ProductTaxClass;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Product Tax Class')] class extends Component {
    public ?int $taxClassId = null;

    public string $name = '';
    public string $description = '';
    public bool $isDefault = false;

    public function mount(?ProductTaxClass $productTaxClass = null): void
    {
        if ($productTaxClass?->exists) {
            $this->taxClassId = $productTaxClass->id;
            $this->name = $productTaxClass->name;
            $this->description = $productTaxClass->description ?? '';
            $this->isDefault = $productTaxClass->is_default;
        }
    }

    public function with(): array
    {
        return [
            'isEditing' => $this->taxClassId !== null,
        ];
    }

    public function save(): void
    {
        if ($this->taxClassId) {
            $taxClass = ProductTaxClass::findOrFail($this->taxClassId);

            if ($this->isDefault && ! $taxClass->is_default) {
                $taxClass->getConnection()->transaction(function () use ($taxClass) {
                    ProductTaxClass::query()->where('is_default', true)->update(['is_default' => false]);
                    (new UpdateProductTaxClass)($taxClass, UpdateTaxClassData::validateAndCreate([
                        'name' => $this->name,
                        'description' => $this->description,
                        'is_default' => true,
                    ]));
                });
            } else {
                (new UpdateProductTaxClass)($taxClass, UpdateTaxClassData::validateAndCreate([
                    'name' => $this->name,
                    'description' => $this->description,
                    'is_default' => $this->isDefault,
                ]));
            }
        } else {
            $isDefault = $this->isDefault || ProductTaxClass::count() === 0;

            if ($isDefault) {
                ProductTaxClass::query()->getConnection()->transaction(function () {
                    ProductTaxClass::query()->where('is_default', true)->update(['is_default' => false]);
                    (new CreateProductTaxClass)(CreateTaxClassData::validateAndCreate([
                        'name' => $this->name,
                        'description' => $this->description,
                        'is_default' => true,
                    ]));
                });
            } else {
                (new CreateProductTaxClass)(CreateTaxClassData::validateAndCreate([
                    'name' => $this->name,
                    'description' => $this->description,
                    'is_default' => false,
                ]));
            }
        }

        $this->redirect(route('admin.settings.tax.product-tax-classes'), navigate: true);
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="tax" :title="$isEditing ? 'Edit Product Tax Class' : 'Create Product Tax Class'" :description="$isEditing ? 'Update this product tax class.' : 'Add a new product tax classification.'">
        <x-slot:actions>
            <flux:button variant="ghost" href="{{ route('admin.settings.tax.product-tax-classes') }}" wire:navigate>
                Back to Product Tax Classes
            </flux:button>
        </x-slot:actions>

        <form wire:submit="save" class="space-y-6">
            <x-signals.form-section title="Tax Class Details">
                <div class="space-y-4">
                    <flux:input wire:model="name" label="Name" required />
                    @error('name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    <flux:textarea wire:model="description" label="Description" rows="2" />
                    @error('description') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    <flux:checkbox wire:model="isDefault" label="Default tax class" />
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">
                    {{ $isEditing ? 'Update Tax Class' : 'Create Tax Class' }}
                </flux:button>
                <flux:button variant="ghost" href="{{ route('admin.settings.tax.product-tax-classes') }}" wire:navigate>
                    Cancel
                </flux:button>
            </div>
        </form>
    </x-admin.layout>
</section>
