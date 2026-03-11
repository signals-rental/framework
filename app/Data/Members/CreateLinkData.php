<?php

namespace App\Data\Members;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CreateLinkData extends Data
{
    public function __construct(
        #[Required]
        public string $url,
        public ?string $name = null,
        public ?int $type_id = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'url' => ['required', 'string', 'url', 'max:2048'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'type_id' => ['sometimes', 'nullable', 'integer', 'exists:list_values,id'],
        ];
    }
}
