<?php

namespace App\Actions\ListValues;

use App\Events\AuditableEvent;
use App\Models\ListName;
use Illuminate\Support\Facades\Gate;

class DeleteListName
{
    public function __invoke(ListName $listName): void
    {
        Gate::authorize('list-values.manage');

        event(new AuditableEvent($listName, 'list_name.deleted'));

        $listName->delete();
    }
}
