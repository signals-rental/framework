<?php

namespace App\Services\Calendar;

class OwnerColorResolver
{
    /**
     * Signals avatar colour tokens, in a fixed order. The mapping is positional,
     * so this list must never be reordered without invalidating existing colours.
     *
     * @var list<string>
     */
    private const PALETTE = [
        's-avatar-green',
        's-avatar-blue',
        's-avatar-amber',
        's-avatar-violet',
        's-avatar-cyan',
        's-avatar-navy',
    ];

    /**
     * Resolve a user id to a deterministic, stable avatar colour token.
     */
    public function for(int $userId): string
    {
        return self::PALETTE[$userId % count(self::PALETTE)];
    }
}
