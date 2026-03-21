<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;

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
                continue;
            }
            $column = $cfv->customField->field_type->valueColumn();
            $values[$cfv->customField->name] = $cfv->{$column};
        }

        return $values;
    }
}
