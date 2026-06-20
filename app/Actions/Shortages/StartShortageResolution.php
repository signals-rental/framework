<?php

namespace App\Actions\Shortages;

use App\Actions\Shortages\Concerns\TransitionsShortageResolution;
use App\Data\Shortages\ShortageResolutionData;
use App\Enums\ShortageResolutionStatus;
use App\Models\ShortageResolution;
use App\Services\Shortages\ShortageEventRecorder;
use Illuminate\Support\Facades\Gate;

/**
 * Moves a confirmed resolution into active fulfilment
 * (shortage-resolution-sub-hires.md §8.3: confirmed → in_progress).
 *
 * Signals that fulfilment has begun (e.g. stock is in transit from a supplier),
 * then emits `shortage.resolution.in_progress`.
 */
class StartShortageResolution
{
    use TransitionsShortageResolution;

    public function __construct(
        private readonly ShortageEventRecorder $events,
    ) {}

    public function __invoke(ShortageResolution $resolution): ShortageResolutionData
    {
        Gate::authorize('shortages.resolve');

        $this->guardTransition($resolution, ShortageResolutionStatus::InProgress);

        $resolution->forceFill([
            'status' => ShortageResolutionStatus::InProgress,
        ])->save();

        $this->events->resolutionInProgress($resolution);

        return ShortageResolutionData::fromModel($resolution->load('items'));
    }
}
