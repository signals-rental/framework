<?php

namespace App\Enums;

enum TimeStatus: int
{
    case Free = 0;
    case Busy = 1;

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Busy => 'Busy',
        };
    }
}
