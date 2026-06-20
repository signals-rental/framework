import MiniSearch from 'minisearch';

/**
 * Two-tier product search controller for the opportunity line-item editor.
 *
 * Tier 1 (instant, local): an in-memory MiniSearch index over the catalogue
 * payload produced by `ProductSearchService::catalogueIndex()` (PHP). It searches
 * `name` + `sku` with prefix + light fuzzy matching and returns the picker fields
 * (rate, accessories) as stored fields — zero network latency.
 *
 * Tier 2 (server fallback): provided by the CONSUMER as an async callback (the
 * editor wires it to a `#[Renderless]` Livewire product-search method backed by
 * the Postgres `pg_trgm` index). This module does NOT hardcode a fetch URL — it
 * only calls the callback you pass and merges the results.
 *
 * The module is pure (no DOM, no globals beyond the export) so it can be unit
 * tested by constructing a controller with a fixed catalogue + a stub server
 * callback and asserting on `searchLocal()` / `merge()` outputs. No JS test infra
 * exists in this project yet; exercise it from the editor's Alpine/Livewire layer.
 *
 * @typedef {Object} ProductHit
 * @property {number} id
 * @property {string} name
 * @property {string|null} sku
 * @property {string|null} default_rate
 * @property {Array<Object>} accessories
 * @property {string|null} [availability]
 * @property {'local'|'server'} [source]   Which tier produced the hit
 * @property {boolean} [isNew]             True for a server-only hit absent from the local index
 */

const INDEX_FIELDS = ['name', 'sku'];
const STORE_FIELDS = ['id', 'name', 'sku', 'default_rate', 'accessories', 'availability'];

const DEFAULT_SEARCH_OPTIONS = {
    prefix: true,
    fuzzy: 0.2,
    boost: { name: 2 },
    combineWith: 'AND',
};

/**
 * Build a MiniSearch index from a catalogue payload.
 *
 * @param {Array<ProductHit>} catalogue
 * @returns {MiniSearch}
 */
export function buildIndex(catalogue = []) {
    const index = new MiniSearch({
        fields: INDEX_FIELDS,
        storeFields: STORE_FIELDS,
        idField: 'id',
        searchOptions: DEFAULT_SEARCH_OPTIONS,
        // Treat a null/absent sku as an empty string so indexing never throws.
        extractField: (document, fieldName) => {
            const value = document[fieldName];
            return value === null || value === undefined ? '' : String(value);
        },
    });

    index.addAll(catalogue);

    return index;
}

/**
 * Parse a quick-add term like "6 spiider" into { quantity, term }. A leading
 * integer becomes the quantity; the remainder is the search term. With no leading
 * integer the quantity defaults to 1 and the whole string is the term.
 *
 * @param {string} raw
 * @returns {{ quantity: number, term: string }}
 */
export function parseQuickAdd(raw) {
    const text = String(raw ?? '').trim();
    const match = text.match(/^(\d+)\s+(.*)$/);

    if (match) {
        return { quantity: Math.max(1, parseInt(match[1], 10)), term: match[2].trim() };
    }

    return { quantity: 1, term: text };
}

/**
 * Normalise a MiniSearch result row back into a plain ProductHit shaped like the
 * server payload, tagged `source: 'local'`.
 *
 * @param {Object} row
 * @returns {ProductHit}
 */
function normaliseLocal(row) {
    return {
        id: row.id,
        name: row.name,
        sku: row.sku ?? null,
        default_rate: row.default_rate ?? null,
        accessories: row.accessories ?? [],
        availability: row.availability ?? null,
        source: 'local',
        isNew: false,
    };
}

/**
 * Merge local + server result sets, de-duplicating by product id. Local hits keep
 * their order (best-match first); server-only hits are appended and flagged
 * `source: 'server'` + `isNew: true` so the editor can badge them as "new" (rows
 * the cached local index had not yet seen). Server hits also refresh the live
 * `availability` on any local row that overlaps (the server tier is store/date
 * aware; the local index is not).
 *
 * @param {Array<ProductHit>} localResults
 * @param {Array<ProductHit>} serverResults
 * @returns {Array<ProductHit>}
 */
export function merge(localResults = [], serverResults = []) {
    const byId = new Map();
    const ordered = [];

    for (const hit of localResults) {
        const normalised = { ...hit, source: hit.source ?? 'local', isNew: false };
        byId.set(normalised.id, normalised);
        ordered.push(normalised);
    }

    for (const hit of serverResults) {
        const existing = byId.get(hit.id);

        if (existing) {
            // Local already has this product — adopt the server's live availability
            // (store/date aware) without disturbing local ranking order.
            if (hit.availability !== undefined && hit.availability !== null) {
                existing.availability = hit.availability;
            }
            continue;
        }

        const serverHit = { ...hit, source: 'server', isNew: true };
        byId.set(serverHit.id, serverHit);
        ordered.push(serverHit);
    }

    return ordered;
}

/**
 * Create a two-tier product search controller.
 *
 * @param {Object} options
 * @param {Array<ProductHit>} [options.catalogue]  Catalogue payload for the local index
 * @param {(term: string) => Promise<Array<ProductHit>>} [options.serverSearch]
 *        Async server-tier callback (wired by the editor to the `#[Renderless]`
 *        Livewire method). Receives the search term, resolves to server hits.
 * @param {number} [options.limit]  Max local results to return
 * @returns {{
 *   searchLocal: (query: string) => Array<ProductHit>,
 *   searchServer: (query: string) => Promise<Array<ProductHit>>,
 *   search: (query: string) => Promise<Array<ProductHit>>,
 *   merge: typeof merge,
 *   parseQuickAdd: typeof parseQuickAdd,
 *   replaceCatalogue: (catalogue: Array<ProductHit>) => void,
 * }}
 */
export function createProductSearch({ catalogue = [], serverSearch = null, limit = 12 } = {}) {
    let index = buildIndex(catalogue);

    /**
     * Instant local search. Returns up to `limit` hits, best-match first.
     *
     * @param {string} query
     * @returns {Array<ProductHit>}
     */
    function searchLocal(query) {
        const term = String(query ?? '').trim();

        if (term === '') {
            return [];
        }

        return index
            .search(term)
            .slice(0, limit)
            .map(normaliseLocal);
    }

    /**
     * Server-tier search via the supplied callback. Resolves to [] when no
     * callback was provided or the term is empty.
     *
     * @param {string} query
     * @returns {Promise<Array<ProductHit>>}
     */
    async function searchServer(query) {
        const term = String(query ?? '').trim();

        if (term === '' || typeof serverSearch !== 'function') {
            return [];
        }

        const results = await serverSearch(term);

        return Array.isArray(results)
            ? results.map((hit) => ({ ...hit, source: 'server' }))
            : [];
    }

    /**
     * Combined two-tier search: instant local results merged with the server
     * fallback (server-only rows flagged `isNew`).
     *
     * @param {string} query
     * @returns {Promise<Array<ProductHit>>}
     */
    async function search(query) {
        const local = searchLocal(query);
        const server = await searchServer(query);

        return merge(local, server);
    }

    /**
     * Rebuild the local index from a fresh catalogue payload (e.g. after the
     * editor re-fetches the catalogue).
     *
     * @param {Array<ProductHit>} next
     */
    function replaceCatalogue(next = []) {
        index = buildIndex(next);
    }

    return { searchLocal, searchServer, search, merge, parseQuickAdd, replaceCatalogue };
}

export default createProductSearch;
