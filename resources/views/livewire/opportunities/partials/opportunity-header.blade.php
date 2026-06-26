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

    // B6: surface the active quote version number after the subject in the header
    // title (e.g. "Subject — v3"). A version number is ALWAYS shown — when the
    // opportunity has no explicit versioning it defaults to v1, derived from the
    // stored version_count (or 1 if that is empty).
    $activeVersionNumber = $opportunity->activeVersion?->version_number ?? max((int) $opportunity->version_count, 1);
    $headerTitle = $opportunity->subject.' — v'.$activeVersionNumber;
@endphp
<x-signals.page-header :title="$headerTitle">
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
        @if(! empty($opportunity->tag_list))
            @foreach($opportunity->tag_list as $tag)
                <span class="s-chip">{{ $tag }}</span>
            @endforeach
        @endif
    </x-slot:meta>
    <x-slot:actions>
        <a href="{{ route('opportunities.edit', $opportunity) }}" wire:navigate class="s-btn s-btn-sm s-btn-accent">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
            Edit
        </a>
        @if($showActions ?? false)
            {{-- $availableActions is provided by the component's with(); each verdict is
                 {key, label, allowed, reason, code}. Allowed items fire their wire method
                 directly (no confirm step), except convert_to_quotation / convert_to_order
                 which open the shared convert-opportunity modal. Denials render greyed-out
                 with the reason as a tooltip. change_status opens the change-status picker. --}}
            @php
                $actionWireMethods = [
                    'convert_to_quotation' => 'convertToQuotation',
                    'convert_to_order' => 'convertToOrder',
                    'reinstate' => 'reinstate',
                    'reopen' => 'reopen',
                    'revert_to_quotation' => 'revertToQuotation',
                    'revert_to_draft' => 'revertToDraft',
                    'unlock_locks' => 'unlockRates',
                    'clone' => 'cloneOpportunity',
                    'delete' => 'archive',
                ];
                $convertModalKeys = ['convert_to_quotation', 'convert_to_order'];
                $confirmModalKeys = ['reinstate', 'reopen', 'revert_to_quotation', 'revert_to_draft', 'unlock_locks', 'clone', 'delete'];
            @endphp
            <x-signals.split-button label="Actions" size="sm">
                @if($canChangeStatus ?? false)
                    <button
                        type="button"
                        x-on:click="open = false; $dispatch('open-modal', 'change-status')"
                        class="s-dropdown-item w-full text-left"
                        wire:key="action-change-status"
                    >
                        Change status…
                    </button>
                    <div class="s-dropdown-divider"></div>
                @endif
                @foreach(($availableActions ?? []) as $action)
                    @if($action['key'] === 'clone')
                        <div class="s-dropdown-divider"></div>
                    @endif
                    @if($action['allowed'] && isset($actionWireMethods[$action['key']]))
                        @if(in_array($action['key'], $convertModalKeys, true))
                            <button
                                type="button"
                                wire:click="openConvertModal('{{ $action['key'] }}')"
                                x-on:click="open = false"
                                wire:loading.attr="disabled"
                                wire:target="openConvertModal"
                                class="s-dropdown-item w-full text-left"
                                wire:key="action-{{ $action['key'] }}"
                            >
                                <span wire:loading.remove wire:target="openConvertModal">{{ $action['label'] }}</span>
                                <span wire:loading wire:target="openConvertModal" class="inline-flex items-center gap-1.5">
                                    <x-signals.spinner size="xs" /> {{ __('Working…') }}
                                </span>
                            </button>
                        @elseif(in_array($action['key'], $confirmModalKeys, true))
                            <button
                                type="button"
                                wire:click="openConfirmModal('{{ $action['key'] }}')"
                                x-on:click="open = false"
                                wire:loading.attr="disabled"
                                wire:target="openConfirmModal"
                                class="s-dropdown-item w-full text-left"
                                wire:key="action-{{ $action['key'] }}"
                            >
                                <span wire:loading.remove wire:target="openConfirmModal">{{ $action['label'] }}</span>
                                <span wire:loading wire:target="openConfirmModal" class="inline-flex items-center gap-1.5">
                                    <x-signals.spinner size="xs" /> {{ __('Working…') }}
                                </span>
                            </button>
                        @else
                            <button
                                type="button"
                                wire:click="{{ $actionWireMethods[$action['key']] }}"
                                x-on:click="open = false"
                                wire:loading.attr="disabled"
                                wire:target="{{ $actionWireMethods[$action['key']] }}"
                                class="s-dropdown-item w-full text-left"
                                wire:key="action-{{ $action['key'] }}"
                            >
                                <span wire:loading.remove wire:target="{{ $actionWireMethods[$action['key']] }}">{{ $action['label'] }}</span>
                                <span wire:loading wire:target="{{ $actionWireMethods[$action['key']] }}" class="inline-flex items-center gap-1.5">
                                    <x-signals.spinner size="xs" /> {{ __('Working…') }}
                                </span>
                            </button>
                        @endif
                    @else
                        <div
                            class="s-dropdown-item w-full"
                            title="{{ $action['reason'] }}"
                            style="opacity: .5; cursor: not-allowed;"
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
