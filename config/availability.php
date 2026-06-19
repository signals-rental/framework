<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum Slots Per Recalculation
    |--------------------------------------------------------------------------
    |
    | A belt-and-suspenders ceiling on the number of slots a single
    | SlotCalculator::generateSlots() call may produce. Even with the rolling
    | horizon clamp in place, this guards against any future caller (or a
    | misconfigured resolution) re-introducing an unbounded slot blow-up: the
    | calculator throws once a requested span would exceed this count.
    |
    | The default comfortably covers the widest legitimate window — the full
    | rolling horizon (90 + 365 = 455 days) at hourly resolution is ~10,920
    | slots — with generous headroom.
    |
    */

    'max_slots_per_recalculation' => (int) env('AVAILABILITY_MAX_SLOTS_PER_RECALCULATION', 50000),

    /*
    |--------------------------------------------------------------------------
    | Suppress Stock-Change Recalculation
    |--------------------------------------------------------------------------
    |
    | When true, the StockLevelObserver does NOT trigger a synchronous
    | availability recalculation on stock-level writes. Intended as an escape
    | hatch for bulk operations — seeders and large imports — that would
    | otherwise fan out one full-horizon recalc per stock row (a "recalc storm").
    |
    | Toggle it around such operations with config(['availability...' => true]),
    | then run a single bounded recalculation afterwards. Demand-driven recalcs
    | (the DemandObserver) are unaffected; only stock-change triggers are gated.
    |
    */

    'suppress_stock_recalc' => (bool) env('AVAILABILITY_SUPPRESS_STOCK_RECALC', false),

    /*
    |--------------------------------------------------------------------------
    | Asynchronous Recalculation
    |--------------------------------------------------------------------------
    |
    | In M3-4 the demand/stock observers no longer recompute snapshots inline.
    | They dispatch a RecalculateAvailabilityJob carrying the affected
    | product/store; the job runs the RecalculationPipeline over the rolling
    | horizon on a Horizon-managed queue. Point queries
    | (AvailabilityService::getAvailability) stay exact — they read `demands`
    | live — so only the snapshot/range/calendar read model becomes
    | eventually-consistent.
    |
    | `queue`            — the named queue the recalc job is pushed onto.
    | `debounce_seconds` — the ShouldBeUnique lock window. A burst of demand or
    |                      stock changes for the SAME product/store within this
    |                      window coalesces into a single recompute, since each
    |                      dispatch shares the unique id "availability:{p}:{s}".
    |
    */

    'recalc' => [
        'queue' => env('AVAILABILITY_RECALC_QUEUE', 'availability'),
        'debounce_seconds' => (int) env('AVAILABILITY_RECALC_DEBOUNCE_SECONDS', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Overdue Demand Detection
    |--------------------------------------------------------------------------
    |
    | The DetectOverdueDemands scheduled command extends still-active demands
    | whose scheduled `ends_at` has passed (and is not already the sentinel) out
    | to the sentinel "no known end" date, so availability keeps reflecting the
    | unreturned stock until an actual return is recorded. `batch_size` bounds
    | how many overdue demands a single run processes — the command is
    | idempotent and safe to re-run, so any remainder is picked up next run.
    |
    */

    'overdue' => [
        'batch_size' => (int) env('AVAILABILITY_OVERDUE_BATCH_SIZE', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Kit Nesting Maximum Depth
    |--------------------------------------------------------------------------
    |
    | The deepest a kit may nest other kits before the chain is rejected. This is
    | an infrastructure safety bound (not a tenant setting): it caps the recursion
    | the KitAvailabilityCalculator performs when composing a kit's availability
    | from its components, and is enforced at composition-create time so a cycle or
    | runaway chain can never be persisted. Depth 1 = a kit whose components are
    | all leaf products; the default of 3 permits kit-of-kit-of-kit.
    |
    */

    'kit_nesting_max_depth' => (int) env('AVAILABILITY_KIT_NESTING_MAX_DEPTH', 3),

];
