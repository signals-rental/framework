<?php

namespace App\Livewire\Concerns;

use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\CloneOpportunity;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\DeleteOpportunity;
use App\Actions\Opportunities\LockOpportunity;
use App\Actions\Opportunities\ReinstateOpportunity;
use App\Actions\Opportunities\ReopenOpportunity;
use App\Actions\Opportunities\RestoreOpportunity;
use App\Actions\Opportunities\RevertToDraft;
use App\Actions\Opportunities\RevertToQuotation;
use App\Actions\Opportunities\UnlockOpportunity;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Guards\Opportunities\Rules\ShortageConfirmationRule;
use App\Models\Opportunity;
use App\Services\Opportunities\OpportunityActionDescriber;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Thunk\Verbs\Exceptions\EventNotValid;

/**
 * Shared opportunity Actions split-button behaviour.
 *
 * The opportunity Show (overview) page and every opportunity tab page (Assets,
 * Shortages, Versions, Costs, Participants, Custom Fields, Files) render the SAME
 * Actions split-button (state-transition menu) in the shared page header plus the
 * change-status picker modal). This trait holds the computed verdicts
 * (availableActions / statusOptions) and the transition wire-methods so they are identical
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
    /** @var 'convert_to_quotation'|'convert_to_order'|null */
    public ?string $pendingConvertKey = null;

    /** @var 'reinstate'|'reopen'|'revert_to_quotation'|'revert_to_draft'|'clone'|'delete'|'unlock_locks'|null */
    public ?string $pendingConfirmKey = null;

    /**
     * @return array<string, array{title: string, message: string, confirm: string, success: string}>
     */
    protected function convertModalCopy(): array
    {
        return [
            'convert_to_quotation' => [
                'title' => __('Convert to quotation'),
                'message' => __('Convert this opportunity to a quotation?'),
                'confirm' => __('Convert to quotation'),
                'success' => __('Converted to quotation'),
            ],
            'convert_to_order' => [
                'title' => __('Convert to order'),
                'message' => __('Convert this quotation to an order? Reserved demand becomes confirmed.'),
                'confirm' => __('Convert to order'),
                'success' => __('Converted to order'),
            ],
        ];
    }

    public function openConvertModal(string $key): void
    {
        if (! array_key_exists($key, $this->convertModalCopy())) {
            return;
        }

        $this->pendingConvertKey = $key;
        $this->js("\$dispatch('open-modal', 'convert-opportunity')");
    }

    public function confirmConvert(): void
    {
        $key = $this->pendingConvertKey;
        $copy = $key !== null ? ($this->convertModalCopy()[$key] ?? null) : null;

        if ($copy === null) {
            return;
        }

        $errorBefore = session()->has('error');

        match ($key) {
            'convert_to_quotation' => $this->convertToQuotation(),
            'convert_to_order' => $this->convertToOrder(),
            default => null,
        };

        if (! $errorBefore && ! session()->has('error')) {
            $this->dispatch('toast', type: 'success', message: $copy['success']);
            $this->dispatch('close-modal', 'convert-opportunity');
            $this->js("\$dispatch('close-modal', 'convert-opportunity')");
        }

        $this->pendingConvertKey = null;
    }

    /**
     * @return array<string, array{title: string, message: string, confirm: string, success: string, danger?: bool}>
     */
    protected function confirmModalCopy(): array
    {
        return [
            'reinstate' => [
                'title' => __('Reinstate opportunity'),
                'message' => __('Reinstate this opportunity and return it to an active status?'),
                'confirm' => __('Reinstate'),
                'success' => __('Opportunity reinstated'),
            ],
            'reopen' => [
                'title' => __('Re-open order'),
                'message' => __('Re-open this completed order so fulfilment can continue?'),
                'confirm' => __('Re-open'),
                'success' => __('Order re-opened'),
            ],
            'revert_to_quotation' => [
                'title' => __('Revert to quotation'),
                'message' => __('Revert this order back to a quotation? Demand will be released and FX/tax locks cleared.'),
                'confirm' => __('Revert to quotation'),
                'success' => __('Reverted to quotation'),
            ],
            'revert_to_draft' => [
                'title' => __('Revert to draft'),
                'message' => __('Revert this quotation to a draft? Reserved demand will be released.'),
                'confirm' => __('Revert to draft'),
                'success' => __('Reverted to draft'),
            ],
            'clone' => [
                'title' => __('Clone opportunity'),
                'message' => __('Create a copy of this opportunity with the same line items and settings?'),
                'confirm' => __('Clone'),
                'success' => __('Opportunity cloned'),
            ],
            'delete' => [
                'title' => __('Archive opportunity'),
                'message' => __('Archive this opportunity? It can be restored later from the archive.'),
                'confirm' => __('Archive'),
                'success' => __('Opportunity archived'),
                'danger' => true,
            ],
            ...$this->unlockLocksConfirmCopy(),
        ];
    }

    /**
     * @return array<string, array{title: string, message: string, confirm: string, success: string}>
     */
    protected function unlockLocksConfirmCopy(): array
    {
        $hasLocks = (bool) $this->opportunity->exchange_rate_locked || (bool) $this->opportunity->tax_locked;

        if ($hasLocks) {
            return [
                'unlock_locks' => [
                    'title' => __('Unlock price'),
                    'message' => __('Unlock price — allow editing quantities, rates, discounts, and removing lines again? New items will be priced normally.'),
                    'confirm' => __('Unlock price'),
                    'success' => __('Price unlocked'),
                ],
            ];
        }

        return [
            'unlock_locks' => [
                'title' => __('Lock price'),
                'message' => __('Lock price — freeze all line pricing and the charge total? You will not be able to edit qty, rate, discount, or days, remove lines, or add priced items (new lines are added at £0).'),
                'confirm' => __('Lock price'),
                'success' => __('Price locked'),
            ],
        ];
    }

    public function openConfirmModal(string $key): void
    {
        if (! array_key_exists($key, $this->confirmModalCopy())) {
            return;
        }

        $this->pendingConfirmKey = $key;
        $this->js("\$dispatch('open-modal', 'confirm-opportunity-action')");
    }

    public function confirmTransition(): void
    {
        $key = $this->pendingConfirmKey;
        $copy = $key !== null ? ($this->confirmModalCopy()[$key] ?? null) : null;

        if ($copy === null) {
            return;
        }

        $errorBefore = session()->has('error');

        match ($key) {
            'reinstate' => $this->reinstate(),
            'reopen' => $this->reopen(),
            'revert_to_quotation' => $this->revertToQuotation(),
            'revert_to_draft' => $this->revertToDraft(),
            'clone' => $this->cloneOpportunity(),
            'delete' => $this->archive(),
            'unlock_locks' => $this->unlockRates(),
            default => null,
        };

        if ($key === 'clone' && ! $errorBefore && ! session()->has('error')) {
            $this->pendingConfirmKey = null;

            return;
        }

        if (! $errorBefore && ! session()->has('error')) {
            $this->dispatch('toast', type: 'success', message: $copy['success']);
            $this->dispatch('close-modal', 'confirm-opportunity-action');
            $this->js("\$dispatch('close-modal', 'confirm-opportunity-action')");
        }

        $this->pendingConfirmKey = null;
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
        $opportunity = $this->opportunity;
        $hasLocks = (bool) $opportunity->exchange_rate_locked || (bool) $opportunity->tax_locked;

        if ($hasLocks) {
            $this->runTransition(fn () => (new UnlockOpportunity)($opportunity, null));
        } else {
            $this->runTransition(fn () => (new LockOpportunity)($opportunity, null));
        }
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

        if (! session()->has('error')) {
            $this->dispatch('close-modal', 'change-status');
            $this->js("\$dispatch('close-modal', 'change-status')");
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
            $this->dispatchOpportunityLifecycleChanged();
        } catch (AuthorizationException) {
            session()->flash('error', 'You do not have permission to perform this action.');
        } catch (EventNotValid $e) {
            session()->flash('error', $e->getMessage() ?: 'This action could not be completed.');
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first() ?? 'This action could not be completed.');
        }
    }

    protected function dispatchOpportunityLifecycleChanged(): void
    {
        $opportunity = $this->opportunity->fresh();

        if ($opportunity === null) {
            return;
        }

        $editable = Gate::allows('opportunities.edit') && ! $opportunity->statusEnum()->isClosed();
        $dealTotalRaw = $opportunity->deal_total !== null
            ? $opportunity->formatMoneyCost('deal_total')
            : '';

        $this->dispatch(
            'opportunity-lifecycle-changed',
            editable: $editable,
            fieldsEditable: $editable && ! $opportunity->pricingFrozen(),
            pricingFrozen: $opportunity->pricingFrozen(),
            priceLocked: $opportunity->hasLocks(),
            hasDealPrice: $opportunity->deal_total !== null,
            dealTotalRaw: $dealTotalRaw,
            chargeTotalMinor: (int) ($opportunity->charge_total ?? 0),
            dealTotalMinor: $opportunity->deal_total !== null ? (int) $opportunity->deal_total : null,
            cacheToken: $opportunity->state->value.':'.$opportunity->status,
        );

        $browserPayload = json_encode([
            'editable' => $editable,
            'fieldsEditable' => $editable && ! $opportunity->pricingFrozen(),
            'pricingFrozen' => $opportunity->pricingFrozen(),
            'priceLocked' => $opportunity->hasLocks(),
            'hasDealPrice' => $opportunity->deal_total !== null,
            'dealTotalRaw' => $dealTotalRaw,
            'chargeTotalMinor' => (int) ($opportunity->charge_total ?? 0),
            'dealTotalMinor' => $opportunity->deal_total !== null ? (int) $opportunity->deal_total : null,
            'cacheToken' => $opportunity->state->value.':'.$opportunity->status,
        ], JSON_THROW_ON_ERROR);

        $this->js(sprintf(
            'window.dispatchEvent(new CustomEvent(%s, { detail: %s, bubbles: true }))',
            json_encode('opportunity-lifecycle-changed'),
            $browserPayload,
        ));
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
            'convertModalCopy' => $this->convertModalCopy(),
            'confirmModalCopy' => $this->confirmModalCopy(),
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

            $this->describeAction($opportunity, 'unlock_locks', $hasLocks ? 'Unlock price' : 'Lock price', 'opportunities.unlock_rates', null,
                statePrecondition: fn (): ?array => (! $hasLocks && $opportunity->deal_total !== null)
                    ? ['Clear the deal price before locking price.', 'deal_price_active']
                    : null),

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
        return app(OpportunityActionDescriber::class)
            ->describe($opportunity, $key, $label, $permission, $transition, $statePrecondition);
    }

    /**
     * The revert-to-quotation state precondition: must be an open Order with no
     * dispatch history. Delegates to {@see OpportunityActionDescriber}.
     *
     * @return array{0: string, 1: string}|null
     */
    protected function revertToQuotationPrecondition(Opportunity $opportunity, bool $isOrder, bool $isClosed): ?array
    {
        return app(OpportunityActionDescriber::class)
            ->revertToQuotationPrecondition($opportunity, $isOrder, $isClosed);
    }
}
