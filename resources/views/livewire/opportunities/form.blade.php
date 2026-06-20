<?php

use App\Models\Opportunity;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/**
 * Placeholder create/edit form for opportunities.
 *
 * Serves both opportunities.create and opportunities.edit so the named routes
 * resolve now. The real form (DTO → action, line-item editor, custom fields)
 * is built in M8 and replaces this file. The optional {opportunity} binding is
 * present so the edit route resolves without erroring.
 */
new #[Layout('components.layouts.app')] #[Title('Opportunity')] class extends Component
{
    public ?Opportunity $opportunity = null;

    public function mount(?Opportunity $opportunity = null): void
    {
        if ($opportunity?->exists) {
            Gate::authorize('opportunities.edit');
            $this->opportunity = $opportunity;

            return;
        }

        Gate::authorize('opportunities.create');
    }
}; ?>

<section class="w-full">
    <x-signals.page-header :title="$opportunity ? 'Edit opportunity' : 'New opportunity'" />

    <x-signals.card>
        <x-signals.empty
            title="The opportunity form is coming soon"
            description="The create and edit interface is being built. Opportunities can be created now via the API."
        />
    </x-signals.card>
</section>
