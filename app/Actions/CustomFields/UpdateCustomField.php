<?php

namespace App\Actions\CustomFields;

use App\Data\CustomFields\CustomFieldData;
use App\Data\CustomFields\UpdateCustomFieldData;
use App\Events\AuditableEvent;
use App\Models\CustomField;
use App\Services\CustomFieldDefinitionResolver;
use App\Services\SchemaRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Optional;

class UpdateCustomField
{
    public function __invoke(CustomField $field, UpdateCustomFieldData $data): CustomFieldData
    {
        Gate::authorize('custom-fields.manage');

        // Enforce the composite ['name', 'module_type'] unique index at the
        // validation layer so a duplicate rename returns a clean 422 instead of
        // an uncaught QueryException (HTTP 500). The DTO does not carry the field
        // id, so the ignore() must happen here. Renaming to the field's own name
        // remains valid because the current record is excluded. Only runs when
        // name is present (Optional => omitted leaves the column untouched).
        if (! $data->name instanceof Optional) {
            Validator::make(['name' => $data->name], [
                'name' => [
                    Rule::unique('custom_fields', 'name')
                        ->where('module_type', $field->module_type)
                        ->ignore($field->id),
                ],
            ])->validate();
        }

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
