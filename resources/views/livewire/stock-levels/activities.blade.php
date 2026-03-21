<?php

use App\Models\Activity;
use App\Models\StockLevel;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public StockLevel $stockLevel;

    public function mount(StockLevel $stockLevel): void
    {
        $this->stockLevel = $stockLevel->load(['product', 'store']);
    }

    public function rendering(View $view): void
    {
        $name = $this->stockLevel->item_name ?? ('Asset #' . $this->stockLevel->asset_number);
        $view->title($name . ' — Activities');
    }

    public function completeActivity(int $activityId): void
    {
        $activity = Activity::findOrFail($activityId);
        (new \App\Actions\Activities\CompleteActivity)($activity);
        $this->dispatch('activity-completed');
    }

    public function deleteActivity(int $activityId): void
    {
        $activity = Activity::findOrFail($activityId);
        (new \App\Actions\Activities\DeleteActivity)($activity);
        $this->dispatch('activity-deleted');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'columns' => [
                ['key' => 'type_id', 'label' => 'Type', 'sortable' => true, 'view' => 'livewire.activities.partials.column-type'],
                ['key' => 'subject', 'label' => 'Subject', 'sortable' => true],
                ['key' => 'owner', 'label' => 'Owner', 'view' => 'livewire.activities.partials.column-owner'],
                ['key' => 'starts_at', 'label' => 'Starts', 'sortable' => true],
                ['key' => 'priority', 'label' => 'Priority', 'sortable' => true, 'view' => 'livewire.activities.partials.column-priority'],
                ['key' => 'status_id', 'label' => 'Status', 'sortable' => true, 'view' => 'livewire.activities.partials.column-status'],
                ['key' => 'actions', 'type' => 'actions'],
            ],
            'scopes' => [
                'forStockLevel' => $this->stockLevel->id,
            ],
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header :title="$stockLevel->item_name ?? ('Asset #' . $stockLevel->asset_number)">
        <x-slot:breadcrumbs>
            <a href="{{ route('stock-levels.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Stock Levels</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <a href="{{ route('stock-levels.show', $stockLevel->id) }}" wire:navigate class="text-[var(--link)] hover:underline">{{ $stockLevel->item_name ?? ('Asset #' . $stockLevel->asset_number) }}</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>Activities</span>
        </x-slot:breadcrumbs>
    </x-signals.page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="mb-4 flex justify-end">
            <a href="{{ route('activities.create', ['regarding_type' => 'StockLevel', 'regarding_id' => $stockLevel->id]) }}" wire:navigate class="s-btn s-btn-sm s-btn-primary">
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
            empty-message="No activities for this stock level."
            actions-view="livewire.activities.partials.row-actions"
            :key="'stock-level-activities-' . $stockLevel->id"
        />
    </div>
</section>
