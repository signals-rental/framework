<?php

use App\Actions\Rates\DeleteRateDefinition;
use App\Models\RateDefinition;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Rate Definitions')] class extends Component {
    public ?int $confirmingDeletion = null;

    /**
     * @return array{definitions: Collection<int, RateDefinition>}
     */
    public function with(): array
    {
        return [
            'definitions' => RateDefinition::query()
                ->withCount('productRates')
                ->orderByDesc('is_preset')
                ->orderBy('name')
                ->get(),
        ];
    }

    public function deleteDefinition(int $definitionId): void
    {
        $definition = RateDefinition::findOrFail($definitionId);

        (new DeleteRateDefinition)($definition);

        $this->confirmingDeletion = null;
        $this->dispatch('rate-definition-deleted');
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="pricing" title="Rate Definitions" description="Composable pricing engines used to calculate rental, sale, and service charges.">
        <x-slot:actions>
            <flux:button variant="primary" href="{{ route('admin.settings.rate-definitions.create') }}" wire:navigate>New Rate Definition</flux:button>
        </x-slot:actions>

        @if($definitions->isEmpty())
            <x-signals.empty
                icon="calculator"
                title="No rate definitions yet"
                description="Create a rate definition from a preset or from scratch to start pricing products."
            />
        @else
            <div class="s-table-wrap">
                <table class="s-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Strategy</th>
                            <th>Base Period</th>
                            <th>Modifiers</th>
                            <th>In Use</th>
                            <th>Type</th>
                            <th class="w-24"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($definitions as $definition)
                            <tr wire:key="rate-definition-{{ $definition->id }}">
                                <td class="font-medium">
                                    <a href="{{ route('admin.settings.rate-definitions.edit', $definition) }}" wire:navigate class="hover:underline">
                                        {{ $definition->name }}
                                    </a>
                                </td>
                                <td>{{ $definition->calculation_strategy->label() }}</td>
                                <td>{{ $definition->base_period?->label() ?? '—' }}</td>
                                <td class="text-sm text-[var(--text-secondary)]">
                                    {{ empty($definition->enabled_modifiers) ? '—' : count($definition->enabled_modifiers) }}
                                </td>
                                <td>{{ $definition->product_rates_count }}</td>
                                <td>
                                    @if($definition->is_preset)
                                        <span class="s-badge s-badge-blue">Preset</span>
                                    @else
                                        <span class="s-badge">Custom</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex gap-2">
                                        <a href="{{ route('admin.settings.rate-definitions.edit', $definition) }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm" title="Edit">
                                            <flux:icon.pencil-square class="w-4 h-4" />
                                        </a>
                                        @unless($definition->is_preset)
                                            <button type="button" class="s-btn s-btn-ghost s-btn-sm text-red-600" wire:click="$set('confirmingDeletion', {{ $definition->id }})" title="Delete">
                                                <flux:icon.trash class="w-4 h-4" />
                                            </button>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <x-action-message on="rate-definition-deleted">Rate definition deleted.</x-action-message>

        @if($confirmingDeletion)
            <flux:modal wire:model.self="confirmingDeletion">
                <div class="space-y-4">
                    <flux:heading size="lg">Delete Rate Definition</flux:heading>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        Are you sure you want to delete this rate definition? Products assigned to it will fall back to default pricing. This action cannot be undone.
                    </p>
                    <div class="flex justify-end gap-3">
                        <flux:button variant="ghost" wire:click="$set('confirmingDeletion', null)">Cancel</flux:button>
                        <flux:button variant="danger" wire:click="deleteDefinition({{ $confirmingDeletion }})">Delete</flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif
    </x-admin.layout>
</section>
