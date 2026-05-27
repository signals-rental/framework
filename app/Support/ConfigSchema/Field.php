<?php

namespace App\Support\ConfigSchema;

use Illuminate\Support\Str;

/**
 * A single configurable field in a {@see Schema}. Fields are declarative: they
 * carry display metadata, a default, optional conditional visibility, and they
 * know how to produce Laravel validation rules and cast a raw value to its
 * typed form. Concrete field types (text, number, decimal, toggle, select,
 * time, group, repeater) extend this base.
 *
 * Visibility conditions are ANDed: a field is visible only when every condition
 * matches the sibling values. Hidden fields are excluded from validation and
 * dropped on sanitisation, so inactive options never persist stale config.
 *
 * @phpstan-consistent-constructor
 */
abstract class Field
{
    protected string $label;

    protected ?string $help = null;

    protected ?string $placeholder = null;

    protected mixed $default = null;

    protected bool $required = false;

    /** @var array<int, mixed> */
    protected array $rules = [];

    /** @var array<int, array{field: string, value: mixed}> */
    protected array $visibleConditions = [];

    public function __construct(public readonly string $key)
    {
        $this->label = Str::headline($key);
    }

    public static function make(string $key): static
    {
        return new static($key);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function help(string $help): static
    {
        $this->help = $help;

        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    /**
     * Append extra Laravel validation rules, applied after the field's own type
     * rules.
     *
     * @param  array<int, mixed>  $rules
     */
    public function rules(array $rules): static
    {
        $this->rules = [...$this->rules, ...$rules];

        return $this;
    }

    /**
     * Only show (and validate) this field when the sibling $field equals $value.
     * Multiple calls AND together.
     */
    public function visibleWhen(string $field, mixed $value): static
    {
        $this->visibleConditions[] = ['field' => $field, 'value' => $value];

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Whether this field carries any conditional-visibility conditions.
     */
    public function hasVisibleConditions(): bool
    {
        return $this->visibleConditions !== [];
    }

    /**
     * Whether this field is visible given the current sibling values.
     *
     * @param  array<string, mixed>  $values
     */
    public function isVisible(array $values): bool
    {
        foreach ($this->visibleConditions as $condition) {
            if (($values[$condition['field']] ?? null) != $condition['value']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Laravel validation rules keyed by full dot-path, or an empty array when the
     * field is hidden. $prefix is the path to this field's parent ('' at the top
     * level, e.g. 'ranges.*' inside a repeater).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, array<int, mixed>>
     */
    public function validationRules(string $prefix, array $values): array
    {
        if (! $this->isVisible($values)) {
            return [];
        }

        $rules = [$this->required ? 'required' : 'nullable', ...$this->typeRules(), ...$this->rules];

        return [$this->path($prefix) => $rules];
    }

    /**
     * Cast a raw value to its typed form, leaving null untouched.
     */
    public function cast(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return $this->castValue($value);
    }

    /**
     * This field's contribution to a schema's default value map.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return $this->default === null ? [] : [$this->key => $this->default];
    }

    /**
     * This field's sanitised contribution given the current values: cast when
     * visible and present, dropped entirely when hidden.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function sanitise(array $values): array
    {
        if (! $this->isVisible($values) || ! array_key_exists($this->key, $values)) {
            return [];
        }

        return [$this->key => $this->cast($values[$this->key])];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $meta = [
            'key' => $this->key,
            'type' => $this->type(),
            'label' => $this->label,
            'help' => $this->help,
            'placeholder' => $this->placeholder,
            'default' => $this->default,
            'required' => $this->required,
            ...$this->extraMeta(),
        ];

        if ($this->visibleConditions !== []) {
            $meta['visible_when'] = $this->visibleConditions;
        }

        return $meta;
    }

    /**
     * The field type identifier (e.g. `text`, `number`, `select`).
     */
    abstract public function type(): string;

    /**
     * Type-specific Laravel rules, appended after the required/nullable rule.
     *
     * @return array<int, mixed>
     */
    abstract protected function typeRules(): array;

    /**
     * Coerce a non-null raw value to its typed form.
     */
    abstract protected function castValue(mixed $value): mixed;

    /**
     * Type-specific metadata merged into {@see self::toArray()}.
     *
     * @return array<string, mixed>
     */
    protected function extraMeta(): array
    {
        return [];
    }

    protected function path(string $prefix): string
    {
        return $prefix === '' ? $this->key : "{$prefix}.{$this->key}";
    }
}
