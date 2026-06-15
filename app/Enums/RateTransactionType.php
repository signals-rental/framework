<?php

namespace App\Enums;

/**
 * Transaction context a product rate applies to.
 *
 * Distinct from the int-backed stock `TransactionType`; this string-backed enum
 * is dedicated to rate definitions and product rate assignments.
 */
enum RateTransactionType: string
{
    case Rental = 'rental';
    case Sale = 'sale';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Rental => 'Rental',
            self::Sale => 'Sale',
            self::Service => 'Service',
        };
    }

    /**
     * Resolve a value to its canonical backing value, matching case-insensitively
     * against both backing values and case names. Returns the original value
     * untouched when no match is found, leaving validation to reject it.
     *
     * Mirrors the case-insensitive enum coercion applied to Ransack filters so
     * that writes (`transaction_type=Rental`) accept the same casings as reads.
     */
    public static function coerce(mixed $value): mixed
    {
        if (! is_string($value) && ! is_int($value)) {
            return $value;
        }

        $needle = mb_strtolower((string) $value);

        foreach (self::cases() as $case) {
            if (mb_strtolower($case->value) === $needle || mb_strtolower($case->name) === $needle) {
                return $case->value;
            }
        }

        return $value;
    }
}
