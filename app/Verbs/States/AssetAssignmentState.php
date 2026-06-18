<?php

namespace App\Verbs\States;

use App\Enums\AssetAssignmentStatus;
use App\Enums\AssetCondition;
use Carbon\CarbonImmutable;
use Thunk\Verbs\State;

/**
 * In-memory event-sourced representation of a single physical asset assigned to
 * an opportunity line item.
 *
 * Verbs folds asset events (M5) onto this object via their apply() methods. It
 * holds only public scalar properties and carries no business logic — allocation/
 * dispatch/return rules live in events, the projection lives in their handle()
 * methods. One state instance tracks one physical asset through its individual
 * dispatch/return cycle.
 *
 * This class is scaffolding for M5: no events mutate it yet.
 */
class AssetAssignmentState extends State
{
    /**
     * Application-allocated small projection PK (set by the genesis event).
     * The state's inherent `->id` remains the Verbs snowflake StateId.
     */
    public int $assignment_id = 0;

    /** Parent line item's small projection id. */
    public int $opportunity_item_id = 0;

    /** The specific physical asset (stock_levels id). */
    public ?int $stock_level_id = null;

    /** Per-asset position in the dispatch/return cycle. */
    public int $status = AssetAssignmentStatus::Allocated->value;

    /** Kit/case this asset is nested within, if any (stock_levels id). */
    public ?int $container_stock_level_id = null;

    /** Lifecycle milestone timestamps. */
    public ?CarbonImmutable $allocated_at = null;

    public ?CarbonImmutable $prepared_at = null;

    public ?CarbonImmutable $dispatched_at = null;

    public ?CarbonImmutable $returned_at = null;

    public ?CarbonImmutable $checked_at = null;

    /** Condition assessed at check-in (null until checked in). */
    public ?int $condition_on_return = null;

    public ?CarbonImmutable $last_event_at = null;

    public function statusEnum(): AssetAssignmentStatus
    {
        return AssetAssignmentStatus::from($this->status);
    }

    public function conditionOnReturn(): ?AssetCondition
    {
        return $this->condition_on_return !== null
            ? AssetCondition::from($this->condition_on_return)
            : null;
    }
}
