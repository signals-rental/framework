<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/** @phpstan-ignore trait.unused (used by Volt components in Blade files) */
trait LoadsCustomFieldValues
{
    /**
     * Load custom field values from a model into a keyed array.
     *
     * @return array<string, mixed>
     */
    protected function loadCustomFieldValuesFrom(Model $model): array
    {
        $values = [];
        $model->load('customFieldValues.customField');
        foreach ($model->customFieldValues as $cfv) {
            if ($cfv->customField === null) {
                Log::warning('Orphaned custom field value: definition not found', [
                    'custom_field_value_id' => $cfv->id,
                    'custom_field_id' => $cfv->custom_field_id,
                ]);

                continue;
            }
            $column = $cfv->customField->field_type->valueColumn();
            $values[$cfv->customField->name] = $cfv->{$column};
        }

        return $values;
    }
}
