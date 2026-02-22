<?php

namespace App\Data\Reference;

class CountryData
{
    /**
     * Get defaults for a country code.
     *
     * @return array{name: string, currency: string, timezone: string, tax_rate: string, tax_label: string, date_format: string, time_format: string}|null
     */
    public static function defaults(string $countryCode): ?array
    {
        return static::all()[$countryCode] ?? null;
    }

    /**
     * Get all country defaults as an associative array keyed by ISO 3166-1 alpha-2 code.
     *
     * @return array<string, array{name: string, currency: string, timezone: string, tax_rate: string, tax_label: string, date_format: string, time_format: string}>
     */
    public static function all(): array
    {
        return [
            'GB' => ['name' => 'United Kingdom', 'currency' => 'GBP', 'timezone' => 'Europe/London', 'tax_rate' => '20.00', 'tax_label' => 'VAT', 'date_format' => 'd/m/Y', 'time_format' => 'H:i'],
            'US' => ['name' => 'United States', 'currency' => 'USD', 'timezone' => 'America/New_York', 'tax_rate' => '0.00', 'tax_label' => 'Sales Tax', 'date_format' => 'm/d/Y', 'time_format' => 'g:i A'],
            'CA' => ['name' => 'Canada', 'currency' => 'CAD', 'timezone' => 'America/Toronto', 'tax_rate' => '5.00', 'tax_label' => 'GST', 'date_format' => 'Y-m-d', 'time_format' => 'g:i A'],
            'AU' => ['name' => 'Australia', 'currency' => 'AUD', 'timezone' => 'Australia/Sydney', 'tax_rate' => '10.00', 'tax_label' => 'GST', 'date_format' => 'd/m/Y', 'time_format' => 'g:i A'],
            'NZ' => ['name' => 'New Zealand', 'currency' => 'NZD', 'timezone' => 'Pacific/Auckland', 'tax_rate' => '15.00', 'tax_label' => 'GST', 'date_format' => 'd/m/Y', 'time_format' => 'g:i A'],
            'IE' => ['name' => 'Ireland', 'currency' => 'EUR', 'timezone' => 'Europe/Dublin', 'tax_rate' => '23.00', 'tax_label' => 'VAT', 'date_format' => 'd/m/Y', 'time_format' => 'H:i'],
            'DE' => ['name' => 'Germany', 'currency' => 'EUR', 'timezone' => 'Europe/Berlin', 'tax_rate' => '19.00', 'tax_label' => 'MwSt', 'date_format' => 'd.m.Y', 'time_format' => 'H:i'],
            'FR' => ['name' => 'France', 'currency' => 'EUR', 'timezone' => 'Europe/Paris', 'tax_rate' => '20.00', 'tax_label' => 'TVA', 'date_format' => 'd/m/Y', 'time_format' => 'H:i'],
            'NL' => ['name' => 'Netherlands', 'currency' => 'EUR', 'timezone' => 'Europe/Amsterdam', 'tax_rate' => '21.00', 'tax_label' => 'BTW', 'date_format' => 'd-m-Y', 'time_format' => 'H:i'],
            'BE' => ['name' => 'Belgium', 'currency' => 'EUR', 'timezone' => 'Europe/Brussels', 'tax_rate' => '21.00', 'tax_label' => 'BTW/TVA', 'date_format' => 'd/m/Y', 'time_format' => 'H:i'],
            'ES' => ['name' => 'Spain', 'currency' => 'EUR', 'timezone' => 'Europe/Madrid', 'tax_rate' => '21.00', 'tax_label' => 'IVA', 'date_format' => 'd/m/Y', 'time_format' => 'H:i'],
            'IT' => ['name' => 'Italy', 'currency' => 'EUR', 'timezone' => 'Europe/Rome', 'tax_rate' => '22.00', 'tax_label' => 'IVA', 'date_format' => 'd/m/Y', 'time_format' => 'H:i'],
            'PT' => ['name' => 'Portugal', 'currency' => 'EUR', 'timezone' => 'Europe/Lisbon', 'tax_rate' => '23.00', 'tax_label' => 'IVA', 'date_format' => 'd/m/Y', 'time_format' => 'H:i'],
            'AT' => ['name' => 'Austria', 'currency' => 'EUR', 'timezone' => 'Europe/Vienna', 'tax_rate' => '20.00', 'tax_label' => 'USt', 'date_format' => 'd.m.Y', 'time_format' => 'H:i'],
            'CH' => ['name' => 'Switzerland', 'currency' => 'CHF', 'timezone' => 'Europe/Zurich', 'tax_rate' => '8.10', 'tax_label' => 'MWST', 'date_format' => 'd.m.Y', 'time_format' => 'H:i'],
            'SE' => ['name' => 'Sweden', 'currency' => 'SEK', 'timezone' => 'Europe/Stockholm', 'tax_rate' => '25.00', 'tax_label' => 'Moms', 'date_format' => 'Y-m-d', 'time_format' => 'H:i'],
            'NO' => ['name' => 'Norway', 'currency' => 'NOK', 'timezone' => 'Europe/Oslo', 'tax_rate' => '25.00', 'tax_label' => 'MVA', 'date_format' => 'd.m.Y', 'time_format' => 'H:i'],
            'DK' => ['name' => 'Denmark', 'currency' => 'DKK', 'timezone' => 'Europe/Copenhagen', 'tax_rate' => '25.00', 'tax_label' => 'Moms', 'date_format' => 'd.m.Y', 'time_format' => 'H:i'],
            'ZA' => ['name' => 'South Africa', 'currency' => 'ZAR', 'timezone' => 'Africa/Johannesburg', 'tax_rate' => '15.00', 'tax_label' => 'VAT', 'date_format' => 'Y/m/d', 'time_format' => 'H:i'],
            'AE' => ['name' => 'United Arab Emirates', 'currency' => 'AED', 'timezone' => 'Asia/Dubai', 'tax_rate' => '5.00', 'tax_label' => 'VAT', 'date_format' => 'd/m/Y', 'time_format' => 'h:i A'],
            'SG' => ['name' => 'Singapore', 'currency' => 'SGD', 'timezone' => 'Asia/Singapore', 'tax_rate' => '9.00', 'tax_label' => 'GST', 'date_format' => 'd/m/Y', 'time_format' => 'h:i A'],
            'JP' => ['name' => 'Japan', 'currency' => 'JPY', 'timezone' => 'Asia/Tokyo', 'tax_rate' => '10.00', 'tax_label' => '消費税', 'date_format' => 'Y/m/d', 'time_format' => 'H:i'],
            'IN' => ['name' => 'India', 'currency' => 'INR', 'timezone' => 'Asia/Kolkata', 'tax_rate' => '18.00', 'tax_label' => 'GST', 'date_format' => 'd/m/Y', 'time_format' => 'h:i A'],
        ];
    }

    /**
     * Get countries as select options (code => name).
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_map(fn (array $country) => $country['name'], static::all());
    }
}
