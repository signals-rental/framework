<?php

namespace App\Data\Opportunities;

use App\Enums\AssetAssignmentStatus;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Data;

/**
 * Input DTO for reverting an asset to an earlier dispatch/return status.
 */
class RevertAssetStatusData extends Data
{
    public function __construct(
        public int $revert_to,
        public ?string $reason = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'revert_to' => ['required', new Enum(AssetAssignmentStatus::class)],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
