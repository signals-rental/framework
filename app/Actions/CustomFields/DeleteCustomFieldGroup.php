<?php

namespace App\Actions\CustomFields;

use App\Events\AuditableEvent;
use App\Models\CustomFieldGroup;
use Illuminate\Support\Facades\Gate;

class DeleteCustomFieldGroup
{
    public function __invoke(CustomFieldGroup $group): void
    {
        Gate::authorize('custom-fields.manage');

        event(new AuditableEvent($group, 'custom_field_group.deleted'));

        $group->delete();
    }
}
