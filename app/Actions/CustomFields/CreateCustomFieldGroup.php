<?php

namespace App\Actions\CustomFields;

use App\Data\CustomFields\CreateCustomFieldGroupData;
use App\Data\CustomFields\CustomFieldGroupData;
use App\Events\AuditableEvent;
use App\Models\CustomFieldGroup;
use Illuminate\Support\Facades\Gate;

class CreateCustomFieldGroup
{
    public function __invoke(CreateCustomFieldGroupData $data): CustomFieldGroupData
    {
        Gate::authorize('custom-fields.manage');

        $group = CustomFieldGroup::create($data->toArray());

        event(new AuditableEvent($group, 'custom_field_group.created'));

        return CustomFieldGroupData::fromModel($group);
    }
}
