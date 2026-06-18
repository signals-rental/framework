<?php

namespace App\Verbs\States;

use App\Enums\ChargePeriod;
use App\Enums\LineItemTransactionType;
use Carbon\CarbonImmutable;
use Thunk\Verbs\State;

/**
 * In-memory event-sourced representation of an opportunity line item.
 *
 * Verbs folds item events (M3) onto this object via their apply() methods. It
 * holds only public scalar properties (Verbs serialises public properties only)
 * and carries no business logic — pricing/quantity rules live in events, the
 * projection lives in their handle() methods.
 *
 * Money (`unit_price`, `total`) is stored as integer minor units (pence/cents/
 * fils) mirroring the `opportunity_items` columns. Quantities are decimal strings
 * so bc-math arithmetic stays lossless. `starts_at` / `ends_at` are the effective
 * dates after override resolution against the parent opportunity.
 */
class OpportunityItemState extends State
{
    /**
     * Application-allocated small projection PK (set by the genesis event).
     * The state's inherent `->id` remains the Verbs snowflake StateId.
     */
    public int $opportunity_item_id = 0;

    /** Parent opportunity's small projection id. */
    public int $opportunity_id = 0;

    /** Quotation version scope (null for orders/legacy). */
    public ?int $version_id = null;

    /** Catalogued item reference (polymorphic — products today). */
    public ?int $item_id = null;

    public ?string $item_type = null;

    /** Display name of the line (catalogue name snapshot or ad-hoc label). */
    public ?string $name = null;

    public ?string $description = null;

    /** Requested quantity as a decimal string (lossless bc-math). */
    public string $quantity = '0';

    /** Price per unit per charge period, in integer minor units. */
    public int $unit_price = 0;

    /**
     * Operator-supplied manual unit-price override in integer minor units; when
     * non-null it ALWAYS wins over the resolved rate. Null = derive from the rate
     * engine (or 0 when no rate matches).
     */
    public ?int $manual_unit_price = null;

    /** Unit of charge the unit_price is quoted against. */
    public int $charge_period = ChargePeriod::Day->value;

    /** Calculated line total in integer minor units (net, tax-exclusive). */
    public int $total = 0;

    /** Resolved tax rate for this line as a decimal-string percentage. */
    public ?string $tax_rate = null;

    /** Line discount as a decimal-string percentage (null = none). */
    public ?string $discount_percent = null;

    /** Commercial nature of the line (rental/sale/service/sub-rental). */
    public int $transaction_type = LineItemTransactionType::Rental->value;

    /** Effective hire window (after per-item override resolution). */
    public ?CarbonImmutable $starts_at = null;

    public ?CarbonImmutable $ends_at = null;

    /** Quantity allocated to physical assets, as a decimal string. */
    public string $allocated_quantity = '0';

    /** Quantity physically dispatched, as a decimal string. */
    public string $dispatched_quantity = '0';

    /** Quantity returned/checked in, as a decimal string. */
    public string $returned_quantity = '0';

    /** Display ordering within the parent opportunity. */
    public int $sort_order = 0;

    /** Optional lines are excluded from the opportunity's charge totals. */
    public bool $is_optional = false;

    /** Per-line currency snapshot (inherits the opportunity currency). */
    public ?string $currency_code = null;

    /**
     * Inline RMS line-item custom-field map (distinct from the entity EAV
     * system).
     *
     * @var array<string, mixed>|null
     */
    public ?array $custom_fields = null;

    public ?string $notes = null;

    /**
     * True once the line has been removed; the projection row is hard-deleted in
     * the same event handler, so this flag mostly guards in-memory replays.
     */
    public bool $is_removed = false;

    public ?CarbonImmutable $last_event_at = null;

    public function chargePeriod(): ChargePeriod
    {
        return ChargePeriod::from($this->charge_period);
    }

    public function transactionType(): LineItemTransactionType
    {
        return LineItemTransactionType::from($this->transaction_type);
    }
}
