<?php

namespace App\Settings;

class CompanySettings extends SettingsDefinition
{
    public function group(): string
    {
        return 'company';
    }

    public function defaults(): array
    {
        return [
            'name' => '',
            'timezone' => 'UTC',
            'date_format' => 'd/m/Y',
            'date_format_php' => 'd/m/Y',
            'date_format_js' => 'DD/MM/YYYY',
            'time_format' => 'H:i',
            'time_format_php' => 'H:i',
            'number_format' => '#,##0.00',
            'fiscal_year_start' => 1,
            'base_currency' => 'GBP',
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'date_format' => ['required', 'string', 'max:20'],
            'date_format_php' => ['required', 'string', 'max:20'],
            'date_format_js' => ['required', 'string', 'max:20'],
            'time_format' => ['required', 'string', 'max:20'],
            'time_format_php' => ['required', 'string', 'max:20'],
            'number_format' => ['required', 'string', 'max:20'],
            'fiscal_year_start' => ['required', 'integer', 'min:1', 'max:12'],
            'base_currency' => ['required', 'string', 'size:3'],
        ];
    }

    public function types(): array
    {
        return [
            'fiscal_year_start' => 'integer',
        ];
    }
}
