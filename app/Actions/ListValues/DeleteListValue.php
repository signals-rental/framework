<?php

namespace App\Actions\ListValues;

use App\Events\AuditableEvent;
use App\Models\ListValue;
use Illuminate\Support\Facades\Gate;

class DeleteListValue
{
    public function __invoke(ListValue $value): void
    {
        Gate::authorize('list-values.manage');

        event(new AuditableEvent($value, 'list_value.deleted'));

        $value->delete();
    }
}
