<?php

namespace App\Support\ConfigSchema\Fields;

use App\Support\ConfigSchema\Field;

/**
 * A decimal field. Values are kept as strings to preserve precision for any
 * downstream decimal arithmetic, rather than being coerced to a float.
 */
class DecimalField extends Field
{
    private int $decimals = 2;

    public function decimals(int $decimals): static
    {
        $this->decimals = $decimals;

        return $this;
    }

    public function type(): string
    {
        return 'decimal';
    }

    /**
     * @return array<int, mixed>
     */
    protected function typeRules(): array
    {
        return ['numeric'];
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
        return ['decimals' => $this->decimals];
    }
}
