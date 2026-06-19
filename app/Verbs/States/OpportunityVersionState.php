<?php

namespace App\Verbs\States;

use App\Enums\VersionStatus;
use App\Enums\VersionType;
use Carbon\CarbonImmutable;
use Thunk\Verbs\State;

/**
 * In-memory event-sourced representation of a quote version
 * (opportunity-lifecycle.md §8).
 *
 * Verbs folds version events onto this object via their apply() methods. It holds
 * only public scalar properties (Verbs serialises public properties only) and
 * carries no business logic — version rules live in events, the projection lives
 * in their handle() methods.
 *
 * Money totals (`charge_*`) are integer minor units (NET, ex-tax), mirroring the
 * `opportunity_versions` columns; they are rolled up from the version's scoped
 * line items by the totals calculator and synced onto this state by
 * {@see App\Verbs\Events\Opportunities\VersionActivated} when the version becomes
 * active.
 */
class OpportunityVersionState extends State
{
    /**
     * Application-allocated small projection PK (set by the genesis event).
     * The state's inherent `->id` remains the Verbs snowflake StateId.
     */
    public int $version_id = 0;

    /** Parent opportunity's small projection id. */
    public int $opportunity_id = 0;

    /** Per-opportunity sequential version number (replay-stable). */
    public int $version_number = 0;

    public ?int $parent_version_id = null;

    /** Forward lineage: the version that superseded this one (null while live). */
    public ?int $superseded_by_version_id = null;

    public int $version_type = VersionType::Revision->value;

    public ?string $label = null;

    public bool $is_active = false;

    public int $status = VersionStatus::Draft->value;

    public int $charge_excluding_tax_total = 0;

    public int $tax_total = 0;

    public int $charge_including_tax_total = 0;

    public int $charge_total = 0;

    /** Currency context snapshotted from the parent opportunity at creation. */
    public ?string $currency_code = null;

    /** Base-currency exchange rate snapshot, mirroring the parent opportunity. */
    public ?string $exchange_rate = null;

    public ?string $notes = null;

    public ?int $created_by = null;

    /** The member the version was last sent to (§8.6); null until sent. */
    public ?int $sent_to = null;

    /** The channel the version was sent through (email/portal/manual). */
    public ?string $sent_via = null;

    /** The member who accepted the version (§8.6); null until accepted. */
    public ?int $accepted_by = null;

    public bool $is_deleted = false;

    public ?CarbonImmutable $last_event_at = null;

    public function status(): VersionStatus
    {
        return VersionStatus::from($this->status);
    }

    public function versionType(): VersionType
    {
        return VersionType::from($this->version_type);
    }
}
