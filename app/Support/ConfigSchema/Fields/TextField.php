<?php

namespace App\Support\ConfigSchema\Fields;

use App\Support\ConfigSchema\Field;

/**
 * A free-text string field with optional min/max length.
 */
class TextField extends Field
{
    private ?int $minLength = null;

    private ?int $maxLength = null;

    public function minLength(int $length): static
    {
        $this->minLength = $length;

        return $this;
    }

    public function maxLength(int $length): static
    {
        $this->maxLength = $length;

        return $this;
    }

    public function type(): string
    {
        return 'text';
    }

    /**
     * @return array<int, mixed>
     */
    protected function typeRules(): array
    {
        $rules = ['string'];

        if ($this->minLength !== null) {
            $rules[] = "min:{$this->minLength}";
        }

        if ($this->maxLength !== null) {
            $rules[] = "max:{$this->maxLength}";
        }

        return $rules;
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
        return [
            'min_length' => $this->minLength,
            'max_length' => $this->maxLength,
        ];
    }
}
