<?php

namespace App\Support;

use DateTimeInterface;

class Formatter
{
    /**
     * Format a date for display using the configured date format.
     */
    public function date(DateTimeInterface|string $date): string
    {
        $local = app(Timezone::class)->toLocal($date);
        $format = settings('company.date_format_php') ?? 'd/m/Y';

        return $local->format($format);
    }

    /**
     * Format a datetime for display using the configured date and time formats.
     */
    public function dateTime(DateTimeInterface|string $datetime): string
    {
        $local = app(Timezone::class)->toLocal($datetime);
        $dateFormat = settings('company.date_format_php') ?? 'd/m/Y';
        $timeFormat = settings('company.time_format_php') ?? 'H:i';

        return $local->format("{$dateFormat} {$timeFormat}");
    }

    /**
     * Format a number for display using configured separators.
     */
    public function number(float|int $value, int $decimals = 2): string
    {
        $decimalSeparator = settings('preferences.number_decimal_separator') ?? '.';
        $thousandsSeparator = settings('preferences.number_thousands_separator') ?? ',';

        return number_format($value, $decimals, $decimalSeparator, $thousandsSeparator);
    }

    /**
     * Format a percentage for display.
     */
    public function percentage(float|int $value, int $decimals = 2): string
    {
        return $this->number($value, $decimals).'%';
    }
}
