<?php

namespace App\Livewire\Concerns;

use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Shared error-handling for entity merge modals (members, products).
 *
 * The two merge components run an identical catch/flash/log ladder around the
 * merge action call. This trait centralises that ladder; each component keeps
 * its own entity-specific picker logic and supplies the entity label so the
 * flashed messages read naturally.
 */
trait HandlesMergeErrors
{
    /**
     * Run the merge action, translating failures into flashed error messages.
     *
     * Returns true when the merge succeeded (no exception thrown), false when a
     * recoverable failure was caught and flashed.
     *
     * @param  Closure(): mixed  $callback  The action invocation to guard.
     * @param  string  $entityLabel  Singular, lower-case entity noun (e.g. "member").
     * @param  array<string, mixed>  $logContext  Context attached to unexpected-failure logs.
     */
    protected function runGuardedMerge(Closure $callback, string $entityLabel, array $logContext = []): bool
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            // Business-rule failures (type mismatch, self-merge) surface as a
            // validation error keyed on secondary_id. Show the first message.
            session()->flash('error', $e->validator->errors()->first() ?: "Unable to merge the selected {$entityLabel}s.");

            return false;
        } catch (ModelNotFoundException) {
            session()->flash('error', "One of the selected {$entityLabel}s no longer exists.");

            return false;
        } catch (\Throwable $e) {
            Log::error(ucfirst($entityLabel).' merge failed', [
                ...$logContext,
                'error' => $e->getMessage(),
            ]);
            session()->flash('error', 'An unexpected error occurred while merging. Please try again.');

            return false;
        }

        return true;
    }
}
