<?php

namespace App\Support\ConfigSchema\Fields;

use App\Support\ConfigSchema\Field;

/**
 * An ordered, addable/removable list of rows. Each row is a set of nested
 * {@see Field}s (the row schema), validated at wildcard paths such as
 * `tiers.*.multiplier`. Used for multiplier tiers and factor ranges.
 *
 * Per-row conditional visibility is not supported in v1 (the rate engine's row
 * fields are unconditional); row field rules are generated unconditionally.
 */
class RepeaterField extends Field
{
    /** @var array<int, Field> */
    private array $fields = [];

    private ?int $minItems = null;

    public function fields(Field ...$fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    public function minItems(int $minItems): static
    {
        $this->minItems = $minItems;

        return $this;
    }

    /**
     * @return array<int, Field>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function type(): string
    {
        return 'repeater';
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

        $path = $this->path($prefix);

        $arrayRules = [$this->required ? 'required' : 'nullable', 'array'];

        if ($this->minItems !== null) {
            $arrayRules[] = "min:{$this->minItems}";
        }

        $rules = [$path => $arrayRules];

        foreach ($this->fields as $field) {
            $rules += $field->validationRules("{$path}.*", []);
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [$this->key => []];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function sanitise(array $values): array
    {
        if (! array_key_exists($this->key, $values)) {
            return [];
        }

        $raw = $values[$this->key];

        if (! is_array($raw)) {
            return [$this->key => []];
        }

        $rows = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $sanitisedRow = [];

            foreach ($this->fields as $field) {
                $sanitisedRow += $field->sanitise($row);
            }

            $rows[] = $sanitisedRow;
        }

        return [$this->key => $rows];
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

    /**
     * @return array<string, mixed>
     */
    protected function extraMeta(): array
    {
        return [
            'min_items' => $this->minItems,
            'fields' => array_map(static fn (Field $field): array => $field->toArray(), $this->fields),
        ];
    }
}
