<?php

namespace App\Enums;

enum ProductType: string
{
    case Rental = 'rental';
    case Sale = 'sale';
    case Service = 'service';
    case LossAndDamage = 'loss_and_damage';

    public function label(): string
    {
        return match ($this) {
            self::Rental => 'Rental',
            self::Sale => 'Sale',
            self::Service => 'Service',
            self::LossAndDamage => 'Loss & Damage',
        };
    }
}
