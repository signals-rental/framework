<?php

namespace App\Support\ConfigSchema\Fields;

use App\Support\ConfigSchema\ContainerField;
use App\Support\ConfigSchema\Field;

/**
 * A visual/visibility container. Its children write to flat top-level keys (the
 * group does not nest the value tree); the group exists to label related fields
 * and to gate them collectively via {@see Field::visibleWhen()}. When the group
 * is hidden, none of its children are validated or persisted.
 */
class GroupField extends ContainerField
{
    public function type(): string
    {
        return 'group';
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, array<int, mixed>>
     */
    public function validationRules(string $prefix, array $values): array
    {
        if (! $this->isVisible($values)) {
            return [];
        }

        $rules = [];

        foreach ($this->fields as $field) {
            $rules += $field->validationRules($prefix, $values);
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $defaults = [];

        foreach ($this->fields as $field) {
            $defaults += $field->defaults();
        }

        return $defaults;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function sanitise(array $values): array
    {
        if (! $this->isVisible($values)) {
            return [];
        }

        $sanitised = [];

        foreach ($this->fields as $field) {
            $sanitised += $field->sanitise($values);
        }

        return $sanitised;
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraMeta(): array
    {
        return [
            'fields' => array_map(static fn (Field $field): array => $field->toArray(), $this->fields),
        ];
    }
}
