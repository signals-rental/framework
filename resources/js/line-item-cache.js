/**
 * IndexedDB-safe serialisation for the local-first line-item editor cache.
 * Strips non-cloneable values (undefined, functions) and dedupes by row id.
 */

export function serializeRowForCache(row, oppId) {
    const id = Number(row?.id);

    if (!Number.isFinite(id) || id === 0) {
        return null;
    }

    /** @type {Record<string, unknown>} */
    const payload = {
        opp: Number(oppId),
        id,
        item_type: row.item_type ?? null,
        path: row.path ?? null,
        depth: Number(row.depth) || 1,
        parent_path: row.parent_path ?? null,
        parent_group_id: row.parent_group_id ?? null,
        name: row.name ?? null,
        description: row.description ?? null,
        notes: row.notes ?? null,
        quantity: row.quantity ?? null,
        quantity_raw: row.quantity_raw ?? null,
        days: row.days ?? null,
        unit_price: row.unit_price ?? null,
        unit_price_display: row.unit_price_display ?? null,
        unit_price_raw: row.unit_price_raw ?? null,
        discount_percent: row.discount_percent ?? null,
        charge_total: row.charge_total ?? null,
        charge_total_display: row.charge_total_display ?? null,
        is_optional: !!row.is_optional,
        is_collapsed: !!row.is_collapsed,
        has_children: !!row.has_children,
        product_id: row.product_id ?? null,
        product_url: row.product_url ?? null,
        revenue_group_id: row.revenue_group_id ?? null,
        starts_at: row.starts_at ?? null,
        ends_at: row.ends_at ?? null,
        charge_period_label: row.charge_period_label ?? null,
        has_duplicates: !!row.has_duplicates,
        type_label: row.type_label ?? null,
        status_label: row.status_label ?? null,
        availability_status: row.availability_status ?? null,
        has_shortage: !!row.has_shortage,
        availability_url: row.availability_url ?? null,
    };

    if (row.charge_breakdown && typeof row.charge_breakdown === 'object') {
        payload.charge_breakdown = row.charge_breakdown;
    }

    return JSON.parse(JSON.stringify(payload));
}

/**
 * @param {array} rows
 * @param {number|string} oppId
 * @returns {array}
 */
export function serializeRowsForCache(rows, oppId) {
    /** @type {array} */
    const out = [];
    const seen = new Set();

    for (const row of rows) {
        const cached = serializeRowForCache(row, oppId);

        if (!cached || seen.has(cached.id)) {
            continue;
        }

        seen.add(cached.id);
        out.push(cached);
    }

    return out;
}

export default {
    serializeRowForCache,
    serializeRowsForCache,
};
