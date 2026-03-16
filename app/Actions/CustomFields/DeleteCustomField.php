<?php

namespace App\Actions\CustomFields;

use App\Events\AuditableEvent;
use App\Models\CustomField;
use App\Services\CustomFieldDefinitionResolver;
use Illuminate\Support\Facades\Gate;

class DeleteCustomField
{
    public function __invoke(CustomField $field): void
    {
        Gate::authorize('custom-fields.manage');

        event(new AuditableEvent($field, 'custom_field.deleted'));

        $field->delete();

        app(CustomFieldDefinitionResolver::class)->clearCache($field->module_type);
    }
}
