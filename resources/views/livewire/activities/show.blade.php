<?php

use App\Models\Activity;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Activity $activity;

    public function mount(Activity $activity): void
    {
        $this->activity = $activity->load(['owner', 'participants.member', 'regarding']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->activity->subject);
    }

    public function completeActivity(): void
    {
        (new \App\Actions\Activities\CompleteActivity)($this->activity);
        $this->activity->refresh();
    }

    public function deleteActivity(): void
    {
        (new \App\Actions\Activities\DeleteActivity)($this->activity);
        $this->redirect(route('activities.index'), navigate: true);
    }
}; ?>

<section class="w-full">
    <x-signals.page-header :title="$activity->subject">
        <x-slot:breadcrumbs>
            <a href="{{ route('activities.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Activities</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>{{ $activity->subject }}</span>
        </x-slot:breadcrumbs>
        <x-slot:meta>
            <div class="flex items-center gap-2">
                <span class="s-badge s-badge-blue">{{ $activity->type_id->label() }}</span>
                <span class="s-badge {{ $activity->status_id === \App\Enums\ActivityStatus::Completed ? 's-badge-green' : ($activity->status_id === \App\Enums\ActivityStatus::Cancelled ? 's-badge-zinc' : 's-badge-amber') }}">
                    {{ $activity->status_id->label() }}
                </span>
                <span class="s-badge {{ $activity->priority === \App\Enums\ActivityPriority::High ? 's-badge-red' : ($activity->priority === \App\Enums\ActivityPriority::Low ? 's-badge-zinc' : 's-badge-blue') }}">
                    {{ $activity->priority->label() }} Priority
                </span>
            </div>
        </x-slot:meta>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @if(!$activity->completed)
                    <button wire:click="completeActivity" class="s-btn s-btn-sm s-btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><polyline points="20 6 9 17 4 12"/></svg>
                        Complete
                    </button>
                @endif
                <a href="{{ route('activities.edit', $activity->id) }}" wire:navigate class="s-btn s-btn-sm s-btn-secondary">Edit</a>
                <button wire:click="deleteActivity" wire:confirm="Are you sure you want to delete this activity?" class="s-btn s-btn-sm s-btn-danger">Delete</button>
            </div>
        </x-slot:actions>
    </x-signals.page-header>

    <div class="grid grid-cols-[1fr_280px] gap-6 px-6 py-4 max-md:grid-cols-1 max-md:px-5 max-sm:px-3">
        {{-- Main Content --}}
        <div class="space-y-6">
            <x-signals.panel title="Details">
                <x-signals.data-list layout="vertical" :items="array_filter([
                    ['label' => 'Subject', 'value' => $activity->subject],
                    $activity->description ? ['label' => 'Description', 'value' => $activity->description] : null,
                    $activity->location ? ['label' => 'Location', 'value' => $activity->location] : null,
                    ['label' => 'Type', 'value' => $activity->type_id->label()],
                    ['label' => 'Status', 'value' => $activity->status_id->label()],
                    ['label' => 'Priority', 'value' => $activity->priority->label()],
                    ['label' => 'Time Status', 'value' => $activity->time_status->label()],
                    ['label' => 'Owner', 'value' => $activity->owner?->name ?? '—'],
                    $activity->starts_at ? ['label' => 'Starts At', 'value' => $activity->starts_at->format('d M Y H:i')] : null,
                    $activity->ends_at ? ['label' => 'Ends At', 'value' => $activity->ends_at->format('d M Y H:i')] : null,
                ])" />
            </x-signals.panel>

            @if($activity->regarding)
                <x-signals.panel title="Regarding">
                    <div class="flex items-center gap-2">
                        <span class="s-badge s-badge-zinc">{{ $activity->regarding_type }}</span>
                        <span class="text-sm font-medium">{{ $activity->regarding->name ?? '—' }}</span>
                    </div>
                </x-signals.panel>
            @endif

            @if($activity->participants->isNotEmpty())
                <x-signals.panel title="Participants">
                    <div class="space-y-2">
                        @foreach($activity->participants as $participant)
                            <div class="flex items-center justify-between" wire:key="participant-{{ $participant->id }}">
                                <span class="text-sm">{{ $participant->member?->name ?? 'Unknown' }}</span>
                                @if($participant->mute)
                                    <span class="s-badge s-badge-zinc">Muted</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-signals.panel>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            <x-signals.panel title="Key Info">
                <x-signals.data-list layout="vertical" :items="[
                    ['label' => 'Created', 'value' => $activity->created_at?->format('d M Y H:i') ?? '—'],
                    ['label' => 'Updated', 'value' => $activity->updated_at?->format('d M Y H:i') ?? '—'],
                    ['label' => 'Completed', 'value' => $activity->completed ? 'Yes' : 'No'],
                ]" />
            </x-signals.panel>

            @if(!empty($activity->tag_list))
                <x-signals.panel title="Tags">
                    <div class="flex flex-wrap gap-1">
                        @foreach($activity->tag_list as $tag)
                            <span class="s-badge s-badge-blue">{{ $tag }}</span>
                        @endforeach
                    </div>
                </x-signals.panel>
            @endif
        </div>
    </div>
</section>
