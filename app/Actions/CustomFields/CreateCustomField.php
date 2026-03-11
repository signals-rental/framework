<?php

namespace App\Actions\CustomFields;

use App\Data\CustomFields\CreateCustomFieldData;
use App\Data\CustomFields\CustomFieldData;
use App\Events\AuditableEvent;
use App\Models\CustomField;
use Illuminate\Support\Facades\Gate;

class CreateCustomField
{
    public function __invoke(CreateCustomFieldData $data): CustomFieldData
    {
        Gate::authorize('custom-fields.manage');

        $field = CustomField::create($data->toArray());

        event(new AuditableEvent($field, 'custom_field.created'));

        return CustomFieldData::fromModel($field);
    }
}
