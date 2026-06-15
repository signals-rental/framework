<?php

namespace App\Actions\CustomFields;

use App\Data\CustomFields\CustomFieldData;
use App\Data\CustomFields\UpdateCustomFieldData;
use App\Events\AuditableEvent;
use App\Models\CustomField;
use App\Services\CustomFieldDefinitionResolver;
use App\Services\SchemaRegistry;
use Illuminate\Support\Facades\Gate;

class UpdateCustomField
{
    public function __invoke(CustomField $field, UpdateCustomFieldData $data): CustomFieldData
    {
        Gate::authorize('custom-fields.manage');

        // toArray() omits Optional (untouched) fields but keeps explicitly
        // provided values — including nulls — so a caller can clear a nullable
        // column (e.g. validation_rules/visibility_rules) by sending it as null,
        // while omitted keys leave their column unchanged (partial update).
        $field->update($data->toArray());

        app(CustomFieldDefinitionResolver::class)->clearCache($field->module_type);
        app(SchemaRegistry::class)->invalidateAll();

        event(new AuditableEvent($field, 'custom_field.updated'));

        return CustomFieldData::fromModel($field->fresh());
    }
}
