<?php

namespace App\Data\Containers;

use App\Enums\ContainerItemUnpackReason;
use Spatie\LaravelData\Data;

/**
 * Input DTO for unpacking a serialised item from an open container.
 */
class UnpackContainerItemData extends Data
{
    public function __construct(
        public int $serialised_item_id,
        public ContainerItemUnpackReason $reason = ContainerItemUnpackReason::Manual,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'serialised_item_id' => ['required', 'integer', 'exists:stock_levels,id'],
        ];
    }
}
