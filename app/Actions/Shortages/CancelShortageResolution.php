<?php

namespace App\Actions\Shortages;

use App\Actions\Shortages\Concerns\TransitionsShortageResolution;
use App\Data\Shortages\ShortageResolutionData;
use App\Data\Shortages\TransitionShortageResolutionData;
use App\Enums\ShortageResolutionStatus;
use App\Models\ShortageResolution;
use App\Services\Shortages\ShortageEventRecorder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Cancels a shortage resolution (shortage-resolution-sub-hires.md §8.3:
 * pending → cancelled, monitoring → cancelled, or confirmed → cancelled).
 *
 * The resolution is withdrawn; the shortage may reappear on re-evaluation. Stamps
 * the cancellation time and reason, then emits `shortage.resolution.cancelled`.
 */
class CancelShortageResolution
{
    use TransitionsShortageResolution;

    public function __construct(
        private readonly ShortageEventRecorder $events,
    ) {}

    public function __invoke(ShortageResolution $resolution, TransitionShortageResolutionData $data): ShortageResolutionData
    {
        Gate::authorize('shortages.resolve');

        $this->guardTransition($resolution, ShortageResolutionStatus::Cancelled);

        $resolution->forceFill([
            'status' => ShortageResolutionStatus::Cancelled,
            'cancelled_at' => Carbon::now('UTC'),
            'cancellation_reason' => $data->reason,
        ])->save();

        $this->events->resolutionCancelled($resolution);

        return ShortageResolutionData::fromModel($resolution->load('items'));
    }
}
