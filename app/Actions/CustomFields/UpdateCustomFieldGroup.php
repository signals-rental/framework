<?php

namespace App\Actions\CustomFields;

use App\Data\CustomFields\CustomFieldGroupData;
use App\Data\CustomFields\UpdateCustomFieldGroupData;
use App\Events\AuditableEvent;
use App\Models\CustomFieldGroup;
use Illuminate\Support\Facades\Gate;

class UpdateCustomFieldGroup
{
    public function __invoke(CustomFieldGroup $group, UpdateCustomFieldGroupData $data): CustomFieldGroupData
    {
        Gate::authorize('custom-fields.manage');

        $group->update(array_filter($data->toArray(), fn ($v) => $v !== null));

        event(new AuditableEvent($group, 'custom_field_group.updated'));

        return CustomFieldGroupData::fromModel($group->fresh());
    }
}
