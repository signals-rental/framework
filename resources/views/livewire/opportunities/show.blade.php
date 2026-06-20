<?php

use App\Models\Opportunity;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Placeholder show page for a single opportunity.
 *
 * Resolves the route-model-bound opportunity and renders a gated placeholder.
 * The real detail view (tabs, items, versions, availability, shortage panel)
 * is built in M8 and replaces this file; route('opportunities.show', $id)
 * resolves now so search results and links work immediately.
 */
new #[Layout('components.layouts.app')] class extends Component
{
    public Opportunity $opportunity;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');

        $this->opportunity = $opportunity;
    }

    public function rendering(View $view): void
    {
        $view->title($this->opportunity->subject);
    }
}; ?>

<section class="w-full">
    <x-signals.page-header :title="$opportunity->subject" />

    <x-signals.card>
        <x-signals.empty
            title="Opportunity detail is coming soon"
            description="The opportunity detail interface is being built. This record is available now via the API."
        />
    </x-signals.card>
</section>
