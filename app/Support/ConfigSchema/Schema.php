<?php

namespace App\Support\ConfigSchema;

/**
 * An ordered collection of {@see Field}s describing one bucket of configuration
 * (a strategy's `strategy_config`, or one modifier's entry in `modifier_configs`).
 *
 * The schema knows how to derive Laravel validation rules for a given value set
 * (visible fields only), produce a default value map, and sanitise raw input
 * (cast visible values, drop hidden ones). The same schema drives both API and
 * UI validation, guaranteeing identical enforcement.
 */
class Schema
{
    /** @var array<int, Field> */
    private array $fields;

    public function __construct(Field ...$fields)
    {
        $this->fields = $fields;
    }

    public static function make(Field ...$fields): self
    {
        return new self(...$fields);
    }

    /**
     * @return array<int, Field>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    public function isEmpty(): bool
    {
        return $this->fields === [];
    }

    /**
     * Laravel validation rules for the given values, keyed by dot-path. Hidden
     * fields are excluded.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, array<int, mixed>>
     */
    public function validationRules(array $values): array
    {
        $rules = [];

        foreach ($this->fields as $field) {
            $rules += $field->validationRules('', $values);
        }

        return $rules;
    }

    /**
     * The schema's default value map.
     *
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
     * Cast visible values and drop config belonging to hidden fields.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function sanitise(array $values): array
    {
        $sanitised = [];

        foreach ($this->fields as $field) {
            $sanitised += $field->sanitise($values);
        }

        return $sanitised;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(static fn (Field $field): array => $field->toArray(), $this->fields);
    }
}
