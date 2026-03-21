<?php

namespace App\Enums;

enum AllowedStockType: int
{
    case Rental = 1;
    case Sale = 2;
    case Both = 3;

    public function label(): string
    {
        return match ($this) {
            self::Rental => 'Rental',
            self::Sale => 'Sale',
            self::Both => 'Both',
        };
    }
}
