<?php

namespace App\Enums;

enum ActivityStatus: int
{
    case Scheduled = 2001;
    case Completed = 2002;
    case Cancelled = 2003;
    case Held = 2004;

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Held => 'Held',
        };
    }
}
