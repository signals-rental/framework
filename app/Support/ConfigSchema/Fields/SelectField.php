<?php

namespace App\Support\ConfigSchema\Fields;

use App\Support\ConfigSchema\Field;
use Illuminate\Validation\Rule;

/**
 * A dropdown constrained to a fixed set of key => label options.
 */
class SelectField extends Field
{
    /** @var array<string, string> */
    private array $options = [];

    /**
     * @param  array<string, string>  $options
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function type(): string
    {
        return 'select';
    }

    /**
     * @return array<int, mixed>
     */
    protected function typeRules(): array
    {
        return [Rule::in(array_keys($this->options))];
    }

    protected function castValue(mixed $value): string
    {
        return (string) (is_scalar($value) ? $value : '');
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraMeta(): array
    {
        return ['options' => $this->options];
    }
}
