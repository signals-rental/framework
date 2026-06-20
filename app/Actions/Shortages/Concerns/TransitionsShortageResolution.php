<?php

namespace App\Actions\Shortages\Concerns;

use App\Enums\ShortageResolutionStatus;
use App\Models\ShortageResolution;
use Illuminate\Validation\ValidationException;

/**
 * Shared guard for the shortage-resolution status-transition actions
 * (shortage-resolution-sub-hires.md §8.3).
 *
 * Each transition action authorises, validates the move against the §8.3 matrix
 * via {@see ShortageResolutionStatus::canTransitionTo()}, then mutates the record
 * and emits the matching `shortage.resolution.*` event through the recorder. An
 * illegal move (e.g. confirming an already-cancelled resolution) is rejected as a
 * 422 validation error rather than silently no-op'ing.
 */
trait TransitionsShortageResolution
{
    /**
     * Reject the transition with a 422 when $resolution cannot move to $target.
     */
    protected function guardTransition(ShortageResolution $resolution, ShortageResolutionStatus $target): void
    {
        $current = $resolution->status;

        if ($current === $target) {
            throw ValidationException::withMessages([
                'status' => ["This resolution is already {$target->label()}."],
            ]);
        }

        if (! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => [
                    "A {$current->label()} resolution cannot transition to {$target->label()}.",
                ],
            ]);
        }
    }
}
