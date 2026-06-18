<?php

namespace App\Enums;

use App\Models\AvailabilityEvent;

/**
 * The kinds of event recorded in the `availability_events` log.
 *
 * Demand lifecycle changes, recalculations, stock changes and overdue
 * extensions are wired today (M2 + M3). Shortage event types are defined here so
 * the log schema and downstream consumers are stable, but they are emitted by
 * the shortage-detection milestone (M3 line items / shortages).
 *
 * @see AvailabilityEvent
 */
enum AvailabilityEventType: string
{
    /** A new demand was registered. */
    case DemandCreated = 'demand_created';

    /** A demand's quantity, period, or phase changed. */
    case DemandUpdated = 'demand_updated';

    /** A demand was deactivated (moved to Closed or Void). */
    case DemandReleased = 'demand_released';

    /** A demand's period was extended (overdue / turnaround). */
    case DemandExtended = 'demand_extended';

    /** A stock level was adjusted (purchase, write-off, count). */
    case StockChanged = 'stock_changed';

    /** Snapshots were refreshed for a product/store/range. */
    case AvailabilityRecalculated = 'availability_recalculated';

    /** A demand was extended to the sentinel because it became overdue. */
    case DemandOverdue = 'demand_overdue';

    /** Available quantity dropped below zero. */
    case ShortageDetected = 'shortage_detected';

    /** Available quantity returned to zero or above. */
    case ShortageResolved = 'shortage_resolved';
}
