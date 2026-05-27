<?php

namespace App\Support\ConfigSchema\Fields;

use App\Support\ConfigSchema\Field;

/**
 * An integer field with optional min, max, and UI step.
 */
class NumberField extends Field
{
    private ?int $min = null;

    private ?int $max = null;

    private ?int $step = null;

    public function min(int $min): static
    {
        $this->min = $min;

        return $this;
    }

    public function max(int $max): static
    {
        $this->max = $max;

        return $this;
    }

    public function step(int $step): static
    {
        $this->step = $step;

        return $this;
    }

    public function type(): string
    {
        return 'number';
    }

    /**
     * @return array<int, mixed>
     */
    protected function typeRules(): array
    {
        $rules = ['integer'];

        if ($this->min !== null) {
            $rules[] = "min:{$this->min}";
        }

        if ($this->max !== null) {
            $rules[] = "max:{$this->max}";
        }

        return $rules;
    }

    protected function castValue(mixed $value): int
    {
        return (int) (is_scalar($value) ? $value : 0);
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraMeta(): array
    {
        return [
            'min' => $this->min,
            'max' => $this->max,
            'step' => $this->step,
        ];
    }
}
