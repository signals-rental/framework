<?php

namespace App\Actions\Shortages;

use App\Actions\Shortages\Concerns\TransitionsShortageResolution;
use App\Data\Shortages\ShortageResolutionData;
use App\Enums\ShortageResolutionStatus;
use App\Models\ShortageResolution;
use App\Services\Shortages\ShortageEventRecorder;
use Illuminate\Support\Facades\Gate;

/**
 * Completes a resolution (shortage-resolution-sub-hires.md §8.3:
 * in_progress → fulfilled, or partially_fulfilled → fulfilled).
 *
 * The resolution's stock is now available; stamps the fulfilment time and emits
 * `shortage.resolution.fulfilled`. Consumers (availability engine, shortage
 * detector) re-evaluate off this event.
 */
class FulfillShortageResolution
{
    use TransitionsShortageResolution;

    public function __construct(
        private readonly ShortageEventRecorder $events,
    ) {}

    public function __invoke(ShortageResolution $resolution): ShortageResolutionData
    {
        Gate::authorize('shortages.resolve');

        $this->guardTransition($resolution, ShortageResolutionStatus::Fulfilled);

        $resolution->forceFill([
            'status' => ShortageResolutionStatus::Fulfilled,
            'fulfilled_at' => now(),
        ])->save();

        $this->events->resolutionFulfilled($resolution);

        return ShortageResolutionData::fromModel($resolution->load('items'));
    }
}
