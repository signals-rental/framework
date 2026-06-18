<?php

namespace App\Concerns;

use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\Broker;
use Thunk\Verbs\Lifecycle\Queue;
use Thunk\Verbs\Lifecycle\StateManager;

/**
 * Atomic commit boundary for Verbs event-sourced actions.
 *
 * Verbs has no native atomic commit. {@see Broker::commit()}
 * flushes the in-memory event queue — writing the `verb_events` rows — BEFORE it
 * runs each event's `handle()` projection. A projection failure therefore leaves a
 * committed event row with no corresponding projected model: an orphaned event store.
 *
 * Every state-mutating action wraps its `fire()` calls plus `Verbs::commit()` in a
 * single database transaction via {@see commitVerbs()}. If anything throws, the
 * transaction rolls back both the event-row inserts and the projection writes, so the
 * database is left untouched.
 *
 * The transaction rollback does NOT, however, rewind Verbs' in-memory bookkeeping:
 * the {@see StateManager} cache still holds the mutated (now-rolled-back) state, and
 * the {@see Queue} may still hold un-flushed events. On a single short-lived web
 * request that divergence dies with the request, but under Octane or a long-running
 * queue worker the stale in-memory state would leak into the next operation. To keep
 * the in-memory representation consistent with the persisted truth, on any exception
 * we reset the StateManager and clear the event queue before rethrowing.
 */
trait CommitsVerbsEvents
{
    /**
     * Run the given closure (which fires Verbs events), then commit, inside one
     * database transaction. On failure, reset Verbs' in-memory state and rethrow.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $fn
     * @return TReturn
     */
    protected function commitVerbs(Closure $fn): mixed
    {
        try {
            return DB::transaction(function () use ($fn): mixed {
                $result = $fn();

                Verbs::commit();

                return $result;
            });
        } catch (Throwable $e) {
            $this->resetVerbsState();

            throw $e;
        }
    }

    /**
     * Discard Verbs' in-memory bookkeeping so it cannot diverge from the
     * database after a rolled-back transaction.
     *
     * Resets the in-memory state cache (without touching snapshot storage, which
     * the rolled-back transaction already reverted) and clears any events that
     * were queued but never durably committed.
     */
    protected function resetVerbsState(): void
    {
        app(StateManager::class)->reset();

        app(Queue::class)->event_queue = [];
    }
}
