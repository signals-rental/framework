<?php

namespace App\Data\Members;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class MergeMemberData extends Data
{
    public function __construct(
        #[Required]
        public int $primary_id,
        #[Required]
        public int $secondary_id,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'primary_id' => ['required', 'integer', 'exists:members,id'],
            'secondary_id' => ['required', 'integer', 'exists:members,id', 'different:primary_id'],
        ];
    }
}
