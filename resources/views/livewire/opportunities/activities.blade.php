<?php

use App\Livewire\Concerns\HasActivityActions;
use App\Livewire\Concerns\HasOpportunityActions;
use App\Models\Opportunity;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    use HasActivityActions;
    use HasOpportunityActions;

    public Opportunity $opportunity;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');

        $this->opportunity = $opportunity;
    }

    public function rendering(View $view): void
    {
        $view->title($this->opportunity->subject.' — Activities');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return array_merge($this->opportunityActionData(), [
            'columns' => $this->activityColumns(),
            'scopes' => [
                'forOpportunity' => $this->opportunity->id,
            ],
        ]);
    }
}; ?>

<section class="w-full">
    @include('livewire.opportunities.partials.opportunity-header', ['opportunity' => $opportunity, 'subpage' => 'Activities', 'showActions' => true, 'canChangeStatus' => $canChangeStatus])
    @include('livewire.opportunities.partials.opportunity-tabs', ['opportunity' => $opportunity, 'activeTab' => 'activities'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="mb-4 flex justify-end">
            <a href="{{ route('activities.create', ['regarding_type' => 'Opportunity', 'regarding_id' => $opportunity->id]) }}" wire:navigate class="s-btn s-btn-sm s-btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Activity
            </a>
        </div>

        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Models\Activity::class"
            :searchable="['subject']"
            :with="['owner']"
            :scopes="$scopes"
            :refresh-events="['activity-completed', 'activity-deleted']"
            default-sort="-created_at"
            empty-message="No activities for this opportunity."
            actions-view="livewire.activities.partials.row-actions"
            :key="'opportunity-activities-' . $opportunity->id"
        />
    </div>
</section>
