<?php

namespace App\Models\Scopes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Context;

class StoreScope implements Scope
{
    private const CONTEXT_KEY = 'store-scope-disabled';

    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (Context::get(self::CONTEXT_KEY, false)) {
            return;
        }

        $user = auth()->user();

        if ($user === null) {
            return;
        }

        /** @var \App\Models\User $user */
        $storeIds = $user->accessibleStoreIds();

        // null means unrestricted access (owner/admin)
        if ($storeIds === null) {
            return;
        }

        $column = $this->resolveStoreColumn($model);

        $builder->whereIn($model->qualifyColumn($column), $storeIds);
    }

    /**
     * Execute a callback with store scoping temporarily disabled.
     */
    public static function withoutScoping(Closure $callback): mixed
    {
        $previous = Context::get(self::CONTEXT_KEY, false);
        Context::add(self::CONTEXT_KEY, true);

        try {
            return $callback();
        } finally {
            if ($previous) {
                Context::add(self::CONTEXT_KEY, true);
            } else {
                Context::forget(self::CONTEXT_KEY);
            }
        }
    }

    /**
     * Resolve the store column from the model's storeScopePath property or default.
     */
    private function resolveStoreColumn(Model $model): string
    {
        if (property_exists($model, 'storeScopePath')) {
            return $model->storeScopePath;
        }

        return 'store_id';
    }
}
