<?php

namespace App\Actions\CustomFields;

use App\Data\CustomFields\CreateCustomFieldData;
use App\Data\CustomFields\CustomFieldData;
use App\Events\AuditableEvent;
use App\Models\CustomField;
use App\Services\CustomFieldDefinitionResolver;
use App\Services\SchemaRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateCustomField
{
    public function __invoke(CreateCustomFieldData $data): CustomFieldData
    {
        Gate::authorize('custom-fields.manage');

        // Enforce the composite ['name', 'module_type'] unique index at the
        // validation layer so a duplicate create returns a clean 422 instead of an
        // uncaught QueryException (HTTP 500) — mirroring UpdateCustomField, but with
        // no ignore() since no record exists yet. The CreateCustomFieldData rules
        // do not carry this constraint because it is scoped to module_type.
        Validator::make(['name' => $data->name], [
            'name' => [
                Rule::unique('custom_fields', 'name')
                    ->where('module_type', $data->module_type),
            ],
        ])->validate();

        $field = CustomField::create($data->toArray());

        app(CustomFieldDefinitionResolver::class)->clearCache($field->module_type);
        app(SchemaRegistry::class)->invalidateAll();

        event(new AuditableEvent($field, 'custom_field.created'));

        return CustomFieldData::fromModel($field);
    }
}
