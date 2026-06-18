<?php

namespace App\Verbs\States;

use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityCostType;
use Carbon\CarbonImmutable;
use Thunk\Verbs\State;

/**
 * In-memory event-sourced representation of an opportunity cost.
 *
 * Verbs folds cost events (M3-2) onto this object via their apply() methods. It
 * holds only public scalar properties (Verbs serialises public properties only)
 * and carries no business logic — pricing/quantity rules live in events, the
 * projection lives in their handle() methods.
 *
 * Money (`amount`) is stored as integer minor units (pence/cents/fils) mirroring
 * the `opportunity_costs.amount` column. Quantity is a decimal string so bc-math
 * arithmetic stays lossless. Costs are NOT priced by the rate engine — they carry
 * their own `amount`.
 */
class OpportunityCostState extends State
{
    /**
     * Application-allocated small projection PK (set by the genesis event).
     * The state's inherent `->id` remains the Verbs snowflake StateId.
     */
    public int $opportunity_cost_id = 0;

    /** Parent opportunity's small projection id. */
    public int $opportunity_id = 0;

    public ?string $description = null;

    /** Categorical cost type (delivery/labour/surcharge/insurance/loss-damage/misc). */
    public int $cost_type = OpportunityCostType::Misc->value;

    /** Commercial nature of the cost (defaults to Service). */
    public int $transaction_type = LineItemTransactionType::Service->value;

    /** Per-unit charge in integer minor units (NOT rate-engine priced). */
    public int $amount = 0;

    /** Requested quantity as a decimal string (lossless bc-math). */
    public string $quantity = '1';

    /** Resolved tax rate for the cost as a decimal-string percentage. */
    public ?string $tax_rate = null;

    /** Per-cost currency snapshot (inherits the opportunity currency). */
    public ?string $currency_code = null;

    /** Optional costs are excluded from the opportunity's charge totals. */
    public bool $is_optional = false;

    /** Display ordering within the parent opportunity. */
    public int $sort_order = 0;

    public ?string $notes = null;

    /**
     * True once the cost has been removed; the projection row is hard-deleted in
     * the same event handler, so this flag mostly guards in-memory replays.
     */
    public bool $is_removed = false;

    public ?CarbonImmutable $last_event_at = null;

    public function costType(): OpportunityCostType
    {
        return OpportunityCostType::from($this->cost_type);
    }

    public function transactionType(): LineItemTransactionType
    {
        return LineItemTransactionType::from($this->transaction_type);
    }
}
