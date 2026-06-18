<?php

namespace App\Enums;

/**
 * The commercial nature of an opportunity line item.
 *
 * RMS-aligned integers persisted on `opportunity_items.transaction_type`. They
 * drive how a line contributes to the opportunity's per-type charge totals
 * (rental vs sale vs service vs sub-rental).
 *
 * Distinct from the int-backed stock {@see TransactionType} (which describes
 * stock movements) and the string-backed {@see RateTransactionType} (which keys
 * rate definitions); this enum mirrors the Current RMS line-item integers.
 */
enum LineItemTransactionType: int
{
    case Rental = 0;

    case Sale = 1;

    case Service = 2;

    case SubRental = 3;

    public function label(): string
    {
        return match ($this) {
            self::Rental => 'Rental',
            self::Sale => 'Sale',
            self::Service => 'Service',
            self::SubRental => 'Sub-rental',
        };
    }
}
