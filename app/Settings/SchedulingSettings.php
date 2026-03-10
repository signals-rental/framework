<?php

namespace App\Settings;

class SchedulingSettings extends SettingsDefinition
{
    public function group(): string
    {
        return 'scheduling';
    }

    public function defaults(): array
    {
        return [
            'default_opportunity_duration_days' => 1,
            'default_buffer_before_minutes' => 0,
            'default_buffer_after_minutes' => 0,
            'collection_reminder_days' => 1,
            'return_reminder_days' => 1,
            'default_start_time' => '09:00',
            'default_end_time' => '17:00',
            'weekend_availability' => false,
        ];
    }

    public function rules(): array
    {
        return [
            'default_opportunity_duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'default_buffer_before_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'default_buffer_after_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'collection_reminder_days' => ['required', 'integer', 'min:0', 'max:30'],
            'return_reminder_days' => ['required', 'integer', 'min:0', 'max:30'],
            'default_start_time' => ['required', 'string', 'date_format:H:i'],
            'default_end_time' => ['required', 'string', 'date_format:H:i'],
            'weekend_availability' => ['required', 'boolean'],
        ];
    }

    public function types(): array
    {
        return [
            'default_opportunity_duration_days' => 'integer',
            'default_buffer_before_minutes' => 'integer',
            'default_buffer_after_minutes' => 'integer',
            'collection_reminder_days' => 'integer',
            'return_reminder_days' => 'integer',
            'weekend_availability' => 'boolean',
        ];
    }
}
