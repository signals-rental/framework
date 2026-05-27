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
}
