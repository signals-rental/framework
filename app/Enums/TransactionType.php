<?php

namespace App\Enums;

enum TransactionType: int
{
    case Opening = 1;
    case Increase = 2;
    case Decrease = 3;
    case Buy = 4;
    case Find = 5;
    case WriteOff = 6;
    case Sell = 7;
    case Return = 8;
    case Make = 9;
    case TransferOut = 10;
    case TransferIn = 11;

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening Balance',
            self::Increase => 'Increase',
            self::Decrease => 'Decrease',
            self::Buy => 'Buy',
            self::Find => 'Find',
            self::WriteOff => 'Write Off',
            self::Sell => 'Sell',
            self::Return => 'Return',
            self::Make => 'Make',
            self::TransferOut => 'Transfer Out',
            self::TransferIn => 'Transfer In',
        };
    }

    /**
     * Types allowed for manual creation via API.
     *
     * @return list<int>
     */
    public static function manualCreationValues(): array
    {
        return [
            self::Buy->value,
            self::Find->value,
            self::WriteOff->value,
            self::Sell->value,
            self::Make->value,
        ];
    }

    /**
     * Signed quantity: negative for reductions, positive for additions.
     */
    public function quantitySign(): int
    {
        return match ($this) {
            self::Decrease, self::WriteOff, self::Sell, self::TransferOut => -1,
            default => 1,
        };
    }
}
