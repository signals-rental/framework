<?php

namespace App\Actions\ListValues;

use App\Data\ListValues\ListValueData;
use App\Data\ListValues\UpdateListValueData;
use App\Events\AuditableEvent;
use App\Models\ListValue;
use Illuminate\Support\Facades\Gate;

class UpdateListValue
{
    public function __invoke(ListValue $value, UpdateListValueData $data): ListValueData
    {
        Gate::authorize('list-values.manage');

        $value->update(array_filter($data->toArray(), fn ($v) => $v !== null));

        event(new AuditableEvent($value, 'list_value.updated'));

        return ListValueData::fromModel($value->fresh());
    }
}
