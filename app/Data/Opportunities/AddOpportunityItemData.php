<?php

namespace App\Data\Opportunities;

use App\Data\Casts\MoneyInput;
use App\Enums\ChargePeriod;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityItemType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

/**
 * Input DTO for adding a line item to an opportunity.
 *
 * `unit_price` is an OPTIONAL manual override (minor units; accepts an int =
 * already-minor or a decimal string/float = major units against `currency`). When
 * omitted the rate engine prices the line. `currency` is the sibling scale for the
 * {@see MoneyInput} cast.
 */
class AddOpportunityItemData extends Data
{
    public function __construct(
        public string $name,
        /** Polymorphic catalogue reference — the itemable's integer PK. */
        public ?int $itemable_id = null,
        /** Polymorphic catalogue reference — the itemable's fully-qualified class. */
        public ?string $itemable_type = null,
        /**
         * Structural role of the row in the unified line-item tree
         * ({@see OpportunityItemType}: group/product/accessory/service). Distinct
         * from the polymorphic catalogue reference above.
         */
        public string $item_type = 'product',
        /**
         * The parent group/product path under which to nest the new line (e.g.
         * '0001'). Null adds the line at the top level. The
         * {@see AddOpportunityItem} action allocates the concrete child/top-level
         * path within this scope via {@see App\Services\Opportunities\ItemTreeService}.
         */
        public ?string $parent_path = null,
        /** Optional revenue-group classification for reporting. */
        public ?int $revenue_group_id = null,
        public ?string $description = null,
        public string $quantity = '1',
        public int $transaction_type = LineItemTransactionType::Rental->value,
        public int $charge_period = ChargePeriod::Day->value,
        public ?string $starts_at = null,
        public ?string $ends_at = null,
        public bool $is_optional = false,
        public ?string $discount_percent = null,
        public ?string $notes = null,
        /** @var array<string, mixed>|null */
        public ?array $custom_fields = null,
        public string $currency = 'GBP',
        #[WithCast(MoneyInput::class)]
        public ?int $unit_price = null,
        /**
         * Internal: the quote version scope the new line belongs to. Not a public
         * input field — it is set by the version-clone path ({@see VersionCreated}).
         * When null the action resolves it from the opportunity's active version.
         */
        public ?int $version_id = null,
        /**
         * When true (default), adding a product auto-materialises catalogue
         * `included` accessories as nested accessory rows. Clone/version paths set
         * this false because they replay every row explicitly.
         */
        public bool $materialize_included_accessories = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'itemable_id' => ['sometimes', 'nullable', 'integer'],
            'itemable_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'item_type' => ['sometimes', 'string', Rule::enum(OpportunityItemType::class)],
            'parent_path' => ['sometimes', 'nullable', 'string'],
            'revenue_group_id' => ['sometimes', 'nullable', 'integer'],
            'description' => ['sometimes', 'nullable', 'string'],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            // Sub-rental is a Phase 4 deliverable with no creation path yet, so it
            // is rejected at the input boundary (its totals bucket is stubbed 0).
            // A closure carries the Phase-4 message inline so it surfaces whether
            // the rules run via the controller's $request->validate() or the DTO's
            // own validateAndCreate() — neither consults the messages() method for
            // this attribute here.
            'transaction_type' => [
                'sometimes',
                'integer',
                Rule::enum(LineItemTransactionType::class),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ((int) $value === LineItemTransactionType::SubRental->value) {
                        $fail('Sub-rental line items are not available until Phase 4.');
                    }
                },
            ],
            'charge_period' => ['sometimes', 'integer', new Enum(ChargePeriod::class)],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'is_optional' => ['sometimes', 'boolean'],
            'discount_percent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'custom_fields' => ['sometimes', 'nullable', 'array'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'unit_price' => ['sometimes', 'nullable', 'numeric'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'transaction_type.enum' => 'The selected transaction type is invalid.',
            'charge_period.enum' => 'The selected charge period is invalid.',
        ];
    }
}
