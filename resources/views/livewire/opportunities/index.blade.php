<?php

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/**
 * Placeholder index page for the opportunities module.
 *
 * The full Livewire list/show/form components land in M8; this thin gated
 * placeholder exists so the named web routes (opportunities.index, .show,
 * .create, .edit) resolve NOW — the global search block, member sub-pages and
 * future nav can all call route('opportunities.*') without 500ing. M8 replaces
 * this component file in place; the route names do not change.
 */
new #[Layout('components.layouts.app')] #[Title('Opportunities')] class extends Component
{
    public function mount(): void
    {
        Gate::authorize('opportunities.access');
    }
}; ?>

<section class="w-full">
    <x-signals.page-header title="Opportunities" />

    <x-signals.card>
        <x-signals.empty
            title="Opportunities are coming soon"
            description="The opportunities interface is being built. The quoting, ordering and availability data is already live via the API."
        />
    </x-signals.card>
</section>
