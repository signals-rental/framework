<?php

namespace App\Support;

use App\Models\Currency;
use Brick\Money\Money;
use DateTimeInterface;

class Formatter
{
    /** @var array<string, array{symbol: string, name: string}> */
    private array $currencyCache = [];

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
     * Format a money value (stored in minor units) for display.
     *
     * Uses brick/money for precision and respects configured currency display
     * preference (symbol, code, or name) and number formatting separators.
     */
    public function money(int $minorUnits, ?string $currencyCode = null): string
    {
        $currencyCode = $currencyCode ?? settings('company.base_currency') ?? 'GBP';
        $money = Money::ofMinor($minorUnits, $currencyCode);

        $decimalSeparator = settings('preferences.number_decimal_separator') ?? '.';
        $thousandsSeparator = settings('preferences.number_thousands_separator') ?? ',';
        $display = settings('preferences.currency_display') ?? 'symbol';

        // Use string-based formatting to avoid float precision loss per CLAUDE.md
        $scale = $money->getCurrency()->getDefaultFractionDigits();
        $amountStr = (string) $money->getAmount()->toScale($scale);

        // Split on decimal point, format integer part with thousands separator
        $parts = explode('.', $amountStr);
        $integerPart = $parts[0];
        $decimalPart = $parts[1] ?? str_repeat('0', $scale);

        $isNegative = str_starts_with($integerPart, '-');
        $integerPart = ltrim($integerPart, '-');

        $formatted = '';
        $len = strlen($integerPart);
        for ($i = 0; $i < $len; $i++) {
            if ($i > 0 && ($len - $i) % 3 === 0) {
                $formatted .= $thousandsSeparator;
            }
            $formatted .= $integerPart[$i];
        }

        $amount = ($isNegative ? '-' : '').$formatted.($scale > 0 ? $decimalSeparator.$decimalPart : '');

        return match ($display) {
            'code' => $currencyCode.' '.$amount,
            'name' => $amount.' '.$this->currencyName($currencyCode),
            default => $this->currencySymbol($currencyCode).$amount,
        };
    }

    /**
     * Format a decimal money string (e.g. "125.50") for display.
     *
     * Useful for API response values that are already in decimal format.
     */
    public function moneyDecimal(string $decimalAmount, ?string $currencyCode = null): string
    {
        $currencyCode = $currencyCode ?? settings('company.base_currency') ?? 'GBP';
        $money = Money::of($decimalAmount, $currencyCode);

        return $this->money($money->getMinorAmount()->toInt(), $currencyCode);
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

    /**
     * Get the symbol for a currency code, cached per request.
     */
    private function currencySymbol(string $code): string
    {
        return $this->loadCurrency($code)['symbol'];
    }

    /**
     * Get the display name for a currency code, cached per request.
     */
    private function currencyName(string $code): string
    {
        return $this->loadCurrency($code)['name'];
    }

    /**
     * Load and cache currency metadata from the database.
     *
     * @return array{symbol: string, name: string}
     */
    private function loadCurrency(string $code): array
    {
        if (isset($this->currencyCache[$code])) {
            return $this->currencyCache[$code];
        }

        $currency = Currency::query()->where('code', $code)->first(['symbol', 'name']);

        $this->currencyCache[$code] = $currency !== null
            ? ['symbol' => $currency->symbol, 'name' => $currency->name]
            : ['symbol' => $code.' ', 'name' => $code];

        return $this->currencyCache[$code];
    }
}
