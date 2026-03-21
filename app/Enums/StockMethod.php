<?php

namespace App\Enums;

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
