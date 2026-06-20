<?php

use App\Models\CustomField;
use App\Models\Opportunity;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Opportunity $opportunity;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');

        $this->opportunity = $opportunity;
    }

    public function rendering(View $view): void
    {
        $view->title($this->opportunity->subject.' — Custom Fields');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $fields = CustomField::query()
            ->forModule('Opportunity')
            ->active()
            ->with('group')
            ->orderBy('sort_order')
            ->get();

        $values = $this->opportunity->customFieldValues()
            ->with('customField')
            ->get()
            ->keyBy('custom_field_id');

        $grouped = $fields->groupBy(fn ($field) => $field->group?->name ?? 'General');

        return [
            'grouped' => $grouped,
            'values' => $values,
        ];
    }
}; ?>

<section class="w-full">
    @include('livewire.opportunities.partials.opportunity-header', ['opportunity' => $opportunity, 'subpage' => 'Custom Fields'])
    @include('livewire.opportunities.partials.opportunity-tabs', ['opportunity' => $opportunity, 'activeTab' => 'custom-fields'])

    <div class="flex-1 p-8 max-md:p-5 max-sm:p-3">
        <div class="max-w-2xl space-y-8">
            <x-signals.custom-fields-display :grouped="$grouped" :values="$values" emptyMessage="No custom fields have been configured for opportunities." />
        </div>
    </div>
</section>
