<?php

namespace App\Actions\ListValues;

use App\Data\ListValues\ListNameData;
use App\Data\ListValues\UpdateListNameData;
use App\Events\AuditableEvent;
use App\Models\ListName;
use Illuminate\Support\Facades\Gate;

class UpdateListName
{
    public function __invoke(ListName $listName, UpdateListNameData $data): ListNameData
    {
        Gate::authorize('list-values.manage');

        $listName->update(array_filter($data->toArray(), fn ($v) => $v !== null));

        event(new AuditableEvent($listName, 'list_name.updated'));

        return ListNameData::fromModel($listName->fresh());
    }
}
