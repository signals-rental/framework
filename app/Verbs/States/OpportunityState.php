<?php

namespace App\Verbs\States;

use App\Enums\OpportunityState as StateAxis;
use App\Enums\OpportunityStatus;
use Carbon\CarbonImmutable;
use Thunk\Verbs\State;

/**
 * In-memory event-sourced representation of an opportunity.
 *
 * Verbs folds events onto this object via their apply() methods. It holds only
 * public scalar properties (Verbs serialises public properties only) and carries
 * no business logic — business rules live in events, projections live in their
 * handle() methods.
 *
 * `state` and `status` are stored as the raw RMS integers (mirroring the
 * `opportunities` columns) so serialisation is trivial and the projection
 * mapping is one-to-one. Use {@see status()} / {@see StateAxis()} for the
 * type-safe enum view.
 */
class OpportunityState extends State
{
    /**
     * Application-allocated small projection PK (set by the genesis event).
     * The state's inherent `->id` remains the Verbs snowflake StateId.
     */
    public int $opportunity_id = 0;

    /** Document-type axis (0=Draft, 1=Quotation, 2=Order). */
    public int $state = 0;

    /** Workflow-position axis — per-state integer. */
    public int $status = 0;

    public ?string $subject = null;

    /** Zero-padded RMS reference number, allocated at create time. */
    public ?string $number = null;

    public ?int $member_id = null;

    public ?int $venue_id = null;

    public ?int $store_id = null;

    public ?int $owned_by = null;

    public ?string $reference = null;

    public ?string $description = null;

    public ?string $external_description = null;

    public ?CarbonImmutable $starts_at = null;

    public ?CarbonImmutable $ends_at = null;

    public ?CarbonImmutable $charge_starts_at = null;

    public ?CarbonImmutable $charge_ends_at = null;

    // Event-logistics lifecycle dates (C3a). Each phase is a start/end pair,
    // in physical-workflow order. All nullable, so old snapshots replay unchanged.
    public ?CarbonImmutable $prep_starts_at = null;

    public ?CarbonImmutable $prep_ends_at = null;

    public ?CarbonImmutable $load_starts_at = null;

    public ?CarbonImmutable $load_ends_at = null;

    public ?CarbonImmutable $deliver_starts_at = null;

    public ?CarbonImmutable $deliver_ends_at = null;

    public ?CarbonImmutable $setup_starts_at = null;

    public ?CarbonImmutable $setup_ends_at = null;

    public ?CarbonImmutable $show_starts_at = null;

    public ?CarbonImmutable $show_ends_at = null;

    public ?CarbonImmutable $takedown_starts_at = null;

    public ?CarbonImmutable $takedown_ends_at = null;

    public ?CarbonImmutable $collect_starts_at = null;

    public ?CarbonImmutable $collect_ends_at = null;

    public ?CarbonImmutable $unload_starts_at = null;

    public ?CarbonImmutable $unload_ends_at = null;

    public ?CarbonImmutable $deprep_starts_at = null;

    public ?CarbonImmutable $deprep_ends_at = null;

    /** Milestone: when the opportunity was ordered (C3a). */
    public ?CarbonImmutable $ordered_at = null;

    /** Milestone: when the quotation expires / becomes invalid (C3a). */
    public ?CarbonImmutable $quote_invalid_at = null;

    /** Whether the rental is billed on a manual chargeable-day count (C3b). */
    public bool $use_chargeable_days = false;

    /** Manual chargeable-day count as a decimal string, e.g. "2.5" (C3b). */
    public ?string $chargeable_days = null;

    /** Whether the rental is open-ended (no fixed return) (C3b). */
    public bool $open_ended_rental = false;

    /** Whether the customer is collecting the goods themselves (C3c). */
    public bool $customer_collecting = false;

    /** Whether the customer is returning the goods themselves (C3c). */
    public bool $customer_returning = false;

    public ?string $delivery_instructions = null;

    public ?string $collection_instructions = null;

    /** Sales priority/quality rating 0–5 (RMS `rating`), nullable (C3i). */
    public ?int $rating = null;

    /** FK to the chosen delivery address on the `addresses` table (C-data-2). */
    public ?int $delivery_address_id = null;

    /** FK to the chosen collection address on the `addresses` table (C-data-2). */
    public ?int $collection_address_id = null;

    /**
     * Free-form tag labels (RMS `tag_list`), projected to the JSONB
     * `opportunities.tag_list` column.
     *
     * @var list<string>
     */
    public array $tag_list = [];

    public int $item_count = 0;

    /**
     * The small projection id of the currently-active quote version, or 0 when
     * the opportunity has no versions (legacy / non-versioned). When non-zero the
     * opportunity's totals mirror this version and its line items are scoped to it.
     */
    public int $active_version_id = 0;

    /** Number of quote versions created on this opportunity (replay-stable). */
    public int $version_count = 0;

    /** True once at least one ALTERNATIVE version exists. */
    public bool $has_alternatives = false;

    /** Document currency (ISO 4217), snapshotted at creation. */
    public ?string $currency_code = null;

    /** Exchange-rate snapshot at creation time, as a decimal string. */
    public string $exchange_rate = '1';

    /**
     * When true, totals use the stored {@see $exchange_rate} and never re-derive
     * from the live rate. Locked at quote → order conversion (MC §4.3).
     */
    public bool $exchange_rate_locked = false;

    /**
     * When true, the stored tax figures are preserved and the final tax pass is
     * skipped on recompute. Locked at quote → order conversion (MC §7.2).
     */
    public bool $tax_locked = false;

    /** Headline charge total in integer minor units (pence/cents/fils). */
    public int $charge_total = 0;

    /**
     * Optional manual deal-total override in integer minor units; null = use the
     * engine-computed total. When set, it replaces {@see $charge_total}.
     */
    public ?int $deal_total = null;

    /** Per-transaction-type charge totals (minor units, optional lines excluded). */
    public int $rental_charge_total = 0;

    public int $sale_charge_total = 0;

    public int $service_charge_total = 0;

    /** Net (tax-exclusive) charge total in minor units. */
    public int $charge_excluding_tax_total = 0;

    /** Total tax in minor units (summed per line). */
    public int $tax_total = 0;

    /** Gross (tax-inclusive) charge total in minor units. */
    public int $charge_including_tax_total = 0;

    /** Whether stored line prices are tax-inclusive. */
    public bool $prices_include_tax = false;

    public bool $is_invoiced = false;

    /** True once the opportunity has been soft-deleted (archived) via OpportunityDeleted. */
    public bool $is_deleted = false;

    public ?CarbonImmutable $last_event_at = null;

    public function stateAxis(): StateAxis
    {
        return StateAxis::from($this->state);
    }

    public function status(): OpportunityStatus
    {
        return OpportunityStatus::fromStateAndStatus($this->stateAxis(), $this->status);
    }

    /**
     * True once the opportunity has reached a closed/terminal status and can no
     * longer be mutated through the standard lifecycle events.
     */
    public function isClosed(): bool
    {
        return $this->status()->isClosed();
    }
}
