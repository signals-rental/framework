/**
 * Server-wins boot reconciliation for the local-first line-items editor.
 * Dexie cache is used for instant paint only when revision + structure match __lfSeed.
 */

import { compareRevisions, normalizeRevision } from './line-item-revision.js';

/**
 * @param {array|null|undefined} rows
 */
export function treeStructureSignature(rows) {
    if (!Array.isArray(rows) || rows.length === 0) {
        return '';
    }

    return rows
        .map((row) => `${Number(row.id)}:${Number(row.depth) || 1}:${row.parent_group_id ?? ''}`)
        .join('|');
}

/**
 * @param {array|null|undefined} rows
 * @returns {Set<number>}
 */
export function treeIdSet(rows) {
    /** @type {Set<number>} */
    const ids = new Set();

    if (!Array.isArray(rows)) {
        return ids;
    }

    for (const row of rows) {
        const id = Number(row?.id);

        if (Number.isFinite(id) && id > 0) {
            ids.add(id);
        }
    }

    return ids;
}

/**
 * @param {{ cached?: array|null, meta?: object|null, seedPayload?: object|null }} input
 * @returns {{ source: 'cache'|'server', reason: string, cacheRevision: number|null, serverRevision: number }}
 */
export function resolveBootSource({ cached, meta, seedPayload }) {
    const seedRows = Array.isArray(seedPayload?.tree) ? seedPayload.tree : [];
    const serverRevision = normalizeRevision(seedPayload?.revision);
    const serverToken = String(seedPayload?.cacheToken || '');
    const cacheRevision = meta != null ? normalizeRevision(meta.revision) : null;

    if (!Array.isArray(cached) || cached.length === 0) {
        return {
            source: 'server',
            reason: 'missing-cache',
            cacheRevision,
            serverRevision,
        };
    }

    if (!meta) {
        return {
            source: 'server',
            reason: 'missing-meta',
            cacheRevision,
            serverRevision,
        };
    }

    if (String(meta.cacheToken || '') !== serverToken) {
        return {
            source: 'server',
            reason: 'cache-token-mismatch',
            cacheRevision,
            serverRevision,
        };
    }

    if (cacheRevision === null || compareRevisions(cacheRevision, serverRevision) < 0) {
        return {
            source: 'server',
            reason: 'cache-stale',
            cacheRevision,
            serverRevision,
        };
    }

    if (compareRevisions(cacheRevision, serverRevision) > 0) {
        return {
            source: 'server',
            reason: 'cache-ahead',
            cacheRevision,
            serverRevision,
        };
    }

    const seedIds = treeIdSet(seedRows);
    const cachedIds = treeIdSet(cached);

    if (cachedIds.size !== seedIds.size || cached.length !== seedRows.length) {
        return {
            source: 'server',
            reason: 'partial-cache',
            cacheRevision,
            serverRevision,
        };
    }

    for (const id of seedIds) {
        if (!cachedIds.has(id)) {
            return {
                source: 'server',
                reason: 'corrupt-cache-ids',
                cacheRevision,
                serverRevision,
            };
        }
    }

    if (treeStructureSignature(seedRows) !== treeStructureSignature(cached)) {
        return {
            source: 'server',
            reason: 'corrupt-cache-structure',
            cacheRevision,
            serverRevision,
        };
    }

    return {
        source: 'cache',
        reason: 'in-sync',
        cacheRevision,
        serverRevision,
    };
}

export default {
    treeStructureSignature,
    treeIdSet,
    resolveBootSource,
};
