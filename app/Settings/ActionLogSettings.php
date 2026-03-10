<?php

namespace App\Settings;

class ActionLogSettings extends SettingsDefinition
{
    public function group(): string
    {
        return 'action-log';
    }

    public function defaults(): array
    {
        return [
            'retention_months' => 12,
        ];
    }

    public function rules(): array
    {
        return [
            'retention_months' => ['required', 'integer', 'min:1', 'max:120'],
        ];
    }

    public function types(): array
    {
        return [
            'retention_months' => 'integer',
        ];
    }
}
