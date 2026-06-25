// Laravel Echo + Reverb client. Sets `window.Echo` so Livewire's `echo:` /
// `echo-private:` listeners (live availability on the opportunity Show + editor)
// receive Reverb broadcasts.
import './echo';

// Opportunity line-item editor two-tier product search (MiniSearch local tier +
// server fallback). Exposed on `window.signals` so Alpine/Livewire in the editor
// (M8-3b-ii) can build a controller from the embedded catalogue payload.
import createProductSearch, {
    buildIndex,
    merge,
    parseQuickAdd,
} from './opportunity-product-search';
import createOpportunityLineItemsEditor from './opportunity-line-items-editor';

window.signals = window.signals || {};
window.signals.productSearch = { createProductSearch, buildIndex, merge, parseQuickAdd };
window.signals.lineItemsEditor = createOpportunityLineItemsEditor;
