<?php

namespace App\Actions\CustomFields;

use App\Data\CustomFields\CustomFieldData;
use App\Data\CustomFields\UpdateCustomFieldData;
use App\Events\AuditableEvent;
use App\Models\CustomField;
use Illuminate\Support\Facades\Gate;

class UpdateCustomField
{
    public function __invoke(CustomField $field, UpdateCustomFieldData $data): CustomFieldData
    {
        Gate::authorize('custom-fields.manage');

        $field->update(array_filter($data->toArray(), fn ($v) => $v !== null));

        event(new AuditableEvent($field, 'custom_field.updated'));

        return CustomFieldData::fromModel($field->fresh());
    }
}
