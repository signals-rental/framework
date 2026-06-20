<?php

use App\Actions\Shortages\AcknowledgeOpportunityShortages;
use App\Actions\Shortages\ApplyShortageResolution;
use App\Actions\Shortages\CancelShortageResolution;
use App\Actions\Shortages\ConfirmShortageResolution;
use App\Actions\Shortages\FailShortageResolution;
use App\Actions\Shortages\FulfillShortageResolution;
use App\Actions\Shortages\StartShortageResolution;
use App\Data\Shortages\AcknowledgeShortageData;
use App\Data\Shortages\ApplyResolutionData;
use App\Data\Shortages\TransitionShortageResolutionData;
use App\Enums\ShortageResolutionStatus;
use App\Models\Opportunity;
use App\Models\ShortageResolution;
use App\Services\Shortages\ShortageConfirmationGate;
use App\Services\Shortages\ShortageDetector;
use App\Services\Shortages\ShortageResolverRegistry;
use App\ValueObjects\Shortage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

/**
 * Opportunity Shortages tab (M8-4c) — the shortage panel + resolver UI
 * (shortage-resolution-sub-hires.md §2, §3, §7, §8).
 *
 * Reads compute shortages live from the availability engine via the
 * {@see ShortageDetector} (shortages are never persisted), enumerate the
 * applicable resolvers per short line through the {@see ShortageResolverRegistry},
 * surface the {@see ShortageConfirmationGate} pre-check (the same Block/Warn/Allow
 * decision the convert-to-order action enforces) and the store dispatch policy, and
 * list + transition the persisted {@see ShortageResolution} records through the
 * §8.3 lifecycle. Every mutation calls the SAME action classes the API controller
 * uses (each authorises internally) — this component never self-HTTPs.
 *
 * Reverb-live: subscribes to `availability.opportunity.{id}` so the panel refreshes
 * when shortages change (Echo client bundle wired in M8-4a).
 */
new #[Layout('components.layouts.app')] class extends Component
{
    public Opportunity $opportunity;

    /** Whether the actor may apply/transition/acknowledge (vs read-only view). */
    public bool $canResolve = false;

    /** The resolver/option the user is composing in the apply modal. */
    public ?int $applyItemId = null;

    public ?string $applyResolverKey = null;

    public int $applyOptionIndex = 0;

    public ?string $applyNotes = null;

    /** The resolution + target status the reason modal is collecting a reason for. */
    public ?int $reasonResolutionId = null;

    public ?string $reasonTransition = null;

    public ?string $transitionReason = null;

    /** Notes captured when acknowledging shortages ahead of conversion. */
    public ?string $acknowledgeNotes = null;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('shortages.view');

        $this->opportunity = $opportunity->load('store');
        $this->canResolve = Gate::allows('shortages.resolve');
    }

    public function rendering(View $view): void
    {
        $view->title($this->opportunity->subject.' — Shortages');
    }

    /**
     * Live refresh when the opportunity's availability changes (a booking, return,
     * stock movement, or resolution elsewhere). The broadcast is a light signal;
     * the with() computed values re-read the engine on the subsequent render so the
     * shortage list, resolver options, gate decision, and resolutions all reflect
     * the recalculated picture. Mirrors the M8-2 / M8-4b `#[On('echo-private:...')]`
     * convention — dynamic `{opportunity.id}` segment + dot-prefixed broadcast name.
     */
    #[On('echo-private:availability.opportunity.{opportunity.id},.availability.changed')]
    public function onAvailabilityChanged(): void
    {
        $this->opportunity->refresh();
    }

    /**
     * Open the apply modal for a resolver option on a short line item.
     */
    public function selectResolution(int $itemId, string $resolverKey, int $optionIndex): void
    {
        $this->applyItemId = $itemId;
        $this->applyResolverKey = $resolverKey;
        $this->applyOptionIndex = $optionIndex;
        $this->applyNotes = null;
    }

    /**
     * Apply the chosen resolver option to the line item's current shortage. The
     * shortage is recomputed fresh inside the action; on success the panel refreshes
     * and the active-resolutions list picks up the new record.
     */
    public function applyResolution(): void
    {
        if ($this->applyItemId === null || $this->applyResolverKey === null) {
            return;
        }

        try {
            (new ApplyShortageResolution(
                app(ShortageDetector::class),
                app(ShortageResolverRegistry::class),
            ))(ApplyResolutionData::from([
                'opportunity_item_id' => $this->applyItemId,
                'resolver_key' => $this->applyResolverKey,
                'option_index' => $this->applyOptionIndex,
                'notes' => $this->applyNotes,
            ]));

            session()->flash('shortage_status', 'Resolution applied.');
            $this->resetApplyState();
            $this->dispatch('shortage-modal-close', name: 'apply-resolution');
        } catch (AuthorizationException) {
            session()->flash('error', 'You do not have permission to resolve shortages.');
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first() ?? 'Unable to apply this resolution.');
        }

        $this->opportunity->refresh();
    }

    public function confirmResolution(int $resolutionId): void
    {
        $this->runTransition($resolutionId, fn (ShortageResolution $resolution) => (new ConfirmShortageResolution(app(\App\Services\Shortages\ShortageEventRecorder::class)))($resolution));
    }

    public function startResolution(int $resolutionId): void
    {
        $this->runTransition($resolutionId, fn (ShortageResolution $resolution) => (new StartShortageResolution(app(\App\Services\Shortages\ShortageEventRecorder::class)))($resolution));
    }

    public function fulfillResolution(int $resolutionId): void
    {
        $this->runTransition($resolutionId, fn (ShortageResolution $resolution) => (new FulfillShortageResolution(app(\App\Services\Shortages\ShortageEventRecorder::class)))($resolution));
    }

    /**
     * Open the reason modal for a cancel/fail transition (both carry an optional
     * reason persisted to `cancellation_reason`).
     */
    public function promptReason(int $resolutionId, string $transition): void
    {
        $this->reasonResolutionId = $resolutionId;
        $this->reasonTransition = $transition;
        $this->transitionReason = null;
    }

    /**
     * Apply the reason-carrying cancel/fail transition collected in the modal.
     */
    public function submitReasonTransition(): void
    {
        if ($this->reasonResolutionId === null || $this->reasonTransition === null) {
            return;
        }

        $reason = $this->transitionReason;
        $transition = $this->reasonTransition;

        $this->runTransition($this->reasonResolutionId, function (ShortageResolution $resolution) use ($transition, $reason) {
            $payload = TransitionShortageResolutionData::from(['reason' => $reason]);

            return $transition === 'fail'
                ? (new FailShortageResolution(app(\App\Services\Shortages\ShortageEventRecorder::class)))($resolution, $payload)
                : (new CancelShortageResolution(app(\App\Services\Shortages\ShortageEventRecorder::class)))($resolution, $payload);
        });

        $this->reasonResolutionId = null;
        $this->reasonTransition = null;
        $this->transitionReason = null;
        $this->dispatch('shortage-modal-close', name: 'transition-reason');
    }

    /**
     * Explicitly acknowledge the opportunity's shortages ahead of conversion,
     * recording the gate acknowledgement with a frozen snapshot (§7.3). Surfaced
     * when the gate decision is Warn (acknowledgement required) — it lets the user
     * record awareness on this tab before clicking Convert to Order on the overview.
     */
    public function acknowledgeShortages(): void
    {
        try {
            (new AcknowledgeOpportunityShortages(app(ShortageConfirmationGate::class)))(
                $this->opportunity,
                AcknowledgeShortageData::from(['notes' => $this->acknowledgeNotes]),
            );

            session()->flash('shortage_status', 'Shortages acknowledged.');
            $this->acknowledgeNotes = null;
        } catch (AuthorizationException) {
            session()->flash('error', 'You do not have permission to acknowledge shortages.');
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first() ?? 'Unable to acknowledge shortages.');
        }

        $this->opportunity->refresh();
    }

    /**
     * Run a resolution status transition, catching the auth/422 failures the action
     * classes raise (an illegal §8.3 move is a 422) and flashing the first message.
     *
     * @param  \Closure(ShortageResolution): mixed  $action
     */
    protected function runTransition(int $resolutionId, \Closure $action): void
    {
        try {
            $resolution = ShortageResolution::query()
                ->forOpportunity($this->opportunity->id)
                ->findOrFail($resolutionId);

            $action($resolution);
            session()->flash('shortage_status', 'Resolution updated.');
        } catch (AuthorizationException) {
            session()->flash('error', 'You do not have permission to update this resolution.');
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first() ?? 'This transition is not permitted.');
        }

        $this->opportunity->refresh();
    }

    protected function resetApplyState(): void
    {
        $this->applyItemId = null;
        $this->applyResolverKey = null;
        $this->applyOptionIndex = 0;
        $this->applyNotes = null;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $shortages = app(ShortageDetector::class)->forOpportunity($this->opportunity);
        $registry = app(ShortageResolverRegistry::class);

        /** @var list<array<string, mixed>> $rows */
        $rows = $shortages
            ->map(function (Shortage $shortage) use ($registry): array {
                return [
                    'shortage' => $shortage,
                    'resolvers' => array_map(
                        static fn ($resolver): array => [
                            'key' => $resolver->key(),
                            'name' => $resolver->name(),
                            'auto_executable' => $resolver->isAutoExecutable(),
                            'options' => array_map(
                                static fn ($option): array => $option->toArray(),
                                $resolver->getOptions($shortage),
                            ),
                        ],
                        $registry->applicableTo($shortage),
                    ),
                ];
            })
            ->all();

        return [
            'shortageRows' => $rows,
            'gate' => app(ShortageConfirmationGate::class)->evaluate($this->opportunity),
            'dispatchPolicy' => $this->opportunity->store?->dispatchPolicy()
                ?? \App\Enums\ShortageDispatchPolicy::default(),
            'resolutions' => $this->loadResolutions(),
            'appliedOption' => $this->appliedOption($rows),
        ];
    }

    /**
     * The opportunity's persisted resolutions, newest first, each annotated with the
     * legal §8.3 transitions for the action buttons.
     *
     * @return Collection<int, ShortageResolution>
     */
    protected function loadResolutions(): Collection
    {
        return ShortageResolution::query()
            ->forOpportunity($this->opportunity->id)
            ->with('items')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * The option currently selected in the apply modal (for the confirm summary), or
     * null when the modal is closed / the selection is stale.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    protected function appliedOption(array $rows): ?array
    {
        if ($this->applyItemId === null || $this->applyResolverKey === null) {
            return null;
        }

        foreach ($rows as $row) {
            /** @var Shortage $shortage */
            $shortage = $row['shortage'];

            if ($shortage->opportunityItemId !== $this->applyItemId) {
                continue;
            }

            foreach ($row['resolvers'] as $resolver) {
                if ($resolver['key'] !== $this->applyResolverKey) {
                    continue;
                }

                return $resolver['options'][$this->applyOptionIndex] ?? null;
            }
        }

        return null;
    }

    /**
     * Whether $resolution may legally transition to $target (§8.3), driving the
     * enabled/disabled state of the lifecycle buttons.
     */
    public function canTransition(ShortageResolution $resolution, string $target): bool
    {
        $targetStatus = ShortageResolutionStatus::tryFrom($target);

        return $targetStatus !== null && $resolution->status->canTransitionTo($targetStatus);
    }

    /**
     * A human window label for a shortage's effective period.
     */
    public function windowLabel(Shortage $shortage): string
    {
        $sentinel = Carbon::parse(\App\Models\Demand::SENTINEL_DATE);
        $start = $shortage->startsAt->format('d M Y');

        if ($shortage->endsAt->equalTo($sentinel)) {
            return $start.' → open-ended';
        }

        return $start.' → '.$shortage->endsAt->format('d M Y');
    }
}; ?>

<section class="w-full">
    @include('livewire.opportunities.partials.opportunity-header', ['opportunity' => $opportunity, 'subpage' => 'Shortages'])
    @include('livewire.opportunities.partials.opportunity-tabs', ['opportunity' => $opportunity, 'activeTab' => 'shortages'])

    @php
        $formatter = app(\App\Support\Formatter::class);
        $gateBadge = match($gate->decision) {
            \App\Enums\ShortagePolicy::Block => 's-badge-red',
            \App\Enums\ShortagePolicy::Warn => 's-badge-amber',
            \App\Enums\ShortagePolicy::Allow => 's-badge-green',
        };
    @endphp

    <div class="flex-1 space-y-6 px-6 py-4 max-md:px-5 max-sm:px-3">

        @if(session('error'))
            <x-signals.alert type="danger">{{ session('error') }}</x-signals.alert>
        @endif
        @if(session('shortage_status'))
            <x-signals.alert type="success">{{ session('shortage_status') }}</x-signals.alert>
        @endif

        {{-- ============================================================ --}}
        {{--  CONVERT-TO-ORDER GATE PRE-CHECK (§7) + dispatch policy       --}}
        {{-- ============================================================ --}}
        <x-signals.panel title="Conversion & Dispatch Checks">
            <div class="grid grid-cols-2 gap-4 max-md:grid-cols-1">
                <div class="rounded-md border border-[var(--border)] p-4">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">Convert to Order gate</span>
                        <span class="s-badge {{ $gateBadge }}">{{ $gate->decision->label() }}</span>
                    </div>
                    <p class="text-sm text-[var(--text)]">
                        @switch($gate->decision)
                            @case(\App\Enums\ShortagePolicy::Block)
                                {{ __('Conversion is blocked while unresolved shortages remain. Resolve them below (or obtain the permission to ignore shortages) before converting to an order.') }}
                                @break
                            @case(\App\Enums\ShortagePolicy::Warn)
                                {{ __('Conversion will proceed but requires an acknowledgement of the outstanding shortages.') }}
                                @break
                            @default
                                {{ __('Shortages do not block conversion for this store; they remain visible below.') }}
                        @endswitch
                    </p>
                    <p class="mt-2 text-[11px] text-[var(--text-muted)]">
                        {{ __('Store policy') }}: <span class="font-semibold">{{ $gate->storePolicy->label() }}</span>
                        @if($gate->permissionUsed)
                            <span class="s-badge s-badge-zinc s-badge-outline ml-1">{{ __('relaxed by your permission') }}</span>
                        @endif
                    </p>

                    @if($gate->acknowledgementRequired() && $canResolve)
                        <div class="mt-3 border-t border-[var(--border)] pt-3">
                            <label for="ack-notes" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Acknowledgement notes (optional)') }}</label>
                            <textarea id="ack-notes" wire:model="acknowledgeNotes" rows="2" class="s-input w-full" placeholder="{{ __('Recorded against the acknowledgement') }}"></textarea>
                            <button type="button" wire:click="acknowledgeShortages" class="s-btn s-btn-sm s-btn-warning mt-2">
                                <flux:icon.check-badge class="!size-3.5" /> {{ __('Acknowledge shortages') }}
                            </button>
                        </div>
                    @endif
                </div>

                <div class="rounded-md border border-[var(--border)] p-4">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Dispatch policy') }}</span>
                        <span class="s-badge {{ $dispatchPolicy === \App\Enums\ShortageDispatchPolicy::Block ? 's-badge-red' : ($dispatchPolicy === \App\Enums\ShortageDispatchPolicy::WarnPartial ? 's-badge-amber' : 's-badge-green') }}">
                            {{ $dispatchPolicy->label() }}
                        </span>
                    </div>
                    <p class="text-sm text-[var(--text)]">
                        @switch($dispatchPolicy)
                            @case(\App\Enums\ShortageDispatchPolicy::Block)
                                {{ __('Dispatch cannot begin while any line is short. Short lines must be resolved before booking out.') }}
                                @break
                            @case(\App\Enums\ShortageDispatchPolicy::WarnPartial)
                                {{ __('Available items dispatch as a partial shipment; short items are held back with a warning.') }}
                                @break
                            @default
                                {{ __('Available items dispatch silently; short items are held back without a warning.') }}
                        @endswitch
                    </p>
                </div>
            </div>
        </x-signals.panel>

        {{-- ============================================================ --}}
        {{--  SHORTAGE LIST + RESOLVER UI                                  --}}
        {{-- ============================================================ --}}
        <x-signals.panel title="Detected Shortages">
            @if(empty($shortageRows))
                <x-signals.empty
                    title="{{ __('No shortages') }}"
                    description="{{ __('Every line item on this opportunity is fully serviceable for its booking window.') }}">
                    <x-slot:icon><flux:icon.check-circle class="!size-7" /></x-slot:icon>
                </x-signals.empty>
            @else
                <div class="space-y-4">
                    @foreach($shortageRows as $row)
                        @php
                            /** @var \App\ValueObjects\Shortage $shortage */
                            $shortage = $row['shortage'];
                            $remaining = $shortage->remainingShortfall();
                            $severityBadge = $shortage->isCritical ? 's-badge-red' : 's-badge-amber';
                        @endphp
                        <div wire:key="shortage-{{ $shortage->opportunityItemId }}" class="rounded-md border border-[var(--border)] p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-[var(--text)]">{{ $shortage->productName }}</span>
                                        <span class="s-badge {{ $severityBadge }} s-badge-dot">
                                            {{ $shortage->isCritical ? __('Critical') : __('Shortfall') }}
                                        </span>
                                        <span class="s-badge s-badge-zinc s-badge-outline">{{ $shortage->trackingType->label() }}</span>
                                    </div>
                                    <p class="mt-1 text-[11px] text-[var(--text-muted)]">
                                        {{ __('Line item') }} #{{ $shortage->opportunityItemId }} &middot; {{ $this->windowLabel($shortage) }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-4 text-right">
                                    <div>
                                        <div class="text-[11px] uppercase tracking-wide text-[var(--text-muted)]">{{ __('Requested') }}</div>
                                        <div class="text-sm font-semibold" style="font-family: var(--font-mono);">{{ $shortage->requestedQuantity }}</div>
                                    </div>
                                    <div>
                                        <div class="text-[11px] uppercase tracking-wide text-[var(--text-muted)]">{{ __('Available') }}</div>
                                        <div class="text-sm font-semibold" style="font-family: var(--font-mono);">{{ $shortage->availableQuantity }}</div>
                                    </div>
                                    <div>
                                        <div class="text-[11px] uppercase tracking-wide text-[var(--text-muted)]">{{ __('Short by') }}</div>
                                        <div class="text-sm font-semibold text-[var(--red)]" style="font-family: var(--font-mono);">{{ $shortage->shortfall }}</div>
                                    </div>
                                    @if($shortage->resolvedQuantity > 0)
                                        <div>
                                            <div class="text-[11px] uppercase tracking-wide text-[var(--text-muted)]">{{ __('Remaining') }}</div>
                                            <div class="text-sm font-semibold" style="font-family: var(--font-mono);">{{ $remaining }}</div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            @php
                                $availPercent = $shortage->requestedQuantity > 0
                                    ? min(100, (int) round($shortage->availableQuantity / $shortage->requestedQuantity * 100))
                                    : 0;
                            @endphp
                            <x-signals.qty-bar
                                class="mt-3"
                                :percent="$availPercent"
                                :label="$shortage->availableQuantity.' / '.$shortage->requestedQuantity.' '.__('available')"
                            />

                            {{-- Resolver options for this shortage --}}
                            <div class="mt-4 border-t border-[var(--border)] pt-3">
                                @if($remaining <= 0)
                                    <p class="text-[11px] text-[var(--text-muted)]">{{ __('This shortfall is fully covered by active resolutions.') }}</p>
                                @elseif(empty($row['resolvers']))
                                    <p class="text-[11px] text-[var(--text-muted)]">{{ __('No resolvers are currently applicable to this shortage.') }}</p>
                                @else
                                    <div class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Resolution options') }}</div>
                                    <div class="mt-2 space-y-2">
                                        @foreach($row['resolvers'] as $resolver)
                                            @foreach($resolver['options'] as $optionIndex => $option)
                                                <div wire:key="opt-{{ $shortage->opportunityItemId }}-{{ $resolver['key'] }}-{{ $optionIndex }}"
                                                     class="flex flex-wrap items-center justify-between gap-2 rounded border border-[var(--border)] px-3 py-2">
                                                    <div class="min-w-0">
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-sm font-medium text-[var(--text)]">{{ $option['label'] }}</span>
                                                            @if($option['is_partial'])
                                                                <span class="s-badge s-badge-amber s-badge-outline">{{ __('Partial') }}</span>
                                                            @endif
                                                            @if($option['auto_executable'])
                                                                <span class="s-badge s-badge-zinc s-badge-outline">{{ __('Auto') }}</span>
                                                            @endif
                                                        </div>
                                                        <p class="mt-0.5 text-[11px] text-[var(--text-muted)]">{{ $option['description'] }}</p>
                                                        <div class="mt-1 flex flex-wrap items-center gap-3 text-[11px] text-[var(--text-muted)]">
                                                            <span>{{ __('Covers') }}: <span class="font-semibold text-[var(--text)]">{{ $option['quantity_resolved'] }}</span></span>
                                                            @if($option['estimated_cost'] !== null)
                                                                <span>{{ __('Est. cost') }}: <span class="font-semibold text-[var(--text)]">{{ $formatter->money($option['estimated_cost']) }}</span></span>
                                                            @endif
                                                            @if($option['estimated_lead_time'] !== null)
                                                                <span>{{ __('Lead time') }}: <span class="font-semibold text-[var(--text)]">{{ $option['estimated_lead_time'] }} {{ __('min') }}</span></span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @if($canResolve)
                                                        <button type="button"
                                                                wire:click="selectResolution({{ $shortage->opportunityItemId }}, '{{ $resolver['key'] }}', {{ $optionIndex }})"
                                                                x-on:click="$dispatch('open-modal', 'apply-resolution')"
                                                                class="s-btn s-btn-sm s-btn-outline-blue shrink-0">
                                                            {{ __('Apply') }}
                                                        </button>
                                                    @endif
                                                </div>
                                            @endforeach
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-signals.panel>

        {{-- ============================================================ --}}
        {{--  ACTIVE RESOLUTIONS + LIFECYCLE (§8.3)                        --}}
        {{-- ============================================================ --}}
        <x-signals.panel title="Resolutions">
            @if($resolutions->isEmpty())
                <p class="text-sm text-[var(--text-muted)]">{{ __('No resolutions have been recorded against this opportunity.') }}</p>
            @else
                <x-signals.table-wrap>
                    <table class="s-table">
                        <thead>
                            <tr>
                                <th>{{ __('Resolver') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th class="text-right">{{ __('Qty') }}</th>
                                <th class="text-right">{{ __('Cost') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th class="text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($resolutions as $resolution)
                                @php
                                    $statusBadge = match(true) {
                                        $resolution->status === \App\Enums\ShortageResolutionStatus::Fulfilled => 's-badge-green',
                                        in_array($resolution->status, [\App\Enums\ShortageResolutionStatus::Cancelled, \App\Enums\ShortageResolutionStatus::Failed], true) => 's-badge-red',
                                        in_array($resolution->status, [\App\Enums\ShortageResolutionStatus::Pending, \App\Enums\ShortageResolutionStatus::Monitoring], true) => 's-badge-amber',
                                        default => 's-badge-blue',
                                    };
                                @endphp
                                <tr wire:key="resolution-{{ $resolution->id }}">
                                    <td class="font-medium">{{ $resolution->resolver_key }}</td>
                                    <td>{{ $resolution->resolution_type->label() }}</td>
                                    <td class="text-right" style="font-family: var(--font-mono);">{{ $resolution->quantity_resolved }}</td>
                                    <td class="text-right" style="font-family: var(--font-mono);">{{ $resolution->cost !== null ? $formatter->money($resolution->cost) : '—' }}</td>
                                    <td><span class="s-badge {{ $statusBadge }}">{{ $resolution->status->label() }}</span></td>
                                    <td class="text-right">
                                        @if($canResolve && ! $resolution->status->isTerminal())
                                            <div class="inline-flex flex-wrap items-center justify-end gap-1">
                                                <button type="button"
                                                        @disabled(! $this->canTransition($resolution, 'confirmed'))
                                                        @if($this->canTransition($resolution, 'confirmed')) wire:click="confirmResolution({{ $resolution->id }})" @endif
                                                        class="s-btn s-btn-xs s-btn-outline-blue {{ $this->canTransition($resolution, 'confirmed') ? '' : 'opacity-40' }}">
                                                    {{ __('Confirm') }}
                                                </button>
                                                <button type="button"
                                                        @disabled(! $this->canTransition($resolution, 'in_progress'))
                                                        @if($this->canTransition($resolution, 'in_progress')) wire:click="startResolution({{ $resolution->id }})" @endif
                                                        class="s-btn s-btn-xs s-btn-outline-blue {{ $this->canTransition($resolution, 'in_progress') ? '' : 'opacity-40' }}">
                                                    {{ __('Start') }}
                                                </button>
                                                <button type="button"
                                                        @disabled(! $this->canTransition($resolution, 'fulfilled'))
                                                        @if($this->canTransition($resolution, 'fulfilled')) wire:click="fulfillResolution({{ $resolution->id }})" @endif
                                                        class="s-btn s-btn-xs s-btn-outline-green {{ $this->canTransition($resolution, 'fulfilled') ? '' : 'opacity-40' }}">
                                                    {{ __('Fulfill') }}
                                                </button>
                                                <button type="button"
                                                        @disabled(! $this->canTransition($resolution, 'cancelled'))
                                                        @if($this->canTransition($resolution, 'cancelled')) wire:click="promptReason({{ $resolution->id }}, 'cancel')" x-on:click="$dispatch('open-modal', 'transition-reason')" @endif
                                                        class="s-btn s-btn-xs s-btn-ghost {{ $this->canTransition($resolution, 'cancelled') ? '' : 'opacity-40' }}">
                                                    {{ __('Cancel') }}
                                                </button>
                                                <button type="button"
                                                        @disabled(! $this->canTransition($resolution, 'failed'))
                                                        @if($this->canTransition($resolution, 'failed')) wire:click="promptReason({{ $resolution->id }}, 'fail')" x-on:click="$dispatch('open-modal', 'transition-reason')" @endif
                                                        class="s-btn s-btn-xs s-btn-ghost {{ $this->canTransition($resolution, 'failed') ? '' : 'opacity-40' }}">
                                                    {{ __('Fail') }}
                                                </button>
                                            </div>
                                        @else
                                            <span class="text-[var(--text-muted)]">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-signals.table-wrap>
            @endif
        </x-signals.panel>
    </div>

    {{-- ============================================================ --}}
    {{--  APPLY-RESOLUTION MODAL                                       --}}
    {{-- ============================================================ --}}
    <x-signals.modal name="apply-resolution" title="{{ __('Apply resolution') }}"
        x-on:shortage-modal-close.window="if ($event.detail?.name === 'apply-resolution') open = false">
        @if($appliedOption !== null)
            <div class="space-y-3">
                <div>
                    <div class="text-sm font-semibold text-[var(--text)]">{{ $appliedOption['label'] }}</div>
                    <p class="mt-1 text-[13px] text-[var(--text-muted)]">{{ $appliedOption['description'] }}</p>
                </div>
                <div class="grid grid-cols-2 gap-2 text-[12px]">
                    <div><span class="text-[var(--text-muted)]">{{ __('Covers') }}:</span> <span class="font-semibold">{{ $appliedOption['quantity_resolved'] }}</span></div>
                    @if($appliedOption['estimated_cost'] !== null)
                        <div><span class="text-[var(--text-muted)]">{{ __('Est. cost') }}:</span> <span class="font-semibold">{{ app(\App\Support\Formatter::class)->money($appliedOption['estimated_cost']) }}</span></div>
                    @endif
                </div>
                <div>
                    <label for="apply-notes" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Notes (optional)') }}</label>
                    <textarea id="apply-notes" wire:model="applyNotes" rows="2" class="s-input w-full"></textarea>
                </div>
            </div>
        @else
            <p class="text-sm text-[var(--text-muted)]">{{ __('This option is no longer available — the shortage may have changed.') }}</p>
        @endif

        <x-slot:footer>
            <button type="button" x-on:click="$dispatch('close-modal', 'apply-resolution')" class="s-btn s-btn-ghost">{{ __('Cancel') }}</button>
            <button type="button" wire:click="applyResolution" @disabled($appliedOption === null) class="s-btn s-btn-primary">{{ __('Apply resolution') }}</button>
        </x-slot:footer>
    </x-signals.modal>

    {{-- ============================================================ --}}
    {{--  CANCEL / FAIL REASON MODAL                                   --}}
    {{-- ============================================================ --}}
    <x-signals.modal name="transition-reason" title="{{ __('Reason') }}"
        x-on:shortage-modal-close.window="if ($event.detail?.name === 'transition-reason') open = false">
        <div>
            <label for="transition-reason" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">
                {{ $reasonTransition === 'fail' ? __('Why did this resolution fail? (optional)') : __('Why are you cancelling? (optional)') }}
            </label>
            <textarea id="transition-reason" wire:model="transitionReason" rows="3" class="s-input w-full"></textarea>
        </div>

        <x-slot:footer>
            <button type="button" x-on:click="$dispatch('close-modal', 'transition-reason')" class="s-btn s-btn-ghost">{{ __('Dismiss') }}</button>
            <button type="button" wire:click="submitReasonTransition" class="s-btn s-btn-danger">
                {{ $reasonTransition === 'fail' ? __('Mark failed') : __('Cancel resolution') }}
            </button>
        </x-slot:footer>
    </x-signals.modal>
</section>
