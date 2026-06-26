/**
 * Optimistic flush helpers for the local-first line-item editor (drag/field/add paths).
 * Deletes are synchronous and do not use this queue.
 */

export function resolveServerItemId(id, rows = []) {
    const numeric = Number(id);

    if (Number.isFinite(numeric) && numeric > 0) {
        return numeric;
    }

    const row = rows.find((candidate) => Number(candidate.id) === numeric);

    if (row && Number(row.id) > 0) {
        return Number(row.id);
    }

    return null;
}

export function rowsEligibleForPersistTree(rows, pendingDeleteIds = new Set()) {
    const pending = pendingDeleteIds instanceof Set
        ? pendingDeleteIds
        : new Set((pendingDeleteIds || []).map(Number));

    return rows.filter((row) => {
        const rowId = Number(row.id);

        return rowId > 0 && ! pending.has(rowId);
    });
}

export function shouldScheduleFlushRetry({ wasBlocked, queueLength, pendingFlushFlag }) {
    return wasBlocked || pendingFlushFlag || queueLength > 0;
}

export function orderFlushBatch(batch) {
    const persistTrees = batch.filter((mutation) => mutation.kind === 'persistTree');
    const middle = batch.filter((mutation) => mutation.kind !== 'persistTree');

    return [...middle, ...persistTrees.slice(0, 1)];
}

/**
 * Resolve the mutations that must be unshifted back onto the queue after a flush.
 *
 * On ERROR (`caught` true) the FULL batch is re-queued exactly once and the partial
 * `requeue` (which may have been populated before the throw on the success path) is
 * ignored — re-applying it would double-queue those mutations. On SUCCESS only the
 * partial `requeue` (deferred/persistTree retries) is returned, exactly once.
 *
 * @param {{ caught: boolean, batch?: Array, requeue?: Array }} args
 * @returns {Array}
 */
export function mutationsToRequeue({ caught, batch = [], requeue = [] }) {
    if (caught) {
        return [...batch];
    }

    return [...requeue];
}

export default {
    resolveServerItemId,
    rowsEligibleForPersistTree,
    shouldScheduleFlushRetry,
    orderFlushBatch,
    mutationsToRequeue,
};
