<?php

use App\Actions\Tax\DeleteTaxRule;
use App\Models\TaxRule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Tax Rules')] class extends Component {
    /** @var \Illuminate\Database\Eloquent\Collection<int, TaxRule> */
    public $taxRules;

    public function mount(): void
    {
        $this->loadTaxRules();
    }

    public function loadTaxRules(): void
    {
        $this->taxRules = TaxRule::query()
            ->with(['organisationTaxClass', 'productTaxClass', 'taxRate'])
            ->orderBy('priority')
            ->get();
    }

    public function deleteTaxRule(int $taxRuleId): void
    {
        $taxRule = TaxRule::findOrFail($taxRuleId);
        (new DeleteTaxRule)($taxRule);
        $this->loadTaxRules();
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="tax" title="Tax Rules" description="Map organisation and product tax classes to tax rates.">
        <x-slot:actions>
            <flux:button variant="primary" href="{{ route('admin.settings.tax.rules.create') }}" wire:navigate>
                Add Tax Rule
            </flux:button>
        </x-slot:actions>

        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Organisation Tax Class</th>
                        <th>Product Tax Class</th>
                        <th>Tax Rate</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th class="w-[120px]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($taxRules as $taxRule)
                        <tr wire:key="tax-rule-{{ $taxRule->id }}">
                            <td>{{ $taxRule->organisationTaxClass->name }}</td>
                            <td>{{ $taxRule->productTaxClass->name }}</td>
                            <td>{{ $taxRule->taxRate->name }} ({{ number_format($taxRule->taxRate->rate, 2) }}%)</td>
                            <td>{{ $taxRule->priority }}</td>
                            <td>
                                @if($taxRule->is_active)
                                    <span class="s-badge s-badge-green">Active</span>
                                @else
                                    <span class="s-badge s-badge-zinc">Inactive</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('admin.settings.tax.rules.edit', $taxRule) }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm" title="Edit">
                                    <flux:icon.pencil-square class="w-4 h-4" />
                                </a>
                                <button wire:click="deleteTaxRule({{ $taxRule->id }})"
                                        wire:confirm="Are you sure you want to delete this tax rule?"
                                        class="s-btn-ghost s-btn-xs text-[var(--red)]">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-[var(--text-muted)]">No tax rules configured.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.layout>
</section>
