<?php

use App\Actions\TaxClasses\CreateOrganisationTaxClass;
use App\Actions\TaxClasses\UpdateOrganisationTaxClass;
use App\Data\TaxClasses\CreateTaxClassData;
use App\Data\TaxClasses\UpdateTaxClassData;
use App\Models\OrganisationTaxClass;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Organisation Tax Class')] class extends Component {
    public ?int $taxClassId = null;

    public string $name = '';
    public string $description = '';
    public bool $isDefault = false;

    public function mount(?OrganisationTaxClass $organisationTaxClass = null): void
    {
        if ($organisationTaxClass?->exists) {
            $this->taxClassId = $organisationTaxClass->id;
            $this->name = $organisationTaxClass->name;
            $this->description = $organisationTaxClass->description ?? '';
            $this->isDefault = $organisationTaxClass->is_default;
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
            $taxClass = OrganisationTaxClass::findOrFail($this->taxClassId);

            if ($this->isDefault && ! $taxClass->is_default) {
                $taxClass->getConnection()->transaction(function () use ($taxClass) {
                    OrganisationTaxClass::query()->where('is_default', true)->update(['is_default' => false]);
                    (new UpdateOrganisationTaxClass)($taxClass, UpdateTaxClassData::validateAndCreate([
                        'name' => $this->name,
                        'description' => $this->description,
                        'is_default' => true,
                    ]));
                });
            } else {
                (new UpdateOrganisationTaxClass)($taxClass, UpdateTaxClassData::validateAndCreate([
                    'name' => $this->name,
                    'description' => $this->description,
                    'is_default' => $this->isDefault,
                ]));
            }
        } else {
            $isDefault = $this->isDefault || OrganisationTaxClass::count() === 0;

            if ($isDefault) {
                OrganisationTaxClass::query()->getConnection()->transaction(function () {
                    OrganisationTaxClass::query()->where('is_default', true)->update(['is_default' => false]);
                    (new CreateOrganisationTaxClass)(CreateTaxClassData::validateAndCreate([
                        'name' => $this->name,
                        'description' => $this->description,
                        'is_default' => true,
                    ]));
                });
            } else {
                (new CreateOrganisationTaxClass)(CreateTaxClassData::validateAndCreate([
                    'name' => $this->name,
                    'description' => $this->description,
                    'is_default' => false,
                ]));
            }
        }

        $this->redirect(route('admin.settings.tax.organisation-tax-classes'), navigate: true);
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="tax" :title="$isEditing ? 'Edit Organisation Tax Class' : 'Create Organisation Tax Class'" :description="$isEditing ? 'Update this organisation tax class.' : 'Add a new organisation tax classification.'">
        <x-slot:actions>
            <flux:button variant="ghost" href="{{ route('admin.settings.tax.organisation-tax-classes') }}" wire:navigate>
                Back to Organisation Tax Classes
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
                <flux:button variant="ghost" href="{{ route('admin.settings.tax.organisation-tax-classes') }}" wire:navigate>
                    Cancel
                </flux:button>
            </div>
        </form>
    </x-admin.layout>
</section>
