<?php

namespace App\Settings;

use Illuminate\Validation\Rule;

class GeneralPreferencesSettings extends SettingsDefinition
{
    public function group(): string
    {
        return 'preferences';
    }

    public function defaults(): array
    {
        return [
            'number_decimal_separator' => '.',
            'number_thousands_separator' => ',',
            'currency_display' => 'symbol',
            'first_day_of_week' => 1,
            'items_per_page' => 25,
        ];
    }

    public function rules(): array
    {
        return [
            'number_decimal_separator' => ['required', 'string', Rule::in(['.', ','])],
            'number_thousands_separator' => ['required', 'string', Rule::in([',', '.', ' ', ''])],
            'currency_display' => ['required', 'string', 'in:symbol,code,name'],
            'first_day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'items_per_page' => ['required', 'integer', 'in:10,25,50,100'],
        ];
    }

    public function types(): array
    {
        return [
            'first_day_of_week' => 'integer',
            'items_per_page' => 'integer',
        ];
    }
}
