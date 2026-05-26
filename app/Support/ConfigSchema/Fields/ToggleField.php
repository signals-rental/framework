<?php

namespace App\Support\ConfigSchema\Fields;

use App\Support\ConfigSchema\Field;

/**
 * A boolean on/off switch.
 */
class ToggleField extends Field
{
    public function type(): string
    {
        return 'toggle';
    }

    /**
     * @return array<int, mixed>
     */
    protected function typeRules(): array
    {
        return ['boolean'];
    }

    protected function castValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
