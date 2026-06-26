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

export default {
    resolveServerItemId,
    rowsEligibleForPersistTree,
    shouldScheduleFlushRetry,
    orderFlushBatch,
};
