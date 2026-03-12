<?php

use App\Actions\TaxClasses\DeleteProductTaxClass;
use App\Actions\TaxClasses\UpdateProductTaxClass;
use App\Data\TaxClasses\UpdateTaxClassData;
use App\Models\ProductTaxClass;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Product Tax Classes')] class extends Component {
    /** @var \Illuminate\Database\Eloquent\Collection<int, ProductTaxClass> */
    public $taxClasses;

    public function mount(): void
    {
        $this->loadTaxClasses();
    }

    public function loadTaxClasses(): void
    {
        $this->taxClasses = ProductTaxClass::query()->orderBy('name')->get();
    }

    public function setDefault(int $taxClassId): void
    {
        $taxClass = ProductTaxClass::findOrFail($taxClassId);

        $taxClass->getConnection()->transaction(function () use ($taxClass) {
            ProductTaxClass::query()->where('is_default', true)->update(['is_default' => false]);
            (new UpdateProductTaxClass)($taxClass, UpdateTaxClassData::validateAndCreate([
                'is_default' => true,
            ]));
        });

        $this->loadTaxClasses();
    }

    public function deleteTaxClass(int $taxClassId): void
    {
        $taxClass = ProductTaxClass::findOrFail($taxClassId);

        if ($taxClass->is_default) {
            $this->addError('deleteTaxClass', 'The default tax class cannot be deleted.');

            return;
        }

        (new DeleteProductTaxClass)($taxClass);
        $this->loadTaxClasses();
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="tax" title="Product Tax Classes" description="Manage product tax classifications.">
        <x-slot:actions>
            <flux:button variant="primary" href="{{ route('admin.settings.tax.product-tax-classes.create') }}" wire:navigate>
                Add Product Tax Class
            </flux:button>
        </x-slot:actions>

        @error('deleteTaxClass')
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                {{ $message }}
            </div>
        @enderror

        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Default</th>
                        <th class="w-[120px]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($taxClasses as $taxClass)
                        <tr wire:key="tax-class-{{ $taxClass->id }}">
                            <td class="font-medium">{{ $taxClass->name }}</td>
                            <td>{{ $taxClass->description }}</td>
                            <td>
                                @if($taxClass->is_default)
                                    <span class="s-badge s-badge-green">Default</span>
                                @else
                                    <button wire:click="setDefault({{ $taxClass->id }})" class="s-btn-ghost s-btn-xs">
                                        Set Default
                                    </button>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('admin.settings.tax.product-tax-classes.edit', $taxClass) }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm" title="Edit">
                                    <flux:icon.pencil-square class="w-4 h-4" />
                                </a>
                                @unless($taxClass->is_default)
                                    <button wire:click="deleteTaxClass({{ $taxClass->id }})"
                                            wire:confirm="Are you sure you want to delete this tax class?"
                                            class="s-btn-ghost s-btn-xs text-[var(--red)]">
                                        Delete
                                    </button>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-[var(--text-muted)]">No product tax classes configured.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.layout>
</section>
