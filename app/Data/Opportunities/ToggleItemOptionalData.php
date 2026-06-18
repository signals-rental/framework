<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for toggling whether a line item is optional.
 */
class ToggleItemOptionalData extends Data
{
    public function __construct(
        public bool $is_optional,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'is_optional' => ['required', 'boolean'],
        ];
    }
}
