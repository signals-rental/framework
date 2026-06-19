<?php

namespace App\Data\Opportunities;

use App\Enums\AssetCondition;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Data;

/**
 * Input DTO for a (partial) bulk-line return.
 */
class BulkReturnData extends Data
{
    public function __construct(
        public string $quantity,
        public ?int $received_by = null,
        public ?int $condition = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'gt:0'],
            'received_by' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'condition' => ['sometimes', 'nullable', new Enum(AssetCondition::class)],
        ];
    }
}
