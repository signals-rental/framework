<?php

use App\Services\SettingsRegistry;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public int $defaultOpportunityDurationDays = 1;
    public int $defaultBufferBeforeMinutes = 0;
    public int $defaultBufferAfterMinutes = 0;
    public int $collectionReminderDays = 1;
    public int $returnReminderDays = 1;
    public string $defaultStartTime = '09:00';
    public string $defaultEndTime = '17:00';
    public bool $weekendAvailability = false;

    public function mount(): void
    {
        $group = settings()->group('scheduling');

        $this->defaultOpportunityDurationDays = (int) $group['default_opportunity_duration_days'];
        $this->defaultBufferBeforeMinutes = (int) $group['default_buffer_before_minutes'];
        $this->defaultBufferAfterMinutes = (int) $group['default_buffer_after_minutes'];
        $this->collectionReminderDays = (int) $group['collection_reminder_days'];
        $this->returnReminderDays = (int) $group['return_reminder_days'];
        $this->defaultStartTime = $group['default_start_time'];
        $this->defaultEndTime = $group['default_end_time'];
        $this->weekendAvailability = (bool) $group['weekend_availability'];
    }

    public function save(): void
    {
        $registry = app(SettingsRegistry::class);
        $rules = $registry->rules('scheduling');
        $types = $registry->types('scheduling');

        $validated = $this->validate([
            'defaultOpportunityDurationDays' => $rules['default_opportunity_duration_days'],
            'defaultBufferBeforeMinutes' => $rules['default_buffer_before_minutes'],
            'defaultBufferAfterMinutes' => $rules['default_buffer_after_minutes'],
            'collectionReminderDays' => $rules['collection_reminder_days'],
            'returnReminderDays' => $rules['return_reminder_days'],
            'defaultStartTime' => $rules['default_start_time'],
            'defaultEndTime' => $rules['default_end_time'],
            'weekendAvailability' => $rules['weekend_availability'],
        ]);

        settings()->setMany([
            'scheduling.default_opportunity_duration_days' => ['value' => $validated['defaultOpportunityDurationDays'], 'type' => $types['default_opportunity_duration_days']],
            'scheduling.default_buffer_before_minutes' => ['value' => $validated['defaultBufferBeforeMinutes'], 'type' => $types['default_buffer_before_minutes']],
            'scheduling.default_buffer_after_minutes' => ['value' => $validated['defaultBufferAfterMinutes'], 'type' => $types['default_buffer_after_minutes']],
            'scheduling.collection_reminder_days' => ['value' => $validated['collectionReminderDays'], 'type' => $types['collection_reminder_days']],
            'scheduling.return_reminder_days' => ['value' => $validated['returnReminderDays'], 'type' => $types['return_reminder_days']],
            'scheduling.default_start_time' => $validated['defaultStartTime'],
            'scheduling.default_end_time' => $validated['defaultEndTime'],
            'scheduling.weekend_availability' => ['value' => $validated['weekendAvailability'], 'type' => $types['weekend_availability']],
        ]);

        $this->dispatch('scheduling-settings-saved');
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="preferences" title="Scheduling" description="Configure default durations, buffer times, and availability for opportunities.">
        <form wire:submit="save" class="space-y-8">
            <x-signals.form-section title="Opportunity Defaults">
                <div class="space-y-4">
                    <flux:input wire:model="defaultOpportunityDurationDays" label="Default Duration (days)" type="number" min="1" max="365" />
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="defaultBufferBeforeMinutes" label="Buffer Before (minutes)" type="number" min="0" max="1440" />
                        <flux:input wire:model="defaultBufferAfterMinutes" label="Buffer After (minutes)" type="number" min="0" max="1440" />
                    </div>
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Reminders">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="collectionReminderDays" label="Collection Reminder (days before)" type="number" min="0" max="30" />
                        <flux:input wire:model="returnReminderDays" label="Return Reminder (days before)" type="number" min="0" max="30" />
                    </div>
                    <p class="text-xs text-zinc-500">Set to 0 to disable reminders.</p>
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Working Hours">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="defaultStartTime" label="Default Start Time" type="time" />
                        <flux:input wire:model="defaultEndTime" label="Default End Time" type="time" />
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer" x-data="{ checked: @js($weekendAvailability) }">
                        <input type="checkbox" wire:model="weekendAvailability" class="hidden" x-on:change="checked = $el.checked" />
                        <x-signals.checkbox x-bind:class="checked && 'checked'" />
                        <span class="text-sm">Allow weekend availability</span>
                    </label>
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">Save Changes</flux:button>

                <x-action-message on="scheduling-settings-saved">
                    Saved.
                </x-action-message>
            </div>
        </form>
    </x-admin.layout>
</section>
