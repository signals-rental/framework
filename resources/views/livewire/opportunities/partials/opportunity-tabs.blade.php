@php
    // Centralise tab-count loading so every opportunity tab shows correct, consistent
    // counts regardless of which relations the individual page component loaded.
    // `items()` is already active-version-scoped (opportunity-lifecycle.md §8.7).
    $opportunity->loadCount(['items', 'costs', 'attachments']);
@endphp
{{--
    The Versions tab (M8-5) and Availability/Shortage tab (M8-4) slot into this
    array later, between Items and Activities.
--}}
<x-signals.module-tabs
    :tabs="[
        ['name' => 'overview', 'label' => 'Overview', 'route' => route('opportunities.show', $opportunity)],
        ['name' => 'items', 'label' => 'Line Items', 'route' => route('opportunities.items', $opportunity), 'count' => $opportunity->items_count ?? 0],
        ['name' => 'costs', 'label' => 'Costs', 'route' => route('opportunities.costs', $opportunity), 'count' => $opportunity->costs_count ?? 0],
        ['name' => 'activities', 'label' => 'Activities', 'route' => route('opportunities.activities', $opportunity)],
        ['name' => 'custom-fields', 'label' => 'Custom Fields', 'route' => route('opportunities.custom-fields', $opportunity)],
        ['name' => 'files', 'label' => 'Files', 'route' => route('opportunities.files', $opportunity), 'count' => $opportunity->attachments_count ?? 0],
    ]"
    :active="$activeTab"
/>
