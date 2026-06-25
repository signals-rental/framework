/**
 * Safe revision tokens for Verbs event ids (snowflakes exceed Number.MAX_SAFE_INTEGER).
 */

export function normalizeRevision(value) {
    if (value === null || value === undefined || value === '') {
        return '0';
    }

    return String(value).trim() || '0';
}

/**
 * @returns {-1|0|1}
 */
export function compareRevisions(left, right) {
    const a = BigInt(normalizeRevision(left));
    const b = BigInt(normalizeRevision(right));

    if (a < b) {
        return -1;
    }

    if (a > b) {
        return 1;
    }

    return 0;
}

export function revisionEquals(left, right) {
    return compareRevisions(left, right) === 0;
}

export default {
    normalizeRevision,
    compareRevisions,
    revisionEquals,
};
