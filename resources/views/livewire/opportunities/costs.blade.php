<?php

use App\Models\Opportunity;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Read-only costs listing for an opportunity (M8-2).
 */
new #[Layout('components.layouts.app')] class extends Component
{
    public Opportunity $opportunity;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');

        $this->opportunity = $opportunity->load('costs');
    }

    public function rendering(View $view): void
    {
        $view->title($this->opportunity->subject.' — Costs');
    }
}; ?>

<section class="w-full">
    @include('livewire.opportunities.partials.opportunity-header', ['opportunity' => $opportunity, 'subpage' => 'Costs'])
    @include('livewire.opportunities.partials.opportunity-tabs', ['opportunity' => $opportunity, 'activeTab' => 'costs'])

    @php
        $formatter = app(\App\Support\Formatter::class);
    @endphp

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        @if($opportunity->costs->isNotEmpty())
            <x-signals.table-wrap>
                <table class="s-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Cost Type</th>
                            <th>Transaction Type</th>
                            <th class="text-right">Amount</th>
                            <th class="text-right">Qty</th>
                            <th>Optional</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($opportunity->costs as $cost)
                            <tr wire:key="cost-{{ $cost->id }}">
                                <td>{{ $cost->description }}</td>
                                <td>{{ $cost->cost_type->label() }}</td>
                                <td>{{ $cost->transaction_type->label() }}</td>
                                <td class="text-right" style="font-family: var(--font-mono);">{{ $formatter->money($cost->amount) }}</td>
                                <td class="text-right" style="font-family: var(--font-mono);">{{ rtrim(rtrim(number_format((float) $cost->quantity, 2), '0'), '.') }}</td>
                                <td>
                                    @if($cost->is_optional)
                                        <span class="s-badge s-badge-amber">Optional</span>
                                    @else
                                        <span class="text-[var(--text-muted)]">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-signals.table-wrap>
        @else
            <x-signals.empty
                title="No costs"
                description="This opportunity has no additional costs recorded."
            />
        @endif
    </div>
</section>
