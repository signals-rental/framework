<?php

namespace App\Data\Members;

use Illuminate\Validation\Rule;
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
            'primary_id' => ['required', 'integer', Rule::exists('members', 'id')->withoutTrashed()],
            'secondary_id' => ['required', 'integer', Rule::exists('members', 'id')->withoutTrashed(), 'different:primary_id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'secondary_id.different' => 'A member cannot be merged into itself.',
        ];
    }
}
