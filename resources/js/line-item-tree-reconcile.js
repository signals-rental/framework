/**
 * Client-side reconcile helpers for the local-first opportunity line-item editor.
 * Mirrors {@see App\Services\Opportunities\LineItemTreeReconciler} for browser tests.
 */

const STRUCTURAL_MUTATION_KINDS = new Set(['persistTree', 'addGroup', 'addProduct', 'delete', 'deleteSection']);

export function hasStructuralPending(queue) {
    return queue.some((mutation) => STRUCTURAL_MUTATION_KINDS.has(mutation.kind));
}

export function pendingLocalIdsFromQueue(queue) {
    const ids = new Set();

    for (const mutation of queue) {
        if (mutation.kind === 'field' && mutation.id != null) {
            ids.add(Number(mutation.id));
        }
    }

    return [...ids].filter((id) => id !== 0);
}

export function reconcileLocalTree(localRows, serverRows, pendingLocalIds = []) {
    const pending = new Set(pendingLocalIds.map(Number));
    const localById = Object.fromEntries(localRows.map((row) => [Number(row.id), row]));
    const merged = [];
    const conflicts = {};

    for (const serverRow of serverRows) {
        const id = Number(serverRow.id);
        const local = localById[id];

        if (!local) {
            merged.push(serverRow);
            continue;
        }

        if (id < 0 || pending.has(id)) {
            if (hasHardFieldConflict(local, serverRow)) {
                conflicts[id] = 'This line was also changed elsewhere.';
            }

            merged.push(local);
            continue;
        }

        merged.push(serverRow);
    }

    for (const row of localRows) {
        if (Number(row.id) < 0) {
            merged.push(row);
        }
    }

    return {
        rows: sortPreOrder(merged),
        conflicts,
    };
}

function hasHardFieldConflict(local, server) {
    const fields = ['quantity', 'unit_price', 'discount_percent', 'name'];

    for (const field of fields) {
        if ((local[field] ?? null) !== (server[field] ?? null)) {
            return true;
        }
    }

    return false;
}

function sortPreOrder(rows) {
    return [...rows].sort((a, b) => {
        const pathCompare = String(a.path ?? '').localeCompare(String(b.path ?? ''));

        if (pathCompare !== 0) {
            return pathCompare;
        }

        return Number(a.id ?? 0) - Number(b.id ?? 0);
    });
}

export default {
    hasStructuralPending,
    pendingLocalIdsFromQueue,
    reconcileLocalTree,
};
