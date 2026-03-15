<?php

use App\Actions\Tax\DeleteTaxRate;
use App\Models\TaxRate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Tax Rates')] class extends Component {
    /** @var \Illuminate\Database\Eloquent\Collection<int, TaxRate> */
    public $taxRates;

    public function mount(): void
    {
        $this->loadTaxRates();
    }

    public function loadTaxRates(): void
    {
        $this->taxRates = TaxRate::query()->orderBy('name')->get();
    }

    public function deleteTaxRate(int $taxRateId): void
    {
        $taxRate = TaxRate::findOrFail($taxRateId);

        if ($taxRate->taxRules()->exists()) {
            $this->addError('deleteTaxRate', 'This tax rate is used by one or more tax rules and cannot be deleted.');

            return;
        }

        (new DeleteTaxRate)($taxRate);
        $this->loadTaxRates();
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="tax" title="Tax Rates" description="Manage tax rates applied through tax rules.">
        <x-slot:actions>
            <flux:button variant="primary" href="{{ route('admin.settings.tax.rates.create') }}" wire:navigate>
                Add Tax Rate
            </flux:button>
        </x-slot:actions>

        @error('deleteTaxRate')
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
                        <th>Rate</th>
                        <th>Status</th>
                        <th class="w-[120px]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($taxRates as $taxRate)
                        <tr wire:key="tax-rate-{{ $taxRate->id }}">
                            <td class="font-medium">{{ $taxRate->name }}</td>
                            <td>{{ $taxRate->description }}</td>
                            <td>{{ number_format($taxRate->rate, 2) }}%</td>
                            <td>
                                @if($taxRate->is_active)
                                    <span class="s-badge s-badge-green">Active</span>
                                @else
                                    <span class="s-badge s-badge-zinc">Inactive</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('admin.settings.tax.rates.edit', $taxRate) }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm" title="Edit">
                                    <flux:icon.pencil-square class="w-4 h-4" />
                                </a>
                                <button wire:click="deleteTaxRate({{ $taxRate->id }})"
                                        wire:confirm="Are you sure you want to delete this tax rate?"
                                        class="s-btn-ghost s-btn-xs text-[var(--red)]">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-[var(--text-muted)]">No tax rates configured.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.layout>
</section>
