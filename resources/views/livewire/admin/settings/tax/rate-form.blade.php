<?php

use App\Actions\Tax\CreateTaxRate;
use App\Actions\Tax\UpdateTaxRate;
use App\Data\Tax\CreateTaxRateData;
use App\Data\Tax\UpdateTaxRateData;
use App\Models\TaxRate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Tax Rate')] class extends Component {
    public ?int $taxRateId = null;

    public string $name = '';
    public string $description = '';
    public string $rate = '0.00';
    public bool $isActive = true;

    public function mount(?TaxRate $taxRate = null): void
    {
        if ($taxRate?->exists) {
            $this->taxRateId = $taxRate->id;
            $this->name = $taxRate->name;
            $this->description = $taxRate->description ?? '';
            $this->rate = number_format((float) $taxRate->rate, 2);
            $this->isActive = $taxRate->is_active;
        }
    }

    public function with(): array
    {
        return [
            'isEditing' => $this->taxRateId !== null,
        ];
    }

    public function save(): void
    {
        if ($this->taxRateId) {
            $taxRate = TaxRate::findOrFail($this->taxRateId);
            (new UpdateTaxRate)($taxRate, UpdateTaxRateData::validateAndCreate([
                'name' => $this->name,
                'description' => $this->description,
                'rate' => $this->rate,
                'is_active' => $this->isActive,
            ]));
        } else {
            (new CreateTaxRate)(CreateTaxRateData::validateAndCreate([
                'name' => $this->name,
                'description' => $this->description,
                'rate' => $this->rate,
                'is_active' => $this->isActive,
            ]));
        }

        $this->redirect(route('admin.settings.tax.rates'), navigate: true);
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="tax" :title="$isEditing ? 'Edit Tax Rate' : 'Create Tax Rate'" :description="$isEditing ? 'Update this tax rate.' : 'Add a new tax rate.'">
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'Tax Rates', 'href' => route('admin.settings.tax.rates')],
                ['label' => $isEditing ? 'Edit' : 'Create'],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <flux:button variant="ghost" href="{{ route('admin.settings.tax.rates') }}" wire:navigate>
                Back to Tax Rates
            </flux:button>
        </x-slot:actions>

        <form wire:submit="save" class="space-y-6">
            <x-signals.form-section title="Tax Rate Details">
                <div class="space-y-4">
                    <flux:input wire:model="name" label="Name" required />
                    @error('name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    <flux:textarea wire:model="description" label="Description" rows="2" />
                    @error('description') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    <flux:input wire:model="rate" label="Rate (%)" type="number" step="0.01" min="0" max="100" required />
                    @error('rate') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    <flux:checkbox wire:model="isActive" label="Active" />
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">
                    {{ $isEditing ? 'Update Tax Rate' : 'Create Tax Rate' }}
                </flux:button>
                <flux:button variant="ghost" href="{{ route('admin.settings.tax.rates') }}" wire:navigate>
                    Cancel
                </flux:button>
            </div>
        </form>
    </x-admin.layout>
</section>
