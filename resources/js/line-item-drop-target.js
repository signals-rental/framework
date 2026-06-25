/**
 * Drop-target depth resolution for the local-first line-item editor.
 * Primary nesting: the group under the pointer (or whose child region contains
 * the insert index) determines parent depth — horizontal indent is secondary.
 */

export const INDENT_PX = 22;

export function blockEndIndex(rows, startIndex) {
    const node = rows[startIndex];

    if (!node) {
        return startIndex;
    }

    let end = startIndex + 1;

    while (end < rows.length && rows[end].depth > node.depth) {
        end++;
    }

    return end;
}

/**
 * True when {@see insertIndex} falls strictly inside a group's child band
 * (after the group header, before the first following row at the same depth).
 */
export function isInsertInsideGroupChildRegion(rows, groupIndex, insertIndex) {
    if (groupIndex < 0 || insertIndex <= groupIndex) {
        return false;
    }

    const end = blockEndIndex(rows, groupIndex);

    return insertIndex < end;
}

export function canPlace(draggedNode, parentNode, originalParentId = null) {
    const type = draggedNode.item_type;

    if (type === 'accessory') {
        if (!parentNode || parentNode.item_type !== 'product') {
            return false;
        }

        if (originalParentId == null) {
            return parentNode.item_type === 'product';
        }

        return parentNode.id === originalParentId;
    }

    if (parentNode === null) {
        return true;
    }

    return parentNode.item_type === 'group';
}

export function parentAt(rest, insertIndex, depth) {
    if (depth == null || depth <= 1) {
        return null;
    }

    for (let i = insertIndex - 1; i >= 0; i--) {
        if (rest[i].depth === depth - 1) {
            return rest[i];
        }

        if (rest[i].depth < depth - 1) {
            return null;
        }
    }

    return null;
}

export function minNestDepthForInsert(rest, insertIndex, node, originalParentId = null) {
    if (node.item_type === 'accessory') {
        return null;
    }

    for (let i = insertIndex - 1; i >= 0; i--) {
        const row = rest[i];

        if (row.item_type !== 'group') {
            if (row.depth <= 1) {
                break;
            }

            continue;
        }

        if (!isInsertInsideGroupChildRegion(rest, i, insertIndex)) {
            continue;
        }

        const nestDepth = row.depth + 1;

        if (canPlace(node, row, originalParentId)) {
            return nestDepth;
        }

        return null;
    }

    return null;
}

export function constrainDepth(node, rest, insertIndex, desired, minDepth, maxDepth, originalParentId = null) {
    const lo = Math.max(1, minDepth);
    const hi = Math.max(lo, maxDepth);
    const ok = (d) => canPlace(node, parentAt(rest, insertIndex, d), originalParentId);

    if (desired >= lo && desired <= hi && ok(desired)) {
        return desired;
    }

    for (let r = 1; r <= (hi - lo); r++) {
        const down = desired - r;
        const up = desired + r;

        if (down >= lo && ok(down)) {
            return down;
        }

        if (up <= hi && ok(up)) {
            return up;
        }
    }

    return null;
}

/**
 * Innermost group the hovered row belongs to (or the group row itself).
 */
export function nestGroupFromHoverRow(rest, hoverRowIndex, draggedNode, originalParentId = null) {
    if (draggedNode.item_type === 'accessory' || hoverRowIndex == null || hoverRowIndex < 0) {
        return null;
    }

    const hoverRow = rest[hoverRowIndex];

    if (!hoverRow) {
        return null;
    }

    if (hoverRow.item_type === 'group' && hoverRow.id !== draggedNode.id) {
        return canPlace(draggedNode, hoverRow, originalParentId) ? hoverRow : null;
    }

    for (let i = hoverRowIndex; i >= 0; i--) {
        const row = rest[i];

        if (row.depth === hoverRow.depth - 1 && row.item_type === 'group' && row.id !== draggedNode.id) {
            return canPlace(draggedNode, row, originalParentId) ? row : null;
        }

        if (row.depth < hoverRow.depth - 1) {
            break;
        }
    }

    return null;
}

/**
 * Group whose open child region contains the insert index (after the group header).
 */
export function nestGroupContainingInsert(rest, insertIndex, draggedNode, originalParentId = null) {
    if (draggedNode.item_type === 'accessory') {
        return null;
    }

    for (let i = Math.min(insertIndex, rest.length) - 1; i >= 0; i--) {
        const row = rest[i];

        if (row.item_type !== 'group' || row.id === draggedNode.id) {
            continue;
        }

        if (isInsertInsideGroupChildRegion(rest, i, insertIndex)) {
            return canPlace(draggedNode, row, originalParentId) ? row : null;
        }
    }

    return null;
}

function resolveNestGroup(rest, draggedNode, rawInsertIndex, insertIndex, hoverRowIndex, originalParentId) {
    const hoverRow = hoverRowIndex != null && hoverRowIndex >= 0 ? rest[hoverRowIndex] : null;
    const insertGroup = nestGroupContainingInsert(rest, insertIndex, draggedNode, originalParentId);
    const hoverGroup = nestGroupFromHoverRow(rest, hoverRowIndex, draggedNode, originalParentId);

    if (insertGroup) {
        return insertGroup;
    }

    if (hoverGroup && hoverRow?.item_type === 'group') {
        const groupIdx = rest.findIndex((r) => r.id === hoverGroup.id);

        if (groupIdx !== -1 && rawInsertIndex > groupIdx) {
            return hoverGroup;
        }
    }

    if (hoverGroup && hoverRow?.item_type !== 'group') {
        const groupIdx = rest.findIndex((r) => r.id === hoverGroup.id);

        if (groupIdx !== -1 && isInsertInsideGroupChildRegion(rest, groupIdx, insertIndex)) {
            return hoverGroup;
        }
    }

    return null;
}

/**
 * Resolve insert index + depth from vertical drop target (group hover / child region).
 */
export function resolveDropTarget(input) {
    const {
        rest,
        draggedNode,
        insertIndex: rawInsertIndex,
        hoverRowIndex = null,
        clientX = null,
        startX = null,
        startDepth,
        originalParentId = null,
        indentPx = INDENT_PX,
    } = input;

    let insertIndex = Math.max(0, Math.min(rawInsertIndex, rest.length));

    const nestGroup = resolveNestGroup(
        rest,
        draggedNode,
        rawInsertIndex,
        insertIndex,
        hoverRowIndex,
        originalParentId,
    );

    if (nestGroup) {
        const groupIdx = rest.findIndex((r) => r.id === nestGroup.id);
        const groupEnd = blockEndIndex(rest, groupIdx);
        insertIndex = Math.max(groupIdx + 1, Math.min(insertIndex, groupEnd - 1));
    }

    const above = rest[insertIndex - 1] || null;
    const below = rest[insertIndex] || null;
    let maxDepth = above ? above.depth + 1 : 1;
    let minDepth = below !== null ? below.depth : 1;

    if (nestGroup) {
        const nestDepth = nestGroup.depth + 1;
        minDepth = Math.max(minDepth, nestDepth);
        maxDepth = Math.max(maxDepth, nestDepth);
    } else if (above?.item_type === 'group' && draggedNode.item_type !== 'accessory') {
        if (isInsertInsideGroupChildRegion(rest, rest.findIndex((r) => r.id === above.id), insertIndex)) {
            minDepth = Math.max(minDepth, above.depth + 1);
        }
    }

    const nestMin = minNestDepthForInsert(rest, insertIndex, draggedNode, originalParentId);

    if (nestMin != null) {
        minDepth = Math.max(minDepth, nestMin);
    }

    let depth;

    if (nestGroup) {
        depth = nestGroup.depth + 1;
    } else {
        const dx = clientX != null && startX != null ? clientX - startX : 0;
        depth = startDepth + Math.round(dx / indentPx);
        depth = Math.max(1, Math.min(depth, maxDepth));
        depth = Math.max(depth, Math.min(minDepth, maxDepth));
    }

    depth = constrainDepth(draggedNode, rest, insertIndex, depth, minDepth, maxDepth, originalParentId);

    const parent = parentAt(rest, insertIndex, depth);
    const valid = depth != null && canPlace(draggedNode, parent, originalParentId);

    return {
        insertIndex,
        targetDepth: depth,
        beforeId: rest[insertIndex]?.id ?? null,
        parent,
        valid,
        highlightGroupId: nestGroup?.id ?? null,
    };
}

/**
 * Apply an optimistic drag result to a row list (mirrors onDragUp in the editor).
 *
 * @param {array} rows
 * @param {{ block: array, blockIds: Set|array, targetIndex: number, targetDepth: number, startDepth: number, valid?: boolean, originalParentId?: number|null }} drag
 */
export function applyDragBlockMove(rows, drag) {
    const blockIds = drag.blockIds instanceof Set ? drag.blockIds : new Set(drag.blockIds);

    if (!drag.valid || drag.targetDepth == null) {
        return { rows, applied: false, reason: 'invalid_drop' };
    }

    const rest = rows.filter((r) => !blockIds.has(r.id));
    const at = Math.max(0, Math.min(drag.targetIndex, rest.length));
    const parent = parentAt(rest, at, drag.targetDepth);

    if (!canPlace(drag.block[0], parent, drag.originalParentId ?? null)) {
        return { rows, applied: false, reason: 'can_place_rejected' };
    }

    const delta = drag.targetDepth - drag.startDepth;
    const moved = drag.block.map((r) => ({ ...r, depth: Math.max(1, r.depth + delta) }));
    rest.splice(at, 0, ...moved);

    for (let i = 0; i < rest.length; i++) {
        const prevDepth = i === 0 ? 0 : rest[i - 1].depth;
        rest[i].depth = Math.max(1, Math.min(rest[i].depth, prevDepth + 1));
    }

    return { rows: rest, applied: true, nodes: rest.map((r) => ({ id: r.id, depth: r.depth })) };
}

export function buildPersistNodes(rows) {
    return rows.filter((r) => r.id > 0).map((r) => ({ id: r.id, depth: r.depth }));
}

export default {
    INDENT_PX,
    blockEndIndex,
    isInsertInsideGroupChildRegion,
    canPlace,
    parentAt,
    minNestDepthForInsert,
    constrainDepth,
    nestGroupFromHoverRow,
    nestGroupContainingInsert,
    resolveDropTarget,
    applyDragBlockMove,
    buildPersistNodes,
};
