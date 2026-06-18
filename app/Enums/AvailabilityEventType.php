<?php

namespace App\Enums;

use App\Models\AvailabilityEvent;

/**
 * The kinds of event recorded in the `availability_events` log.
 *
 * Demand lifecycle changes, recalculations, stock changes and overdue
 * extensions are wired today (M2 + M3). Shortage detection/clear and resolution
 * events are emitted by the shortage subsystem (M3-5 Track C).
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

    /** A line item's requested quantity exceeds available stock. */
    case ShortageDetected = 'shortage_detected';

    /** A previously-detected shortage ceased to exist (stock freed, dates/quantity changed). */
    case ShortageResolved = 'shortage_resolved';

    /** A resolution record was created against a shortage. */
    case ShortageResolutionCreated = 'shortage_resolution_created';

    /** A shortage resolution was confirmed. */
    case ShortageResolutionConfirmed = 'shortage_resolution_confirmed';

    /** A shortage resolution was cancelled. */
    case ShortageResolutionCancelled = 'shortage_resolution_cancelled';
}
