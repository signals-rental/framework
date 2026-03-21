<?php

namespace App\Enums;

enum StockCategory: int
{
    case BulkStock = 10;
    case SerialisedStock = 50;

    public function label(): string
    {
        return match ($this) {
            self::BulkStock => 'Bulk Stock',
            self::SerialisedStock => 'Serialised Stock',
        };
    }
}
