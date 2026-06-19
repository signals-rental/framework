<?php

namespace App\Data\Products;

use App\Enums\KitComponentBinding;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Data;

/**
 * Input DTO for updating a kit composition line. All fields are optional; only
 * those supplied are changed. The kit parent and component product cannot be
 * re-pointed here — remove and re-add to change the composition shape.
 */
class UpdateSerialisedComponentData extends Data
{
    public function __construct(
        public ?string $quantity = null,
        public ?KitComponentBinding $binding = null,
        public ?int $sort_order = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'quantity' => ['sometimes', 'numeric', 'min:1'],
            'binding' => ['sometimes', new Enum(KitComponentBinding::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
