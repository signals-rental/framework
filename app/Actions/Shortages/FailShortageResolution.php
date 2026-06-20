<?php

namespace App\Actions\Shortages;

use App\Actions\Shortages\Concerns\TransitionsShortageResolution;
use App\Data\Shortages\ShortageResolutionData;
use App\Data\Shortages\TransitionShortageResolutionData;
use App\Enums\ShortageResolutionStatus;
use App\Models\ShortageResolution;
use App\Services\Shortages\ShortageEventRecorder;
use Illuminate\Support\Facades\Gate;

/**
 * Marks a shortage resolution failed (shortage-resolution-sub-hires.md §8.3:
 * pending → failed, monitoring → failed).
 *
 * The resolution attempt did not succeed (e.g. a supplier declined); the shortage
 * may reappear on re-evaluation. The reason is persisted to `cancellation_reason`
 * (the single reason column) and surfaced on the
 * `shortage.resolution.failed` event payload.
 */
class FailShortageResolution
{
    use TransitionsShortageResolution;

    public function __construct(
        private readonly ShortageEventRecorder $events,
    ) {}

    public function __invoke(ShortageResolution $resolution, TransitionShortageResolutionData $data): ShortageResolutionData
    {
        Gate::authorize('shortages.resolve');

        $this->guardTransition($resolution, ShortageResolutionStatus::Failed);

        $resolution->forceFill([
            'status' => ShortageResolutionStatus::Failed,
            'cancellation_reason' => $data->reason,
        ])->save();

        $this->events->resolutionFailed($resolution, $data->reason);

        return ShortageResolutionData::fromModel($resolution->load('items'));
    }
}
