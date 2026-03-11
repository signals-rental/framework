<?php

namespace App\Actions\ListValues;

use App\Data\ListValues\CreateListValueData;
use App\Data\ListValues\ListValueData;
use App\Events\AuditableEvent;
use App\Models\ListValue;
use Illuminate\Support\Facades\Gate;

class CreateListValue
{
    public function __invoke(CreateListValueData $data): ListValueData
    {
        Gate::authorize('list-values.manage');

        $value = ListValue::create($data->toArray());

        event(new AuditableEvent($value, 'list_value.created'));

        return ListValueData::fromModel($value);
    }
}
