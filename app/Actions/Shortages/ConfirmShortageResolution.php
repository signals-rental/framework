<?php

namespace App\Actions\Shortages;

use App\Actions\Shortages\Concerns\TransitionsShortageResolution;
use App\Data\Shortages\ShortageResolutionData;
use App\Enums\ShortageResolutionStatus;
use App\Models\ShortageResolution;
use App\Services\Shortages\ShortageEventRecorder;
use Illuminate\Support\Facades\Gate;

/**
 * Confirms a pending (or monitoring) shortage resolution
 * (shortage-resolution-sub-hires.md §8.3: pending → confirmed).
 *
 * Marks the resolution accepted (e.g. a supplier confirmed a sub-hire, or a
 * watched shortage freed up), stamping the confirming user and time, then emits
 * `shortage.resolution.confirmed`.
 */
class ConfirmShortageResolution
{
    use TransitionsShortageResolution;

    public function __construct(
        private readonly ShortageEventRecorder $events,
    ) {}

    public function __invoke(ShortageResolution $resolution): ShortageResolutionData
    {
        Gate::authorize('shortages.resolve');

        $this->guardTransition($resolution, ShortageResolutionStatus::Confirmed);

        $resolution->forceFill([
            'status' => ShortageResolutionStatus::Confirmed,
            'confirmed_by' => auth()->id(),
            'confirmed_at' => now(),
        ])->save();

        $this->events->resolutionConfirmed($resolution);

        return ShortageResolutionData::fromModel($resolution->load('items'));
    }
}
