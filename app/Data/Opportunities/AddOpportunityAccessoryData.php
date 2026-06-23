<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for adding an accessory line beneath an existing product (principal)
 * line in an opportunity's unified line-item tree.
 *
 * An accessory is product-backed: it carries a polymorphic catalogue reference,
 * is priced by the rate engine, and syncs availability demand. It inherits the
 * principal's quote-version scope and nests directly beneath it — the
 * {@see App\Actions\Opportunities\AddOpportunityAccessory} action enforces that
 * the principal is a product row and allocates the child path via
 * {@see App\Services\Opportunities\ItemTreeService}.
 */
class AddOpportunityAccessoryData extends Data
{
    public function __construct(
        public string $name,
        /** The OpportunityItem id of the product line this accessory hangs under. */
        public int $principal_item_id,
        /** Polymorphic catalogue reference — the itemable's integer PK. */
        public ?int $itemable_id = null,
        /** Polymorphic catalogue reference — the itemable's fully-qualified class. */
        public ?string $itemable_type = null,
        public string $quantity = '1',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'principal_item_id' => ['required', 'integer'],
            'itemable_id' => ['sometimes', 'nullable', 'integer'],
            'itemable_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
