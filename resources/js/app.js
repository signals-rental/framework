// Opportunity line-item editor two-tier product search (MiniSearch local tier +
// server fallback). Exposed on `window.signals` so Alpine/Livewire in the editor
// (M8-3b-ii) can build a controller from the embedded catalogue payload.
import createProductSearch, {
    buildIndex,
    merge,
    parseQuickAdd,
} from './opportunity-product-search';

window.signals = window.signals || {};
window.signals.productSearch = { createProductSearch, buildIndex, merge, parseQuickAdd };
