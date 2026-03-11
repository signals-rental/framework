<?php

namespace App\Actions\ListValues;

use App\Data\ListValues\CreateListNameData;
use App\Data\ListValues\ListNameData;
use App\Events\AuditableEvent;
use App\Models\ListName;
use Illuminate\Support\Facades\Gate;

class CreateListName
{
    public function __invoke(CreateListNameData $data): ListNameData
    {
        Gate::authorize('list-values.manage');

        $listName = ListName::create($data->toArray());

        event(new AuditableEvent($listName, 'list_name.created'));

        return ListNameData::fromModel($listName);
    }
}
