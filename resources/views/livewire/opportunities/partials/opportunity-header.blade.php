{{--
    Shared opportunity Show-page header (mirrors products/partials/product-header).

    Every tab page @include's this, passing `subpage` for the breadcrumb. The full
    Actions split-button (state-transition menu) is only rendered on the OVERVIEW
    page — that page owns the transition wire-methods (convertToQuotation,
    convertToOrder, reinstate, revertToQuotation, cloneOpportunity, archive,
    unlockRates) and computes the permitted-action verdicts in its with(). Tab pages
    omit `showActions`, so they show only the Edit button — avoiding duplicating the
    transition methods across every tab component.
--}}
@php
    $stateBadgeClass = match($opportunity->state) {
        \App\Enums\OpportunityState::Draft => 's-badge-zinc',
        \App\Enums\OpportunityState::Quotation => 's-badge-blue',
        \App\Enums\OpportunityState::Order => 's-badge-green',
        default => 's-badge-zinc',
    };
    $status = $opportunity->statusEnum();
@endphp
<x-signals.page-header :title="$opportunity->subject">
    <x-slot:icon>
        <x-signals.entity-icon :model="$opportunity" :size="44" />
    </x-slot:icon>
    <x-slot:breadcrumbs>
        <a href="{{ route('opportunities.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Opportunities</a>
        <span class="mx-1 text-[var(--text-muted)]">/</span>
        @if(isset($subpage))
            <a href="{{ route('opportunities.show', $opportunity) }}" wire:navigate class="text-[var(--link)] hover:underline">{{ $opportunity->subject }}</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>{{ $subpage }}</span>
        @else
            <span>{{ $opportunity->subject }}</span>
        @endif
    </x-slot:breadcrumbs>
    <x-slot:meta>
        <span class="s-badge {{ $stateBadgeClass }}">{{ $opportunity->state->label() }}</span>
        <span class="s-status {{ $status->isClosed() ? 's-status-zinc' : 's-status-green' }}"><span class="s-status-dot"></span> {{ $status->label() }}</span>
        @if($opportunity->trashed())
            <span class="s-badge s-badge-red"><span class="s-badge-dot"></span> Archived</span>
        @endif
    </x-slot:meta>
    <x-slot:actions>
        <a href="{{ route('opportunities.edit', $opportunity) }}" wire:navigate class="s-btn s-btn-sm s-btn-accent">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
            Edit
        </a>
        @if($showActions ?? false)
            {{-- $availableActions is provided by the overview component's with(); each
                 verdict is {key, label, allowed, reason, code}. Allowed actions render
                 as wire:click buttons; denials render greyed-out with the reason as a
                 tooltip. Map: convert_to_quotation→convertToQuotation,
                 convert_to_order→convertToOrder, reinstate→reinstate,
                 revert_to_quotation→revertToQuotation, clone→cloneOpportunity,
                 unlock_locks→unlockRates, delete→archive. --}}
            @php
                $actionMethods = [
                    'convert_to_quotation' => 'convertToQuotation',
                    'convert_to_order' => 'convertToOrder',
                    'reinstate' => 'reinstate',
                    'revert_to_quotation' => 'revertToQuotation',
                    'unlock_locks' => 'unlockRates',
                    'clone' => 'cloneOpportunity',
                    'delete' => 'archive',
                ];
            @endphp
            <x-signals.split-button label="Actions" size="sm">
                @foreach(($availableActions ?? []) as $action)
                    @php $method = $actionMethods[$action['key']] ?? null; @endphp
                    @if($action['key'] === 'clone')
                        <div class="s-dropdown-divider"></div>
                    @endif
                    @if($action['allowed'] && $method)
                        <button
                            type="button"
                            wire:click="{{ $method }}"
                            x-on:click="open = false"
                            class="s-dropdown-item"
                            style="width: 100%; text-align: left;"
                            wire:key="action-{{ $action['key'] }}"
                        >
                            {{ $action['label'] }}
                        </button>
                    @else
                        <div
                            class="s-dropdown-item"
                            title="{{ $action['reason'] }}"
                            style="opacity: .5; cursor: not-allowed; width: 100%;"
                            wire:key="action-{{ $action['key'] }}"
                        >
                            {{ $action['label'] }}
                        </div>
                    @endif
                @endforeach
            </x-signals.split-button>
        @endif
    </x-slot:actions>
</x-signals.page-header>
