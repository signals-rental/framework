<?php

namespace App\Models\Traits;

use App\Models\Scopes\StoreScope;

trait HasStoreScoping
{
    public static function bootHasStoreScoping(): void
    {
        static::addGlobalScope(new StoreScope);
    }
}
