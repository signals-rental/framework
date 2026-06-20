<?php

use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\CloneOpportunity;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\DeleteOpportunity;
use App\Actions\Opportunities\ReinstateOpportunity;
use App\Actions\Opportunities\ReopenOpportunity;
use App\Actions\Opportunities\RestoreOpportunity;
use App\Actions\Opportunities\RevertToDraft;
use App\Actions\Opportunities\RevertToQuotation;
use App\Actions\Opportunities\UnlockOpportunity;
use App\Enums\AssetAssignmentStatus;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\Rules\ShortageConfirmationRule;
use App\Guards\Opportunities\TransitionContext;
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
    public Opportunity $opportunity;

    /**
     * The Actions split-button items confirm through a single shared styled modal
     * (B1) rather than per-item native JS dialogs: an item sets the pending wire
     * method + its label/message and opens `confirm-action`; the modal's Confirm
     * button calls confirmPendingAction(), which dispatches to the stored method.
     * Only the methods whitelisted in confirmableActions() may be invoked this way.
     */
    public ?string $pendingAction = null;

    public string $pendingLabel = '';

    public string $pendingMessage = '';

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');

        $this->opportunity = $opportunity->load(['member', 'venue', 'store', 'owner', 'activeVersion']);
    }

    /**
     * The Actions wire-methods that may be invoked through the shared confirm modal,
     * keyed by the verdict key the header renders. Whitelisting the dispatch target
     * keeps confirmPendingAction() from calling arbitrary component methods.
     *
     * @return array<string, string>
     */
    protected function confirmableActions(): array
    {
        return [
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
    }

    /**
     * Stage an Actions item for confirmation: record the pending verdict key (mapped
     * to a whitelisted wire method) plus the label/message the shared modal shows.
     * The header opens the `confirm-action` modal once this has been set.
     */
    public function prepareAction(string $key, string $label, string $message): void
    {
        if (! array_key_exists($key, $this->confirmableActions())) {
            return;
        }

        $this->pendingAction = $key;
        $this->pendingLabel = $label;
        $this->pendingMessage = $message;
    }

    /**
     * Run the staged Actions item. Dispatches to the whitelisted wire method, closes
     * the confirm modal, and clears the pending state. A no-op when nothing is staged
     * or the staged key is not confirmable (defensive — the UI never stages otherwise).
     */
    public function confirmPendingAction(): mixed
    {
        $method = $this->confirmableActions()[$this->pendingAction] ?? null;

        $this->pendingAction = null;
        $this->dispatch('close-modal', 'confirm-action');

        if ($method === null) {
            return null;
        }

        return $this->{$method}();
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

    public function reopen(): void
    {
        $this->runTransition(fn () => (new ReopenOpportunity)($this->opportunity));
    }

    public function revertToQuotation(): void
    {
        $this->runTransition(fn () => (new RevertToQuotation)($this->opportunity));
    }

    public function revertToDraft(): void
    {
        $this->runTransition(fn () => (new RevertToDraft)($this->opportunity));
    }

    public function unlockRates(): void
    {
        $this->runTransition(fn () => (new UnlockOpportunity)($this->opportunity, null));
    }

    /**
     * Move the opportunity to a different status WITHIN its current state (the
     * change_status picker). The legal candidates are derived generically from the
     * OpportunityStatus enum (every status of the current state, minus the current
     * one) — never a hardcoded named matrix; the OpportunityStatusChanged event's
     * own invariants (closed, cancel-with-stock-out, complete-with-unreturned)
     * remain the source of truth and surface as a flashed 422.
     */
    public function changeStatus(int $status): void
    {
        $target = OpportunityStatus::tryFrom($this->opportunity->state->value * 100 + $status);

        if ($target === null || $target->state() !== $this->opportunity->state) {
            session()->flash('error', 'The given status is not valid for the opportunity\'s current state.');

            return;
        }

        $this->runTransition(fn () => (new ChangeOpportunityStatus)($this->opportunity, $target));

        // B2: only close the change-status modal once the move actually succeeded.
        // runTransition() flashes an `error` on an authorisation/guard failure and
        // leaves the status unchanged — keep the modal open in that case so the
        // flashed reason is visible and the user can pick a different target.
        if (! session()->has('error')) {
            $this->dispatch('close-modal', 'change-status');
        }
    }

    /**
     * The legal target statuses for the change_status picker: every status that
     * belongs to the opportunity's current state, except the current one. Derived
     * from the enum so configurable/custom statuses are inherited automatically.
     * Empty when the opportunity is closed (no status moves are permitted).
     *
     * @return list<array{value: int, label: string}>
     */
    protected function statusOptions(): array
    {
        if ($this->opportunity->statusEnum()->isClosed()) {
            return [];
        }

        $state = $this->opportunity->state;
        $current = $this->opportunity->statusEnum();

        return collect(OpportunityStatus::cases())
            ->filter(fn (OpportunityStatus $s): bool => $s->state() === $state && $s !== $current)
            ->map(fn (OpportunityStatus $s): array => ['value' => $s->statusValue(), 'label' => $s->label()])
            ->values()
            ->all();
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
     * Restore (un-archive) a soft-deleted opportunity. Archived opportunities remain
     * viewable (the route binding resolves withTrashed()) but are read-only except
     * for this Restore. On success the projection is refreshed in place so the
     * archived banner clears and the normal actions return.
     */
    public function restore(): void
    {
        try {
            (new RestoreOpportunity)($this->opportunity);
            $this->opportunity->refresh();
        } catch (AuthorizationException $e) {
            session()->flash('error', 'You do not have permission to restore this opportunity.');
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first() ?? 'Unable to restore the opportunity.');
        }
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
     * @return array<string, mixed>
     */
    public function with(): array
    {
        // An archived (soft-deleted) opportunity is READ-ONLY: the only mutating
        // affordance is Restore. Suppress the transition actions + status picker so
        // the UI matches the action-layer guards.
        $isArchived = $this->opportunity->trashed();

        return [
            'isArchived' => $isArchived,
            'canRestore' => $isArchived && Gate::allows('opportunities.delete'),
            'availableActions' => $isArchived ? [] : $this->buildAvailableActions(),
            'statusOptions' => $isArchived ? [] : $this->statusOptions(),
            'canChangeStatus' => ! $isArchived && Gate::allows('opportunities.edit') && ! $this->opportunity->statusEnum()->isClosed(),
        ];
    }

    /**
     * The subset of state-transition actions the Show UI surfaces, computed the
     * same way as OpportunityController::availableActions. change_status is a
     * separate picker (it opens the change-status modal rather than firing a single
     * transition); the dispatch/fulfilment flows live on the Assets tab.
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

            $this->describeAction($opportunity, 'reopen', 'Re-open', 'opportunities.edit', ReopenOpportunity::TRANSITION,
                statePrecondition: fn (): ?array => $status->isTerminalComplete() ? null : ['Only a completed order can be re-opened.', 'invalid_state']),

            $this->describeAction($opportunity, 'revert_to_quotation', 'Revert to Quotation', 'opportunities.edit', RevertToQuotation::TRANSITION,
                statePrecondition: fn (): ?array => $this->revertToQuotationPrecondition($opportunity, $isOrder, $status->isClosed())),

            $this->describeAction($opportunity, 'revert_to_draft', 'Revert to Draft', 'opportunities.edit', RevertToDraft::TRANSITION,
                statePrecondition: fn (): ?array => $isQuotation && $status->isRevertibleToDraft() ? null : ['Only an open, provisional quotation can be reverted to a draft.', 'invalid_state']),

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
    @include('livewire.opportunities.partials.opportunity-header', ['opportunity' => $opportunity, 'showActions' => true, 'canChangeStatus' => $canChangeStatus])

    @include('livewire.opportunities.partials.opportunity-tabs', ['opportunity' => $opportunity, 'activeTab' => 'overview'])

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

    {{-- 2-column layout: ~25% Key-Attributes sidebar + ~75% live line-item editor. --}}
    <div class="grid grid-cols-[minmax(240px,1fr)_3fr] gap-6 px-6 py-4 max-md:grid-cols-1 max-md:px-5 max-sm:px-3">

        {{-- ============================================================ --}}
        {{-- LEFT SIDEBAR — totals, key attributes, dates, member         --}}
        {{-- ============================================================ --}}
        <div class="min-w-0 space-y-6">
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

            <x-signals.panel title="Dates">
                <x-signals.data-list layout="vertical" :items="[
                    ['label' => 'Starts', 'value' => $opportunity->starts_at ? $formatter->dateTime($opportunity->starts_at) : '—'],
                    ['label' => 'Ends', 'value' => $opportunity->ends_at ? $formatter->dateTime($opportunity->ends_at) : '—'],
                    ['label' => 'Charge Starts', 'value' => $opportunity->charge_starts_at ? $formatter->dateTime($opportunity->charge_starts_at) : '—'],
                    ['label' => 'Charge Ends', 'value' => $opportunity->charge_ends_at ? $formatter->dateTime($opportunity->charge_ends_at) : '—'],
                ]" />
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
        <div class="min-w-0">
            <livewire:opportunities.items :opportunity="$opportunity" :key="'opp-items-'.$opportunity->id" />
        </div>
    </div>

    {{-- ============================================================ --}}
    {{--  CHANGE-STATUS PICKER MODAL                                   --}}
    {{--                                                               --}}
    {{--  Offers the legal target statuses for the CURRENT state       --}}
    {{--  (derived from the OpportunityStatus enum, never a hardcoded  --}}
    {{--  matrix). Calls ChangeOpportunityStatus; an illegal move the  --}}
    {{--  event guard rejects surfaces as a flashed 422.               --}}
    {{-- ============================================================ --}}
    @if($canChangeStatus)
        <x-signals.modal name="change-status" title="{{ __('Change status') }}">
            @if(empty($statusOptions))
                <p class="text-sm text-[var(--text-muted)]">{{ __('There are no other statuses available for the current state.') }}</p>
            @else
                <p class="mb-3 text-sm text-[var(--text-muted)]">
                    {{ __('Move this :state to a different status.', ['state' => strtolower($opportunity->state->label())]) }}
                </p>
                <div class="space-y-2">
                    @foreach($statusOptions as $option)
                        <button type="button"
                                wire:key="status-option-{{ $option['value'] }}"
                                wire:click="changeStatus({{ $option['value'] }})"
                                class="s-btn s-btn-outline-blue w-full justify-start">
                            {{ $option['label'] }}
                        </button>
                    @endforeach
                </div>
            @endif

            <x-slot:footer>
                <button type="button" x-on:click="$dispatch('close-modal', 'change-status')" class="s-btn s-btn-ghost">{{ __('Cancel') }}</button>
            </x-slot:footer>
        </x-signals.modal>
    @endif

    {{-- ============================================================ --}}
    {{--  SHARED ACTION-CONFIRM MODAL (B1)                             --}}
    {{--                                                               --}}
    {{--  A single styled modal confirms EVERY Actions split-button    --}}
    {{--  item (clone, archive, convert×2, reinstate, revert, unlock)  --}}
    {{--  in place of per-item native `wire:confirm` dialogs. Each     --}}
    {{--  header item stages the pending action (prepareAction) + opens --}}
    {{--  this modal; Confirm calls confirmPendingAction(), which       --}}
    {{--  dispatches to the staged, whitelisted wire method and closes  --}}
    {{--  the modal. Mirrors the index-page archive-confirm styling.    --}}
    {{-- ============================================================ --}}
    <x-signals.modal name="confirm-action" title="{{ $pendingLabel ?: __('Confirm action') }}" size="sm">
        <p class="text-sm text-[var(--text-muted)]">{{ $pendingMessage ?: __('Are you sure?') }}</p>

        <x-slot:footer>
            <button type="button" x-on:click="$dispatch('close-modal', 'confirm-action')" class="s-btn s-btn-sm">{{ __('Cancel') }}</button>
            <button type="button"
                    wire:click="confirmPendingAction"
                    class="s-btn s-btn-sm s-btn-primary">
                {{ $pendingLabel ?: __('Confirm') }}
            </button>
        </x-slot:footer>
    </x-signals.modal>
</section>
