<?php

namespace App\Support\ConfigSchema\Fields;

use App\Support\ConfigSchema\ContainerField;
use App\Support\ConfigSchema\Field;
use InvalidArgumentException;

/**
 * An ordered, addable/removable list of rows. Each row is a set of nested
 * {@see Field}s (the row schema), validated at wildcard paths such as
 * `tiers.*.multiplier`. Used for multiplier tiers and factor ranges.
 *
 * Per-row conditional visibility is not supported: wildcard rules apply to every
 * row uniformly, so a row field cannot show/hide per row. {@see self::fields()}
 * rejects row fields that declare {@see Field::visibleWhen()} rather than
 * silently ignoring the condition.
 */
class RepeaterField extends ContainerField
{
    private ?int $minItems = null;

    /**
     * Define the row schema. Row fields may not use visibleWhen() — per-row
     * visibility cannot be expressed as uniform wildcard validation rules.
     */
    public function fields(Field ...$fields): static
    {
        foreach ($fields as $field) {
            if ($field->hasVisibleConditions()) {
                throw new InvalidArgumentException(
                    'Repeater row fields cannot use visibleWhen(); per-row conditional visibility is not supported.',
                );
            }
        }

        return parent::fields(...$fields);
    }

    public function minItems(int $minItems): static
    {
        $this->minItems = $minItems;

        return $this;
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

        // Row fields are validated uniformly at the wildcard path. visibleWhen is
        // rejected in fields(), so an empty values array is safe here.
        foreach ($this->fields as $field) {
            $rules += $field->validationRules("{$path}.*", []);
        }

        return $rules;
    }

    /**
     * Defaults to an empty list of rows. Note: a repeater with minItems() set
     * therefore starts below its own minimum — the UI seeds the initial row(s),
     * and the `min:` rule is only enforced on submit.
     *
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
        if (! $this->isVisible($values) || ! array_key_exists($this->key, $values)) {
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
