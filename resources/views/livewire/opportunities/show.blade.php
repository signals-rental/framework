<?php

use App\Actions\Opportunities\CloneOpportunity;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\DeleteOpportunity;
use App\Actions\Opportunities\ReinstateOpportunity;
use App\Actions\Opportunities\RevertToQuotation;
use App\Actions\Opportunities\UnlockOpportunity;
use App\Enums\AssetAssignmentStatus;
use App\Enums\OpportunityState;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\Rules\ShortageConfirmationRule;
use App\Guards\Opportunities\TransitionContext;
use App\Livewire\Concerns\HasAuditTimeline;
use App\Models\Opportunity;
use App\Models\OpportunityItemAsset;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

/**
 * Opportunity Show (overview) page (M8-2).
 *
 * Renders the record overview plus the Actions split-button. The permitted
 * state-transition actions are computed exactly the way the API's
 * `available_actions` endpoint does (OpportunityController::availableActions /
 * describeAction): a permission probe, a generic state precondition, then the
 * non-throwing GuardPipeline::check() dry-run for transitions that route through
 * it. The transition wire-methods call the SAME action classes the API calls.
 */
new #[Layout('components.layouts.app')] class extends Component
{
    use HasAuditTimeline;

    public Opportunity $opportunity;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');

        $this->opportunity = $opportunity->load(['member', 'venue', 'store', 'owner', 'items']);
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

    public function convertToQuotation(): void
    {
        $this->runTransition(fn () => (new ConvertToQuotation)($this->opportunity));
    }

    public function convertToOrder(): void
    {
        $this->runTransition(fn () => (new ConvertToOrder)($this->opportunity));
    }

    public function reinstate(): void
    {
        $this->runTransition(fn () => (new ReinstateOpportunity)($this->opportunity));
    }

    public function revertToQuotation(): void
    {
        $this->runTransition(fn () => (new RevertToQuotation)($this->opportunity));
    }

    public function unlockRates(): void
    {
        $this->runTransition(fn () => (new UnlockOpportunity)($this->opportunity, null));
    }

    public function cloneOpportunity(): mixed
    {
        try {
            $result = (new CloneOpportunity)($this->opportunity);
        } catch (AuthorizationException $e) {
            session()->flash('error', 'You do not have permission to clone this opportunity.');

            return null;
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first() ?? 'Unable to clone the opportunity.');

            return null;
        }

        return $this->redirect(route('opportunities.show', $result->id), navigate: true);
    }

    public function archive(): mixed
    {
        try {
            (new DeleteOpportunity)($this->opportunity);
        } catch (AuthorizationException $e) {
            session()->flash('error', 'You do not have permission to archive this opportunity.');

            return null;
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first() ?? 'Unable to archive the opportunity.');

            return null;
        }

        return $this->redirect(route('opportunities.index'), navigate: true);
    }

    /**
     * Run a state-transition action, catching the user-facing failures the action
     * classes raise (authorisation 403s and guard/shortage 422s) and flashing the
     * first message. On success the projection is refreshed in place; with()
     * re-runs on the re-render so the badges + action verdicts reflect the new state.
     *
     * @param  \Closure(): mixed  $action
     */
    protected function runTransition(\Closure $action): void
    {
        try {
            $action();
            $this->opportunity->refresh();
        } catch (AuthorizationException $e) {
            session()->flash('error', 'You do not have permission to perform this action.');
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first() ?? 'This action could not be completed.');
        }
    }

    /**
     * Opportunity timeline colours: lifecycle progressions read green, terminal /
     * destructive events red, within-state changes blue.
     *
     * @return array<string, list<string>>
     */
    protected function timelineColorMap(): array
    {
        return [
            'green' => ['.created', '.quoted', '.converted_to_order', '.reinstated', '.restored'],
            'red' => ['.deleted', '.cancelled', '.lost', '.dead'],
            'blue' => ['.updated', '.status_changed', '.reverted_to_quotation'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'availableActions' => $this->buildAvailableActions(),
            'timeline' => $this->auditTimelineFor($this->opportunity),
        ];
    }

    /**
     * The subset of state-transition actions the Show UI surfaces, computed the
     * same way as OpportunityController::availableActions. change_status and
     * dispatch are intentionally omitted — those are not simple menu items here
     * (status-change UI + dispatch flows land in later milestones).
     *
     * @return list<array{key: string, label: string, allowed: bool, reason: string|null, code: string|null}>
     */
    protected function buildAvailableActions(): array
    {
        $opportunity = $this->opportunity;
        $status = $opportunity->statusEnum();
        $isDraft = $opportunity->state === OpportunityState::Draft;
        $isQuotation = $opportunity->state === OpportunityState::Quotation;
        $isOrder = $opportunity->state === OpportunityState::Order;
        $hasLocks = (bool) $opportunity->exchange_rate_locked || (bool) $opportunity->tax_locked;

        return [
            $this->describeAction($opportunity, 'convert_to_quotation', 'Convert to Quotation', 'opportunities.edit', 'opportunity.convert_to_quotation',
                statePrecondition: fn (): ?array => $isDraft ? null : ['Only a draft can be converted to a quotation.', 'invalid_state']),

            $this->describeAction($opportunity, 'convert_to_order', 'Convert to Order', 'opportunities.edit', ShortageConfirmationRule::TRANSITION,
                statePrecondition: fn (): ?array => $isQuotation && ! $status->isClosed() ? null : ['Only an open quotation can be converted to an order.', 'invalid_state']),

            $this->describeAction($opportunity, 'reinstate', 'Reinstate', 'opportunities.edit', ReinstateOpportunity::TRANSITION,
                statePrecondition: fn (): ?array => $status->isReinstatable() ? null : ['Only a lost, dead, postponed, or cancelled opportunity can be reinstated.', 'invalid_state']),

            $this->describeAction($opportunity, 'revert_to_quotation', 'Revert to Quotation', 'opportunities.edit', RevertToQuotation::TRANSITION,
                statePrecondition: fn (): ?array => $this->revertToQuotationPrecondition($opportunity, $isOrder, $status->isClosed())),

            $this->describeAction($opportunity, 'unlock_locks', 'Unlock Rates', 'opportunities.unlock_rates', null,
                statePrecondition: fn (): ?array => $hasLocks ? null : ['The opportunity has no active FX/tax locks to release.', 'nothing_to_unlock']),

            $this->describeAction($opportunity, 'clone', 'Clone', 'opportunities.create', null),

            $this->describeAction($opportunity, 'delete', 'Archive', 'opportunities.delete', null),
        ];
    }

    /**
     * Describe one available action: a permission probe, a generic state
     * precondition, then the non-throwing guard-pipeline dry-run for transitions
     * that route through it. Mirrors OpportunityController::describeAction.
     *
     * @param  \Closure(): (array{0: string, 1: string}|null)|null  $statePrecondition
     * @return array{key: string, label: string, allowed: bool, reason: string|null, code: string|null}
     */
    protected function describeAction(
        Opportunity $opportunity,
        string $key,
        string $label,
        string $permission,
        ?string $transition,
        ?\Closure $statePrecondition = null,
    ): array {
        if (! Gate::allows($permission)) {
            return $this->actionVerdict($key, $label, false, 'You do not have permission to perform this action.', 'permission_denied');
        }

        if ($statePrecondition !== null) {
            $stateDenial = $statePrecondition();

            if ($stateDenial !== null) {
                return $this->actionVerdict($key, $label, false, $stateDenial[0], $stateDenial[1]);
            }
        }

        if ($transition !== null) {
            $result = app(GuardPipeline::class)->check(new TransitionContext(
                transition: $transition,
                opportunity: $opportunity,
                permission: $permission,
            ));

            if ($result->denied()) {
                return $this->actionVerdict($key, $label, false, $result->firstError(), $result->code);
            }
        }

        return $this->actionVerdict($key, $label, true, null, null);
    }

    /**
     * @return array{key: string, label: string, allowed: bool, reason: string|null, code: string|null}
     */
    protected function actionVerdict(string $key, string $label, bool $allowed, ?string $reason, ?string $code): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'allowed' => $allowed,
            'reason' => $reason,
            'code' => $code,
        ];
    }

    /**
     * The revert-to-quotation state precondition: must be an open Order with no
     * dispatch history. Mirrors OpportunityController::revertToQuotationPrecondition.
     *
     * @return array{0: string, 1: string}|null
     */
    protected function revertToQuotationPrecondition(Opportunity $opportunity, bool $isOrder, bool $isClosed): ?array
    {
        if (! $isOrder || $isClosed) {
            return ['Only an open order can be reverted to a quotation.', 'invalid_state'];
        }

        $dispatched = OpportunityItemAsset::query()
            ->whereIn('opportunity_item_id', $opportunity->allItems()->select('opportunity_items.id'))
            ->where('status', '>=', AssetAssignmentStatus::Dispatched->value)
            ->exists();

        $bulkDispatched = $opportunity->allItems()
            ->where('dispatched_quantity', '>', 0)
            ->exists();

        return $dispatched || $bulkDispatched
            ? ['An order with dispatched assets cannot be reverted to a quotation.', 'dispatched']
            : null;
    }
}; ?>

<section class="w-full">
    @include('livewire.opportunities.partials.opportunity-header', ['opportunity' => $opportunity, 'showActions' => true])

    @include('livewire.opportunities.partials.opportunity-tabs', ['opportunity' => $opportunity, 'activeTab' => 'overview'])

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
    @endphp

    {{-- 3-column layout --}}
    <div class="grid grid-cols-[240px_1fr_280px] gap-6 px-6 py-4 max-lg:grid-cols-[240px_1fr] max-md:grid-cols-1 max-md:px-5 max-sm:px-3">

        {{-- ============================================================ --}}
        {{-- LEFT SIDEBAR --}}
        {{-- ============================================================ --}}
        <div class="space-y-6">
            <x-signals.panel title="Key Attributes">
                <x-signals.data-list layout="vertical" :items="array_filter([
                    $opportunity->number ? ['label' => 'Number', 'value' => $opportunity->number, 'mono' => true] : null,
                    $opportunity->reference ? ['label' => 'Reference', 'value' => $opportunity->reference] : null,
                    ['label' => 'State', 'value' => $opportunity->state->label(), 'badge' => $stateBadgeClass],
                    ['label' => 'Status', 'value' => $status->label()],
                    ['label' => 'Currency', 'value' => $opportunity->currency_code ?? '—'],
                    ['label' => 'Created', 'value' => $opportunity->created_at?->format('d M Y') ?? '—'],
                    ['label' => 'Updated', 'value' => $opportunity->updated_at?->format('d M Y') ?? '—'],
                ])" />
            </x-signals.panel>
        </div>

        {{-- ============================================================ --}}
        {{-- CENTER CONTENT --}}
        {{-- ============================================================ --}}
        <div class="space-y-6">
            {{-- Totals --}}
            <x-signals.stat-grid style="grid-template-columns: repeat(3, 1fr);">
                <x-signals.stat-card color="green" label="Charge Total" :value="$formatter->money($opportunity->charge_total ?? 0)" />
                <x-signals.stat-card color="blue" label="Excl. Tax" :value="$formatter->money($opportunity->charge_excluding_tax_total ?? 0)" />
                <x-signals.stat-card color="amber" label="Tax" :value="$formatter->money($opportunity->tax_total ?? 0)" />
            </x-signals.stat-grid>

            {{-- Line items summary (read-only — full read-only tab is /items, the
                 editable editor is M8-3). --}}
            <x-signals.panel title="Line Items">
                @if($opportunity->items->isNotEmpty())
                    <x-signals.table-wrap>
                        <table class="s-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-right">Qty</th>
                                    <th class="text-right">Unit Price</th>
                                    <th class="text-right">Line Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($opportunity->items as $item)
                                    <tr wire:key="item-{{ $item->id }}">
                                        <td>{{ $item->name }}</td>
                                        <td class="text-right" style="font-family: var(--font-mono);">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                                        <td class="text-right" style="font-family: var(--font-mono);">{{ $formatter->money($item->unit_price ?? 0) }}</td>
                                        <td class="text-right" style="font-family: var(--font-mono);">{{ $formatter->money($item->total ?? 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-signals.table-wrap>
                @else
                    <p class="text-sm text-[var(--text-muted)]">No line items.</p>
                @endif
            </x-signals.panel>

            {{-- Activity Timeline (live audit trail) --}}
            <x-signals.panel title="Activity Timeline">
                @if($timeline->isEmpty())
                    <div class="text-sm text-[var(--text-muted)] py-4">No recorded activity for this opportunity yet.</div>
                @else
                    <x-signals.timeline>
                        @foreach($timeline as $event)
                            <x-signals.timeline-item
                                :color="$event['color']"
                                :title="$event['title']"
                                :meta="$event['meta']"
                                wire:key="timeline-{{ $loop->index }}"
                            >
                                @if($event['body'])
                                    {{ $event['body'] }}
                                @endif
                            </x-signals.timeline-item>
                        @endforeach
                    </x-signals.timeline>
                @endif
            </x-signals.panel>
        </div>

        {{-- ============================================================ --}}
        {{-- RIGHT SIDEBAR --}}
        {{-- ============================================================ --}}
        <div class="space-y-6 max-lg:col-span-full max-lg:grid max-lg:grid-cols-2 max-lg:gap-6 max-md:grid-cols-1">
            {{-- Dates --}}
            <x-signals.panel title="Dates">
                <x-signals.data-list layout="vertical" :items="[
                    ['label' => 'Starts', 'value' => $opportunity->starts_at ? $formatter->dateTime($opportunity->starts_at) : '—'],
                    ['label' => 'Ends', 'value' => $opportunity->ends_at ? $formatter->dateTime($opportunity->ends_at) : '—'],
                    ['label' => 'Charge Starts', 'value' => $opportunity->charge_starts_at ? $formatter->dateTime($opportunity->charge_starts_at) : '—'],
                    ['label' => 'Charge Ends', 'value' => $opportunity->charge_ends_at ? $formatter->dateTime($opportunity->charge_ends_at) : '—'],
                ]" />
            </x-signals.panel>

            {{-- Member & Store --}}
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
    </div>
</section>
