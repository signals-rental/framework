<?php

namespace App\Support\ConfigSchema;

/**
 * Base for fields that contain other fields rather than holding a scalar value
 * of their own (groups, repeaters). A container has no leaf value, so the
 * type-rule and cast hooks are no-ops; subclasses override validationRules(),
 * defaults() and sanitise() to delegate to their child fields.
 */
abstract class ContainerField extends Field
{
    /** @var array<int, Field> */
    protected array $fields = [];

    public function fields(Field ...$fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * @return array<int, Field>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @return array<int, mixed>
     */
    protected function typeRules(): array
    {
        return [];
    }

    protected function castValue(mixed $value): mixed
    {
        return $value;
    }
}
