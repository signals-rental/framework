<?php

use App\Models\Member;
use App\Views\OpportunityColumnRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Member $member;

    public function mount(Member $member): void
    {
        Gate::authorize('opportunities.access');

        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links', 'organisations', 'contacts']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->member->name . ' — Opportunities');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        // Derive sortable/filterable flags from the canonical OpportunityColumnRegistry
        // so this related list's column metadata stays in lockstep with the main
        // opportunities index and the custom-view / column-picker machinery.
        $registry = app(OpportunityColumnRegistry::class);
        $columnMeta = fn (string $key): array => [
            'sortable' => $registry->get($key)?->sortable ?? false,
            'filterable' => $registry->get($key)?->filterable ?? false,
        ];

        return [
            'columns' => [
                ['key' => 'subject', 'label' => 'Subject', ...$columnMeta('subject'), 'filter_type' => 'text', 'view' => 'livewire.opportunities.partials.column-subject'],
                ['key' => 'reference', 'label' => 'Reference', ...$columnMeta('reference'), 'filter_type' => 'text', 'view' => 'livewire.opportunities.partials.column-reference'],
                ['key' => 'state', 'label' => 'State', ...$columnMeta('state'), 'view' => 'livewire.opportunities.partials.column-state'],
                ['key' => 'status', 'label' => 'Status', ...$columnMeta('status'), 'view' => 'livewire.opportunities.partials.column-status'],
                ['key' => 'starts_at', 'label' => 'Starts', ...$columnMeta('starts_at'), 'view' => 'livewire.opportunities.partials.column-starts-at'],
                ['key' => 'ends_at', 'label' => 'Ends', ...$columnMeta('ends_at'), 'view' => 'livewire.opportunities.partials.column-ends-at'],
                ['key' => 'charge_total', 'label' => 'Charge Total', ...$columnMeta('charge_total'), 'view' => 'livewire.opportunities.partials.column-charge-total'],
                ['key' => 'created_at', 'label' => 'Created', ...$columnMeta('created_at'), 'view' => 'livewire.opportunities.partials.column-created'],
                ['key' => 'actions', 'type' => 'actions'],
            ],
            'scopes' => [
                'forMember' => $this->member->id,
            ],
        ];
    }
}; ?>

<section class="w-full">
    @include('livewire.members.partials.member-header', ['member' => $member, 'subpage' => 'Opportunities'])
    @include('livewire.members.partials.member-tabs', ['member' => $member, 'activeTab' => 'opportunities'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Models\Opportunity::class"
            :searchable="['subject', 'number', 'reference']"
            :with="['member', 'store']"
            :scopes="$scopes"
            default-sort="-created_at"
            empty-message="No opportunities for this member yet."
            actions-view="livewire.members.partials.opportunity-row-actions"
            :key="'member-opportunities-' . $member->id"
        />
    </div>
</section>
