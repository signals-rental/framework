<?php

namespace App\Data\Members;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CreatePhoneData extends Data
{
    public function __construct(
        #[Required]
        public string $number,
        public ?int $type_id = null,
        public bool $is_primary = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:50'],
            'type_id' => ['sometimes', 'nullable', 'integer', 'exists:list_values,id'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
