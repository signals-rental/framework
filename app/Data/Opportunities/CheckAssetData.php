<?php

namespace App\Data\Opportunities;

use App\Enums\AssetCondition;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Data;

/**
 * Input DTO for the condition assessment of a returned asset (the RMS
 * `finalise_check_in` action).
 */
class CheckAssetData extends Data
{
    public function __construct(
        public int $condition = 0,
        public ?int $checked_by = null,
        public ?string $damage_notes = null,
        public ?string $checked_at = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'condition' => ['required', new Enum(AssetCondition::class)],
            'checked_by' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'damage_notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'checked_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
