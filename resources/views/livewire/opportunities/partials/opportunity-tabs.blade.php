@php
    // Centralise tab-count loading so every opportunity tab shows correct, consistent
    // counts regardless of which relations the individual page component loaded.
    // `items()` is already active-version-scoped (opportunity-lifecycle.md §8.7).
    $opportunity->loadCount(['items', 'costs', 'attachments']);

    // The Shortages tab is gated on `shortages.view`; the live shortage count
    // (computed, never stored) drives the badge so the tab flags outstanding
    // shortfall at a glance. Detection only ever runs for users who can see it.
    $shortageTabCount = 0;
    if (\Illuminate\Support\Facades\Gate::allows('shortages.view')) {
        $shortageTabCount = app(\App\Services\Shortages\ShortageDetector::class)
            ->forOpportunity($opportunity)
            ->unresolved()
            ->count();
    }
@endphp
{{--
    The Overview tab embeds the live line-item editor (the standalone "Line Items"
    tab/route was removed in the show-page restructure). The "Versions & Timeline"
    tab (M8-5) renders the quote-versioning UI alongside the activity timeline (the
    standalone "Activities" tab/route was likewise removed); it is gated on
    `opportunities.view` (a version is an aspect of an opportunity, not a standalone
    resource) and carries the opportunity's `version_count` as its badge. The
    Shortages tab (M8-4c) is gated on `shortages.view` and only appears for users
    who hold it.
--}}
<x-signals.module-tabs
    :tabs="array_values(array_filter([
        ['name' => 'overview', 'label' => 'Overview', 'route' => route('opportunities.show', $opportunity)],
        ['name' => 'assets', 'label' => 'Assets', 'route' => route('opportunities.assets', $opportunity)],
        \Illuminate\Support\Facades\Gate::allows('opportunities.view')
            ? ['name' => 'versions', 'label' => 'Versions & Timeline', 'route' => route('opportunities.versions', $opportunity), 'count' => $opportunity->version_count ?? 0]
            : null,
        \Illuminate\Support\Facades\Gate::allows('shortages.view')
            ? ['name' => 'shortages', 'label' => 'Shortages', 'route' => route('opportunities.shortages', $opportunity), 'count' => $shortageTabCount]
            : null,
        ['name' => 'costs', 'label' => 'Costs', 'route' => route('opportunities.costs', $opportunity), 'count' => $opportunity->costs_count ?? 0],
        ['name' => 'custom-fields', 'label' => 'Custom Fields', 'route' => route('opportunities.custom-fields', $opportunity)],
        ['name' => 'files', 'label' => 'Files', 'route' => route('opportunities.files', $opportunity), 'count' => $opportunity->attachments_count ?? 0],
    ]))"
    :active="$activeTab"
/>
