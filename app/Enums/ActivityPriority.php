<?php

namespace App\Enums;

enum ActivityPriority: int
{
    case Low = 0;
    case Normal = 1;
    case High = 2;

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Normal => 'Normal',
            self::High => 'High',
        };
    }
}
