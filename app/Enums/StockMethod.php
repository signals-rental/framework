<?php

namespace App\Enums;

/**
 * Stock-level tracking method.
 *
 * Can override the parent product's StockCategory on a per-location basis.
 */
enum StockMethod: int
{
    case Bulk = 1;
    case Serialised = 2;

    public function label(): string
    {
        return match ($this) {
            self::Bulk => 'Bulk',
            self::Serialised => 'Serialised',
        };
    }
}
