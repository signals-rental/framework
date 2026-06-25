<?php

use App\Livewire\Concerns\HasOpportunityActions;
use App\Models\Opportunity;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

/**
 * Opportunity Show (overview) page (M8-2).
 *
 * Renders the record overview plus the Actions split-button. The permitted
 * state-transition actions + the confirm/change-status modal plumbing are shared
 * with every opportunity tab page via {@see HasOpportunityActions}; the verdicts
 * are computed exactly the way the API's `available_actions` endpoint does and the
 * transition wire-methods call the SAME action classes the API calls.
 */
new #[Layout('components.layouts.app')] class extends Component
{
    use HasOpportunityActions;

    public Opportunity $opportunity;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');

        $this->opportunity = $opportunity->load(['member', 'venue', 'store', 'owner', 'activeVersion']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->opportunity->subject);
    }

    /**
     * Live availability refresh requires the Reverb/Echo client bundle to be
     * configured (not yet wired in resources/js/app.js); the server-side broadcast
     * (App\Events\Availability\OpportunityAvailabilityChanged, broadcastAs
     * `availability.changed` on `availability.opportunity.{id}`) and the channel
     * auth (routes/channels.php) already exist. Until the client Echo instance is
     * registered this listener is inert but correct — the dynamic `{opportunity.id}`
     * channel segment and the dot-prefixed custom broadcast name follow the Livewire
     * 4 / Laravel Echo convention.
     */
    #[On('echo-private:availability.opportunity.{opportunity.id},.availability.changed')]
    public function onAvailabilityChanged(): void
    {
        // Re-read the projection; with() re-evaluates on the subsequent re-render so
        // totals + shortage flags pick up the recalculated picture.
        $this->opportunity->refresh();
    }

    /**
     * Refresh the projection when the nested line-item editor reports a
     * totals-affecting change (#10: qty / rate override / discount). The editor
     * recomputes its own footer + line totals, but the sidebar Totals panel + header
     * live on THIS component, so they would otherwise stay stale.
     */
    #[On('opportunity-totals-updated')]
    public function onTotalsUpdated(): void
    {
        $this->opportunity->refresh();
    }

    /**
     * Merge the shared Actions data ({@see HasOpportunityActions}) into the view.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return $this->opportunityActionData();
    }
}; ?>

<section class="w-full">
    @include('livewire.opportunities.partials.opportunity-header', ['opportunity' => $opportunity, 'showActions' => true, 'canChangeStatus' => $canChangeStatus])

    @include('livewire.opportunities.partials.opportunity-tabs', ['opportunity' => $opportunity, 'activeTab' => 'overview'])

    {{-- Editor Lab launcher (THROWAWAY) — a small, additive dropdown linking the
         four prototype line-item editors for this opportunity. Purely for
         comparing DnD approaches; the live Overview editor below is untouched. --}}
    <div class="px-6 pt-4 max-md:px-5 max-sm:px-3">
        <div x-data="{ open: false }" class="relative inline-block">
            <button type="button" x-on:click="open = !open" class="s-btn s-btn-sm s-btn-outline">
                <span class="s-badge s-badge-amber"><span class="s-badge-dot"></span> Lab</span>
                Editor Lab
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div x-show="open" x-on:click.outside="open = false" x-cloak
                 class="s-dropdown absolute z-20 mt-1 min-w-56" style="display:none;">
                {{-- Full page load (NO wire:navigate): the prototype loads Dexie + its grid CSS via @push('scripts'), which Livewire's SPA navigation does not reliably re-run. Kept as the reference for the production editor rebuild. --}}
                <a href="{{ route('opportunities.editor-lab.local-first', $opportunity) }}" class="s-dropdown-item">Local-First (Dexie) — reference</a>
            </div>
        </div>
    </div>

    {{-- Archived (soft-deleted) opportunities are viewable but read-only. Surface a
         clear banner + a Restore action; the transition actions/status picker are
         suppressed in with() while archived. --}}
    @if($isArchived)
        <div class="px-6 pt-4 max-md:px-5 max-sm:px-3">
            <x-signals.alert type="warning">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span>{{ __('This opportunity is archived. It is read-only until restored.') }}</span>
                    @if($canRestore)
                        <button type="button"
                                wire:click="restore"
                                wire:confirm="{{ __('Restore this opportunity? It will become active and editable again.') }}"
                                class="s-btn s-btn-sm s-btn-outline-green shrink-0">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                            {{ __('Restore') }}
                        </button>
                    @endif
                </div>
            </x-signals.alert>
        </div>
    @endif

    @if(session('error'))
        <div class="px-6 pt-4 max-md:px-5 max-sm:px-3">
            <x-signals.alert type="danger">{{ session('error') }}</x-signals.alert>
        </div>
    @endif

    @php
        $formatter = app(\App\Support\Formatter::class);
        $stateBadgeClass = match($opportunity->state) {
            \App\Enums\OpportunityState::Draft => 's-badge-zinc',
            \App\Enums\OpportunityState::Quotation => 's-badge-blue',
            \App\Enums\OpportunityState::Order => 's-badge-green',
            default => 's-badge-zinc',
        };
        $status = $opportunity->statusEnum();

        // Active quote version (if the opportunity has been split into versions).
        // Surfaced under "Number" in Key Attributes as `v{n}` + its timestamp.
        $activeVersion = $opportunity->activeVersion;
        $versionRow = $activeVersion
            ? ['label' => 'Version', 'value' => 'v'.$activeVersion->version_number.($activeVersion->created_at ? ' · '.$activeVersion->created_at->format('d M Y') : '')]
            : null;
    @endphp

    {{-- 2-column layout: ~25% Key-Attributes sidebar + ~75% live line-item editor.
         Uses standard grid utilities (lg:grid-cols-4 + col-span 1/3) which are
         guaranteed to be present in the compiled Tailwind build, rather than an
         arbitrary grid-template that may be purged. --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 px-6 py-4 max-md:px-5 max-sm:px-3">

        {{-- ============================================================ --}}
        {{-- LEFT SIDEBAR — totals, key attributes, dates, member         --}}
        {{-- ============================================================ --}}
        <div class="min-w-0 space-y-6 lg:col-span-1">
            {{-- Compact totals stacked ABOVE Key Attributes (the live ex-tax breakdown
                 also renders in the editor footer; this keeps the headline figures
                 visible alongside attributes). --}}
            <x-signals.panel title="Totals">
                <x-signals.data-list layout="vertical" :items="[
                    ['label' => 'Charge Total', 'value' => $formatter->money($opportunity->charge_total ?? 0), 'mono' => true],
                    ['label' => 'Excl. Tax', 'value' => $formatter->money($opportunity->charge_excluding_tax_total ?? 0), 'mono' => true],
                    ['label' => 'Tax', 'value' => $formatter->money($opportunity->tax_total ?? 0), 'mono' => true],
                ]" />
            </x-signals.panel>

            <x-signals.panel title="Dates">
                <x-signals.data-list layout="vertical" :items="[
                    ['label' => 'Starts', 'value' => $opportunity->starts_at ? $formatter->dateTime($opportunity->starts_at) : '—'],
                    ['label' => 'Ends', 'value' => $opportunity->ends_at ? $formatter->dateTime($opportunity->ends_at) : '—'],
                    ['label' => 'Charge Starts', 'value' => $opportunity->charge_starts_at ? $formatter->dateTime($opportunity->charge_starts_at) : '—'],
                    ['label' => 'Charge Ends', 'value' => $opportunity->charge_ends_at ? $formatter->dateTime($opportunity->charge_ends_at) : '—'],
                ]" />
            </x-signals.panel>

            <x-signals.panel title="Key Attributes">
                <x-signals.data-list layout="vertical" :items="array_filter([
                    $opportunity->number ? ['label' => 'Number', 'value' => $opportunity->number, 'mono' => true] : null,
                    $versionRow,
                    $opportunity->reference ? ['label' => 'Reference', 'value' => $opportunity->reference] : null,
                    ['label' => 'State', 'value' => $opportunity->state->label(), 'badge' => $stateBadgeClass],
                    ['label' => 'Status', 'value' => $status->label()],
                    ['label' => 'Currency', 'value' => $opportunity->currency_code ?? '—'],
                    ['label' => 'Created', 'value' => $opportunity->created_at?->format('d M Y') ?? '—'],
                    ['label' => 'Updated', 'value' => $opportunity->updated_at?->format('d M Y') ?? '—'],
                ])" />
            </x-signals.panel>

            <x-signals.panel title="Member & Store">
                <x-signals.data-list layout="vertical" :items="array_filter([
                    $opportunity->member
                        ? ['label' => 'Member', 'value' => $opportunity->member->name, 'href' => route('members.show', $opportunity->member)]
                        : ['label' => 'Member', 'value' => '—'],
                    ['label' => 'Venue', 'value' => $opportunity->venue?->name ?? '—'],
                    ['label' => 'Store', 'value' => $opportunity->store?->name ?? '—'],
                    ['label' => 'Owner', 'value' => $opportunity->owner?->name ?? '—'],
                ])" />
            </x-signals.panel>
        </div>

        {{-- ============================================================ --}}
        {{-- MAIN — the live line-item editor (nested Volt component) --}}
        {{-- ============================================================ --}}
        <div class="min-w-0 lg:col-span-3">
            <livewire:opportunities.line-items :opportunity="$opportunity" :key="'opp-line-items-'.$opportunity->id" />
        </div>
    </div>

    @include('livewire.opportunities.partials.opportunity-action-modals')
</section>
