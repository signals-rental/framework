<?php

namespace App\Support\ConfigSchema\Fields;

use App\Support\ConfigSchema\Field;

/**
 * A time-of-day field in 24-hour HH:MM format (cut-off times, business hours).
 */
class TimeField extends Field
{
    public function type(): string
    {
        return 'time';
    }

    /**
     * @return array<int, mixed>
     */
    protected function typeRules(): array
    {
        return ['date_format:H:i'];
    }

    protected function castValue(mixed $value): string
    {
        return (string) (is_scalar($value) ? $value : '');
    }
}
