<?php

namespace App\Livewire\Concerns;

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

/**
 * Shared opportunity Actions split-button behaviour.
 *
 * The opportunity Show (overview) page and every opportunity tab page (Assets,
 * Shortages, Versions, Costs, Participants, Custom Fields, Files) render the SAME
 * Actions split-button (state-transition menu) in the shared page header plus the
 * two shared modals (change-status picker + confirm-action). This trait holds the
 * properties, computed verdicts (availableActions / statusOptions), the
 * confirm-modal plumbing and the transition wire-methods so they are identical
 * everywhere instead of living only on the Show component.
 *
 * The using Volt component must declare a `public Opportunity $opportunity` property
 * and include the partials/opportunity-action-modals partial in its view. The
 * permitted state-transition actions are computed exactly the way the API's
 * `available_actions` endpoint does (OpportunityController::availableActions /
 * describeAction); the transition wire-methods call the SAME action classes the API
 * calls.
 *
 * @property Opportunity $opportunity
 *
 * @phpstan-ignore trait.unused (used by Volt components in Blade files)
 */
trait HasOpportunityActions
{
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
     * Run the staged Actions item. Dispatches to the whitelisted wire method, then
     * closes the confirm modal and clears the pending state. A no-op when nothing is
     * staged or the staged key is not confirmable (defensive — the UI never stages
     * otherwise).
     *
     * The close-modal event is dispatched AFTER the wired method runs — this is the
     * fix for the "modal stays open once the action completes" report: the on-page
     * transitions (convert / revert / reinstate / reopen / unlock) are slow,
     * event-sourced commits that re-render this component, and emitting close-modal
     * as the FINAL step of the same round-trip guarantees the close event reaches
     * Alpine on the very response that finishes the commit, so the modal can never
     * be left visible over a finished action. The redirecting actions (clone,
     * archive) tear the page down via navigation, so the trailing dispatch is a
     * harmless no-op for them. The pending label/message are also reset so a stale
     * title can't flash if the modal is reopened for a different action.
     */
    public function confirmPendingAction(): mixed
    {
        $method = $this->confirmableActions()[$this->pendingAction] ?? null;

        $this->pendingAction = null;
        $this->pendingLabel = '';
        $this->pendingMessage = '';

        $result = $method !== null ? $this->{$method}() : null;

        $this->dispatch('close-modal', 'confirm-action');

        return $result;
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
        } catch (AuthorizationException) {
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
        } catch (AuthorizationException) {
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
        } catch (AuthorizationException) {
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
        } catch (AuthorizationException) {
            session()->flash('error', 'You do not have permission to perform this action.');
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first() ?? 'This action could not be completed.');
        }
    }

    /**
     * The shared data the Actions split-button + modals need. Merge this into the
     * component's with() array so the header/modals partials render identically on
     * the overview page and every tab page.
     *
     * @return array<string, mixed>
     */
    protected function opportunityActionData(): array
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
     * The subset of state-transition actions the UI surfaces, computed the same way
     * as OpportunityController::availableActions. change_status is a separate picker
     * (it opens the change-status modal rather than firing a single transition); the
     * dispatch/fulfilment flows live on the Assets tab.
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
}
