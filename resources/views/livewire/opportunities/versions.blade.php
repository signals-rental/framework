<?php

use App\Actions\Opportunities\AcceptVersion;
use App\Actions\Opportunities\ActivateVersion;
use App\Actions\Opportunities\ChangeVersionLabel;
use App\Actions\Opportunities\CreateVersion;
use App\Actions\Opportunities\DeclineVersion;
use App\Actions\Opportunities\DeleteVersion;
use App\Actions\Opportunities\DiffVersions;
use App\Actions\Opportunities\SendVersion;
use App\Data\Opportunities\ChangeVersionLabelData;
use App\Data\Opportunities\CreateVersionData;
use App\Data\Opportunities\VersionDiffData;
use App\Enums\OpportunityState;
use App\Enums\VersionStatus;
use App\Enums\VersionType;
use App\Livewire\Concerns\HasAuditTimeline;
use App\Models\ActionLog;
use App\Models\Opportunity;
use App\Models\OpportunityVersion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Livewire\Concerns\HasOpportunityActions;

/**
 * Opportunity Versions tab (M8-5) — the quote-versioning UI
 * (opportunity-lifecycle.md §8).
 *
 * Renders the opportunity's quote versions as a tree — revisions (the supersede
 * chain) and alternatives, the ACTIVE version marked — using the shared
 * `<x-signals.version-tree>` component. Every mutation calls the SAME version
 * action classes the API controller uses ({@see CreateVersion}, {@see
 * ActivateVersion}, {@see AcceptVersion}, {@see DeclineVersion}, {@see SendVersion},
 * {@see ChangeVersionLabel}, {@see DeleteVersion}) — each authorises internally and
 * runs the atomic Verbs commit; this component never self-HTTPs. Two versions can be
 * selected to diff via {@see DiffVersions}.
 *
 * Lifecycle actions are only offered where they are legal for the version's status
 * and the opportunity's state (a Quotation): the legality mirrors the validate()
 * guards on the underlying version events, so an illegal move is never surfaced and,
 * if raced, surfaces as a flashed 422 rather than a crash.
 *
 * The RIGHT panel is the opportunity's own lifecycle event history (B4) — the
 * model-event timeline as originally spec'd (opportunity-lifecycle.md §"event stream
 * provides full audit trail"). It is rendered READ-ONLY from the opportunity's
 * `action_logs` rows: every state-mutating Verbs event records one human-readable
 * audit row (action key + actor + timestamp + old→new) via {@see RecordsOpportunityAudit},
 * replay-stable and carrying the firing actor that the raw `verb_events.metadata`
 * does not round-trip after replay (M2). There is NO ability to add timeline entries.
 */
new #[Layout('components.layouts.app')] class extends Component
{
    use HasOpportunityActions;

    use HasAuditTimeline;

    public Opportunity $opportunity;

    /** Whether the actor may mutate versions (vs a read-only view). */
    public bool $canEdit = false;

    /** The version the rename modal is editing + its draft label. */
    public ?int $labelVersionId = null;

    public ?string $labelDraft = null;

    /** The version the decline modal is declining + its reason. */
    public ?int $declineVersionId = null;

    public ?string $declineReason = null;

    /** The two versions selected for the diff panel (projection ids). */
    public ?int $diffFromId = null;

    public ?int $diffToId = null;

    /** Create-version modal inputs. */
    public int $createType = 0;

    public ?int $createSourceId = null;

    public ?string $createLabel = null;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');

        $this->opportunity = $opportunity;
        $this->canEdit = Gate::allows('opportunities.edit');
        $this->createType = VersionType::Revision->value;
    }

    public function rendering(View $view): void
    {
        $view->title($this->opportunity->subject.' — Versions & Timeline');
    }

    /**
     * Open the create-version modal, defaulting the source to the active version.
     */
    public function openCreate(int $type): void
    {
        $this->createType = $type;
        $this->createSourceId = $this->opportunity->active_version_id > 0
            ? $this->opportunity->active_version_id
            : null;
        $this->createLabel = null;
    }

    /**
     * Create a revision or an alternative, cloning the source version's line items.
     */
    public function createVersion(): void
    {
        try {
            (new CreateVersion)($this->opportunity->refresh(), CreateVersionData::from([
                'version_type' => $this->createType,
                'label' => $this->createLabel,
                'source_version_id' => $this->createSourceId,
            ]));

            session()->flash('version_status', __('Version created.'));
            $this->resetCreateState();
            $this->dispatch('version-modal-close', name: 'create-version');
        } catch (AuthorizationException) {
            $this->flashDenied();
        } catch (ValidationException $e) {
            $this->flashValidation($e, __('Unable to create this version.'));
        }

        $this->opportunity->refresh();
    }

    /**
     * Switch the active version. The action handles the one-active flip, totals
     * re-roll, and the availability demand swap atomically.
     */
    public function activate(int $versionId): void
    {
        $this->run($versionId, fn (OpportunityVersion $version) => (new ActivateVersion)($version), __('Active version switched.'));
    }

    public function send(int $versionId): void
    {
        $this->run($versionId, fn (OpportunityVersion $version) => (new SendVersion)($version), __('Version marked as sent.'));
    }

    public function accept(int $versionId): void
    {
        $this->run($versionId, fn (OpportunityVersion $version) => (new AcceptVersion)($version), __('Version accepted.'));
    }

    /**
     * Open the decline modal (the reason is optional, captured before declining).
     */
    public function promptDecline(int $versionId): void
    {
        $this->declineVersionId = $versionId;
        $this->declineReason = null;
    }

    public function submitDecline(): void
    {
        if ($this->declineVersionId === null) {
            return;
        }

        $reason = $this->declineReason;
        $this->run($this->declineVersionId, fn (OpportunityVersion $version) => (new DeclineVersion)($version, $reason), __('Version declined.'));

        $this->declineVersionId = null;
        $this->declineReason = null;
        $this->dispatch('version-modal-close', name: 'decline-version');
    }

    /**
     * Open the rename modal seeded with the current label.
     */
    public function promptRename(int $versionId): void
    {
        $version = $this->findVersion($versionId);
        $this->labelVersionId = $versionId;
        $this->labelDraft = $version?->label;
    }

    public function submitRename(): void
    {
        if ($this->labelVersionId === null) {
            return;
        }

        $label = $this->labelDraft;
        $this->run($this->labelVersionId, fn (OpportunityVersion $version) => (new ChangeVersionLabel)($version, ChangeVersionLabelData::from(['label' => $label])), __('Version renamed.'));

        $this->labelVersionId = null;
        $this->labelDraft = null;
        $this->dispatch('version-modal-close', name: 'rename-version');
    }

    public function deleteVersion(int $versionId): void
    {
        $this->run($versionId, fn (OpportunityVersion $version) => (new DeleteVersion)($version), __('Version deleted.'));
    }

    /**
     * Run a single-version action, catching the auth/422 failures the action
     * classes raise (an illegal §8 move is a 422) and flashing the first message.
     *
     * @param  \Closure(OpportunityVersion): mixed  $action
     */
    protected function run(int $versionId, \Closure $action, string $success): void
    {
        try {
            $version = $this->findVersionOrFail($versionId);
            $action($version);
            session()->flash('version_status', $success);
        } catch (AuthorizationException) {
            $this->flashDenied();
        } catch (ValidationException $e) {
            $this->flashValidation($e, __('This action is not permitted.'));
        }

        $this->opportunity->refresh();
    }

    protected function findVersion(int $versionId): ?OpportunityVersion
    {
        return OpportunityVersion::query()
            ->where('opportunity_id', $this->opportunity->id)
            ->whereKey($versionId)
            ->first();
    }

    protected function findVersionOrFail(int $versionId): OpportunityVersion
    {
        return OpportunityVersion::query()
            ->where('opportunity_id', $this->opportunity->id)
            ->whereKey($versionId)
            ->firstOrFail();
    }

    protected function resetCreateState(): void
    {
        $this->createType = VersionType::Revision->value;
        $this->createSourceId = null;
        $this->createLabel = null;
    }

    protected function flashDenied(): void
    {
        session()->flash('error', __('You do not have permission to manage versions.'));
    }

    protected function flashValidation(ValidationException $e, string $fallback): void
    {
        session()->flash('error', collect($e->errors())->flatten()->first() ?? $fallback);
    }

    /**
     * Whether a version may legally be sent / accepted / declined / activated /
     * renamed / deleted, mirroring the validate() guards on the version events so
     * the UI only offers legal moves. All require the opportunity to be a Quotation.
     */
    public function isQuotation(): bool
    {
        return $this->opportunity->state === OpportunityState::Quotation;
    }

    public function canSend(OpportunityVersion $version): bool
    {
        return $this->isQuotation() && $version->status === VersionStatus::Draft;
    }

    public function canAcceptOrDecline(OpportunityVersion $version): bool
    {
        return $this->isQuotation()
            && in_array($version->status, [VersionStatus::Draft, VersionStatus::Sent], true);
    }

    public function canActivate(OpportunityVersion $version): bool
    {
        return $this->isQuotation() && ! $version->is_active;
    }

    public function canRename(OpportunityVersion $version): bool
    {
        return $this->isQuotation();
    }

    public function canDelete(OpportunityVersion $version, int $versionCount): bool
    {
        return $this->isQuotation() && ! $version->is_active && $versionCount > 1;
    }

    /**
     * The on-demand diff between the two selected versions, or null until both are
     * chosen. Computed fresh from the projections every render (never stored).
     */
    protected function diff(Collection $versions): ?VersionDiffData
    {
        if ($this->diffFromId === null || $this->diffToId === null || $this->diffFromId === $this->diffToId) {
            return null;
        }

        $from = $versions->firstWhere('id', $this->diffFromId);
        $to = $versions->firstWhere('id', $this->diffToId);

        if (! $from instanceof OpportunityVersion || ! $to instanceof OpportunityVersion) {
            return null;
        }

        try {
            return (new DiffVersions)($from, $to);
        } catch (AuthorizationException|ValidationException) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $versions = $this->opportunity->versions()
            ->with('author')
            ->orderBy('version_number')
            ->get();

        return [
            ...$this->opportunityActionData(),

            'versions' => $versions,
            'versionCount' => $versions->count(),
            'diff' => $this->diff($versions),
            // The opportunity's lifecycle event history (B4) — read-only, newest-first.
            'timeline' => $this->lifecycleTimeline(),
        ];
    }

    /**
     * The opportunity's own lifecycle event history, newest-first, shaped for the
     * read-only timeline render. Sourced from `action_logs` (each Verbs event records
     * one replay-stable audit row with the firing actor), capped at
     * {@see HasAuditTimeline::$auditTimelineLimit}.
     *
     * @return Collection<int, array{title: string, color: ?string, icon: string, actor: ?string, at: ?Carbon, detail: ?string}>
     */
    protected function lifecycleTimeline(): Collection
    {
        return ActionLog::query()
            ->with('user')
            ->forEntity($this->opportunity->getMorphClass(), (int) $this->opportunity->id)
            ->latest('created_at')
            ->latest('id')
            ->limit($this->auditTimelineLimit)
            ->get()
            ->map(fn (ActionLog $log): array => [
                'title' => $this->lifecycleLabel($log->action),
                'color' => $this->timelineColor($log->action),
                'icon' => $this->lifecycleIcon($log->action),
                'actor' => $log->user?->name,
                'at' => $log->created_at instanceof Carbon ? $log->created_at : null,
                'detail' => $this->lifecycleDetail($log),
            ])
            ->values();
    }

    /**
     * Human label for a lifecycle action key. A small explicit map covers the keys
     * whose default headline reads awkwardly; everything else falls back to a
     * headline of the suffix (e.g. `opportunity.item_added` → "Item added").
     */
    protected function lifecycleLabel(string $action): string
    {
        $suffix = Str::after($action, 'opportunity.');

        return $this->lifecycleLabelMap()[$suffix]
            ?? Str::of($suffix)->replace('_', ' ')->ucfirst()->toString();
    }

    /**
     * Explicit suffix → label overrides for the lifecycle timeline.
     *
     * @return array<string, string>
     */
    protected function lifecycleLabelMap(): array
    {
        return [
            'created' => __('Opportunity created'),
            'updated' => __('Details updated'),
            'quoted' => __('Converted to quotation'),
            'converted_to_order' => __('Converted to order'),
            'status_changed' => __('Status changed'),
            'status_promoted' => __('Status promoted'),
            'reverted_to_quotation' => __('Reverted to quotation'),
            'reinstated' => __('Reinstated'),
            'restored' => __('Restored'),
            'deleted' => __('Deleted'),
            'cloned' => __('Cloned'),
            'item_added' => __('Item added'),
            'item_removed' => __('Item removed'),
            'item_quantity_changed' => __('Item quantity changed'),
            'item_price_overridden' => __('Item price overridden'),
            'item_discount_set' => __('Item discount set'),
            'item_dates_changed' => __('Item dates changed'),
            'item_substituted' => __('Item substituted'),
            'items_reordered' => __('Items reordered'),
            'deal_price_set' => __('Deal price set'),
            'deal_price_cleared' => __('Deal price cleared'),
            'cost_added' => __('Cost added'),
            'cost_updated' => __('Cost updated'),
            'cost_removed' => __('Cost removed'),
            'asset_allocated' => __('Asset allocated'),
            'asset_deallocated' => __('Asset de-allocated'),
            'asset_prepared' => __('Asset prepared'),
            'asset_dispatched' => __('Asset dispatched'),
            'asset_on_hire' => __('Asset on hire'),
            'asset_returned' => __('Asset returned'),
            'asset_checked' => __('Asset checked'),
            'asset_substituted' => __('Asset substituted'),
            'bulk_dispatched' => __('Bulk dispatched'),
            'bulk_returned' => __('Bulk returned'),
            'version_created' => __('Version created'),
            'version_activated' => __('Version activated'),
            'version_sent' => __('Version sent'),
            'version_accepted' => __('Version accepted'),
            'version_declined' => __('Version declined'),
            'version_relabelled' => __('Version relabelled'),
            'version_deleted' => __('Version deleted'),
            'version_superseded' => __('Version superseded'),
        ];
    }

    /**
     * Flux icon name for a lifecycle action, grouped by domain (status, items,
     * assets/dispatch, versions, lifecycle), for at-a-glance scanning.
     */
    protected function lifecycleIcon(string $action): string
    {
        $suffix = Str::after($action, 'opportunity.');

        return match (true) {
            $suffix === 'created' => 'sparkles',
            $suffix === 'deleted' => 'trash',
            in_array($suffix, ['converted_to_order', 'quoted', 'status_changed', 'status_promoted', 'reverted_to_quotation', 'reinstated', 'restored', 'cloned'], true) => 'arrow-path-rounded-square',
            in_array($suffix, ['asset_dispatched', 'bulk_dispatched', 'asset_on_hire'], true) => 'truck',
            in_array($suffix, ['asset_returned', 'bulk_returned', 'asset_checked'], true) => 'arrow-uturn-left',
            Str::startsWith($suffix, 'asset_') => 'cube',
            Str::startsWith($suffix, 'item') => 'list-bullet',
            Str::startsWith($suffix, 'version_') => 'document-duplicate',
            Str::contains($suffix, ['price', 'cost', 'deal']) => 'currency-pound',
            default => 'clock',
        };
    }

    /**
     * A compact old→new detail line where the audit row carries a meaningful
     * before/after (e.g. a status change), else null. Scalar values only — nested
     * payloads are summarised as a key list rather than dumped.
     */
    protected function lifecycleDetail(ActionLog $log): ?string
    {
        $new = $log->new_values ?? [];
        $old = $log->old_values ?? [];

        if (array_key_exists('status', $new)) {
            $from = $this->scalarLabel($old['status'] ?? null);
            $to = $this->scalarLabel($new['status'] ?? null);

            if ($to !== null) {
                return $from !== null ? "{$from} → {$to}" : $to;
            }
        }

        return null;
    }

    /**
     * Render a scalar audit value as a short label, or null when not scalar/empty.
     */
    protected function scalarLabel(mixed $value): ?string
    {
        if ($value === null || $value === '' || is_array($value)) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? __('Yes') : __('No');
        }

        return Str::of((string) $value)->replace('_', ' ')->ucfirst()->toString();
    }

    /**
     * Opportunity lifecycle colours: creation/restoration green, deletion red,
     * order/dispatch transitions blue, returns amber.
     *
     * @return array<string, list<string>>
     */
    protected function timelineColorMap(): array
    {
        return [
            'green' => ['.created', '.restored', '.reinstated', '.version_accepted', '.asset_returned'],
            'red' => ['.deleted', '.version_declined', '.item_removed', '.asset_deallocated'],
            'blue' => ['.converted_to_order', '.status_promoted', '.asset_dispatched', '.bulk_dispatched', '.asset_on_hire', '.version_activated'],
            'amber' => ['.status_changed', '.reverted_to_quotation', '.bulk_returned'],
        ];
    }
}; ?>

<section class="w-full">
    @include('livewire.opportunities.partials.opportunity-header', ['opportunity' => $opportunity, 'subpage' => 'Versions & Timeline', 'showActions' => true, 'canChangeStatus' => $canChangeStatus])
    @include('livewire.opportunities.partials.opportunity-tabs', ['opportunity' => $opportunity, 'activeTab' => 'versions'])

    @php
        $statusBadge = fn (\App\Enums\VersionStatus $status): string => match ($status) {
            \App\Enums\VersionStatus::Accepted => 's-badge-green',
            \App\Enums\VersionStatus::Declined => 's-badge-red',
            \App\Enums\VersionStatus::Superseded => 's-badge-zinc',
            \App\Enums\VersionStatus::Sent => 's-badge-blue',
            \App\Enums\VersionStatus::Draft => 's-badge-amber',
        };
    @endphp

    <div class="flex-1 space-y-6 px-6 py-4 max-md:px-5 max-sm:px-3">

        @if(session('error'))
            <x-signals.alert type="danger">{{ session('error') }}</x-signals.alert>
        @endif
        @if(session('version_status'))
            <x-signals.alert type="success">{{ session('version_status') }}</x-signals.alert>
        @endif

        @unless($this->isQuotation())
            <x-signals.alert type="info">
                {{ __('Versions can only be edited while the opportunity is a Quotation. They are read-only in the current state.') }}
            </x-signals.alert>
        @endunless

        {{-- ============================================================ --}}
        {{--  TWO-COLUMN LAYOUT — versions LEFT, timeline RIGHT.           --}}
        {{--  Collapses to a single stacked column on small screens.      --}}
        {{-- ============================================================ --}}
        <div class="grid grid-cols-[1fr_minmax(320px,28rem)] items-start gap-6 max-lg:grid-cols-1">

        {{-- ---------- LEFT: version tree + diff ---------- --}}
        <div class="min-w-0 space-y-6">

        {{-- ============================================================ --}}
        {{--  VERSION TREE (revisions + alternatives, active marked)       --}}
        {{-- ============================================================ --}}
        <x-signals.panel title="Quote Versions">
            @if($canEdit && $this->isQuotation())
                <x-slot:headerActions>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                wire:click="openCreate({{ \App\Enums\VersionType::Revision->value }})"
                                x-on:click="$dispatch('open-modal', 'create-version')"
                                class="s-btn s-btn-sm s-btn-outline-blue">
                            <flux:icon.plus class="!size-3.5" /> {{ __('New revision') }}
                        </button>
                        <button type="button"
                                wire:click="openCreate({{ \App\Enums\VersionType::Alternative->value }})"
                                x-on:click="$dispatch('open-modal', 'create-version')"
                                class="s-btn s-btn-sm s-btn-outline-blue">
                            <flux:icon.plus class="!size-3.5" /> {{ __('New alternative') }}
                        </button>
                    </div>
                </x-slot:headerActions>
            @endif

            <div>
                @if($versions->isEmpty())
                    <x-signals.empty
                        title="{{ __('No versions yet') }}"
                        description="{{ __('This opportunity has not been split into quote versions. Create a revision or alternative to start tracking versions.') }}">
                        <x-slot:icon><flux:icon.document-duplicate class="!size-7" /></x-slot:icon>
                    </x-signals.empty>
                @else
                    <x-signals.version-tree>
                        @foreach($versions as $version)
                            @php
                                $data = \App\Data\Opportunities\OpportunityVersionData::fromModel($version);
                            @endphp
                            <div wire:key="version-{{ $version->id }}">
                                <div class="s-vt-node {{ $version->is_active ? 's-vt-node-active' : '' }}">
                                    <div class="s-vt-node-header">
                                        <span class="s-vt-version-label">V{{ $version->version_number }}</span>
                                        @if($version->label)
                                            <span class="s-vt-version-name">{{ $version->label }}</span>
                                        @endif
                                        <span class="s-badge s-badge-zinc s-badge-outline">{{ $version->version_type->label() }}</span>
                                        <span class="s-badge {{ $statusBadge($version->status) }}">{{ $version->status->label() }}</span>
                                        @if($version->is_active)
                                            <span class="s-vt-confirmed-marker">{{ __('ACTIVE') }}</span>
                                        @endif
                                        <span class="s-vt-version-total">{{ $data->charge_total }}</span>
                                    </div>

                                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <span class="s-vt-version-date">
                                            {{ optional($version->created_at)->format('d M Y H:i') }}
                                        </span>
                                        @if($version->author)
                                            <span class="text-[12px] text-[var(--text-muted)]">{{ __('by') }} {{ $version->author->name }}</span>
                                        @endif
                                        @if($version->parent_version_id)
                                            <span class="text-[12px] text-[var(--text-muted)]">{{ __('revises') }} #{{ $version->parent_version_id }}</span>
                                        @endif
                                        @if($version->status === \App\Enums\VersionStatus::Declined && $version->decline_reason)
                                            <span class="text-[12px] text-[var(--red)]">{{ __('Declined') }}: {{ $version->decline_reason }}</span>
                                        @endif
                                    </div>

                                    @if($canEdit)
                                        <div class="mt-3 flex flex-wrap items-center gap-1 border-t border-[var(--border)] pt-2">
                                            @if($this->canActivate($version))
                                                <button type="button" wire:click="activate({{ $version->id }})"
                                                        wire:confirm="{{ __('Make this the active version? Reserved demand and the opportunity totals will swap to it.') }}"
                                                        class="s-btn s-btn-xs s-btn-outline-green">{{ __('Make active') }}</button>
                                            @endif
                                            @if($this->canSend($version))
                                                <button type="button" wire:click="send({{ $version->id }})"
                                                        class="s-btn s-btn-xs s-btn-outline-blue">{{ __('Send') }}</button>
                                            @endif
                                            @if($this->canAcceptOrDecline($version))
                                                <button type="button" wire:click="accept({{ $version->id }})"
                                                        class="s-btn s-btn-xs s-btn-outline-green">{{ __('Accept') }}</button>
                                                <button type="button" wire:click="promptDecline({{ $version->id }})"
                                                        x-on:click="$dispatch('open-modal', 'decline-version')"
                                                        class="s-btn s-btn-xs s-btn-ghost">{{ __('Decline') }}</button>
                                            @endif
                                            @if($this->canRename($version))
                                                <button type="button" wire:click="promptRename({{ $version->id }})"
                                                        x-on:click="$dispatch('open-modal', 'rename-version')"
                                                        class="s-btn s-btn-xs s-btn-ghost">{{ __('Rename') }}</button>
                                            @endif
                                            @if($this->canDelete($version, $versionCount))
                                                <button type="button" wire:click="deleteVersion({{ $version->id }})"
                                                        wire:confirm="{{ __('Delete this version? Its line items and demands are removed.') }}"
                                                        class="s-btn s-btn-xs s-btn-ghost text-[var(--red)]">{{ __('Delete') }}</button>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </x-signals.version-tree>
                @endif
            </div>
        </x-signals.panel>

        {{-- ============================================================ --}}
        {{--  VERSION DIFF (select two versions → field/item delta)        --}}
        {{-- ============================================================ --}}
        @if($versions->count() >= 2)
            <x-signals.panel title="Compare Versions">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label for="diff-from" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('From') }}</label>
                        <select id="diff-from" wire:model.live="diffFromId" class="s-input">
                            <option value="">{{ __('Select a version') }}</option>
                            @foreach($versions as $version)
                                <option value="{{ $version->id }}" wire:key="from-{{ $version->id }}">
                                    V{{ $version->version_number }}{{ $version->label ? ' — '.$version->label : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="diff-to" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('To') }}</label>
                        <select id="diff-to" wire:model.live="diffToId" class="s-input">
                            <option value="">{{ __('Select a version') }}</option>
                            @foreach($versions as $version)
                                <option value="{{ $version->id }}" wire:key="to-{{ $version->id }}">
                                    V{{ $version->version_number }}{{ $version->label ? ' — '.$version->label : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if($diff !== null)
                    <div class="mt-4 space-y-4">
                        <div class="flex flex-wrap items-center gap-4 text-sm">
                            <span class="text-[var(--text-muted)]">{{ __('V') }}{{ $diff->source_version_number }} {{ $diff->source_total }}</span>
                            <flux:icon.arrow-right class="!size-4 text-[var(--text-muted)]" />
                            <span class="text-[var(--text-muted)]">{{ __('V') }}{{ $diff->target_version_number }} {{ $diff->target_total }}</span>
                            <span class="s-badge {{ str_starts_with($diff->net_change, '-') ? 's-badge-red' : 's-badge-green' }}">
                                {{ __('Net') }} {{ $diff->net_change }}
                            </span>
                        </div>

                        @if(empty($diff->added) && empty($diff->removed) && empty($diff->changed))
                            <p class="text-sm text-[var(--text-muted)]">{{ __('These two versions have identical line items.') }}</p>
                        @else
                            <x-signals.table-wrap>
                                <table class="s-table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Change') }}</th>
                                            <th>{{ __('Line') }}</th>
                                            <th class="text-right">{{ __('Qty') }}</th>
                                            <th class="text-right">{{ __('Unit') }}</th>
                                            <th class="text-right">{{ __('Total Δ') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($diff->added as $i => $line)
                                            <tr wire:key="diff-add-{{ $i }}">
                                                <td><span class="s-badge s-badge-green">{{ __('Added') }}</span></td>
                                                <td class="font-medium">{{ $line->name }}</td>
                                                <td class="text-right" style="font-family: var(--font-mono);">{{ $line->target_quantity }}</td>
                                                <td class="text-right" style="font-family: var(--font-mono);">{{ $line->target_unit_price }}</td>
                                                <td class="text-right text-[var(--green)]" style="font-family: var(--font-mono);">{{ $line->total_delta }}</td>
                                            </tr>
                                        @endforeach
                                        @foreach($diff->changed as $i => $line)
                                            <tr wire:key="diff-chg-{{ $i }}">
                                                <td><span class="s-badge s-badge-amber">{{ __('Changed') }}</span></td>
                                                <td class="font-medium">{{ $line->name }}</td>
                                                <td class="text-right" style="font-family: var(--font-mono);">{{ $line->source_quantity }} → {{ $line->target_quantity }}</td>
                                                <td class="text-right" style="font-family: var(--font-mono);">{{ $line->source_unit_price }} → {{ $line->target_unit_price }}</td>
                                                <td class="text-right {{ str_starts_with($line->total_delta, '-') ? 'text-[var(--red)]' : 'text-[var(--green)]' }}" style="font-family: var(--font-mono);">{{ $line->total_delta }}</td>
                                            </tr>
                                        @endforeach
                                        @foreach($diff->removed as $i => $line)
                                            <tr wire:key="diff-rem-{{ $i }}">
                                                <td><span class="s-badge s-badge-red">{{ __('Removed') }}</span></td>
                                                <td class="font-medium">{{ $line->name }}</td>
                                                <td class="text-right" style="font-family: var(--font-mono);">{{ $line->source_quantity }}</td>
                                                <td class="text-right" style="font-family: var(--font-mono);">{{ $line->source_unit_price }}</td>
                                                <td class="text-right text-[var(--red)]" style="font-family: var(--font-mono);">{{ $line->total_delta }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </x-signals.table-wrap>
                        @endif
                    </div>
                @else
                    <p class="mt-4 text-sm text-[var(--text-muted)]">{{ __('Select two different versions to compare their line items and totals.') }}</p>
                @endif
            </x-signals.panel>
        @endif

        </div>{{-- /LEFT column --}}

        {{-- ---------- RIGHT: lifecycle event timeline (read-only) ---------- --}}
        <div class="min-w-0 space-y-6">

        {{-- ============================================================ --}}
        {{--  TIMELINE (B4) — the opportunity's own lifecycle event        --}}
        {{--  history (action_logs), READ-ONLY, newest-first. No add.      --}}
        {{-- ============================================================ --}}
        <x-signals.panel title="{{ __('Timeline') }}">
            @if($timeline->isEmpty())
                <x-signals.empty
                    title="{{ __('No history yet') }}"
                    description="{{ __('The opportunity\'s lifecycle events — status changes, line items, dispatch and returns — appear here as they happen.') }}">
                    <x-slot:icon><flux:icon.clock class="!size-7" /></x-slot:icon>
                </x-signals.empty>
            @else
                <x-signals.timeline>
                    @foreach($timeline as $event)
                        <x-signals.timeline-item
                            :color="$event['color']"
                            :title="$event['title']"
                            wire:key="timeline-{{ $loop->index }}"
                        >
                            <x-slot:icon>
                                <flux:icon :icon="$event['icon']" class="!size-3.5" />
                            </x-slot:icon>

                            <div class="flex flex-col gap-0.5">
                                @if($event['detail'])
                                    <span class="font-medium text-[var(--text-default)]">{{ $event['detail'] }}</span>
                                @endif
                                <span class="text-[12px] text-[var(--text-muted)]">
                                    @if($event['at'])@localdatetime($event['at'])@endif
                                    @if($event['actor'])
                                        · {{ __('by') }} {{ $event['actor'] }}
                                    @endif
                                </span>
                            </div>
                        </x-signals.timeline-item>
                    @endforeach
                </x-signals.timeline>
            @endif
        </x-signals.panel>

        </div>{{-- /RIGHT column --}}

        </div>{{-- /two-column grid --}}
    </div>

    {{-- ============================================================ --}}
    {{--  CREATE-VERSION MODAL                                         --}}
    {{-- ============================================================ --}}
    <x-signals.modal name="create-version" title="{{ __('Create version') }}"
        x-on:version-modal-close.window="if ($event.detail?.name === 'create-version') open = false">
        <div class="space-y-3">
            <div>
                <label for="create-type" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Type') }}</label>
                <select id="create-type" wire:model="createType" class="s-input w-full">
                    <option value="{{ \App\Enums\VersionType::Revision->value }}">{{ __('Revision (supersedes its parent)') }}</option>
                    <option value="{{ \App\Enums\VersionType::Alternative->value }}">{{ __('Alternative (coexists)') }}</option>
                </select>
            </div>
            <div>
                <label for="create-label" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Label (optional)') }}</label>
                <input id="create-label" type="text" wire:model="createLabel" class="s-input w-full" placeholder="{{ __('e.g. Budget option') }}" />
            </div>
            @if($versions->isNotEmpty())
                <div>
                    <label for="create-source" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Clone line items from') }}</label>
                    <select id="create-source" wire:model="createSourceId" class="s-input w-full">
                        <option value="">{{ __('Active version') }}</option>
                        @foreach($versions as $version)
                            <option value="{{ $version->id }}" wire:key="src-{{ $version->id }}">
                                V{{ $version->version_number }}{{ $version->label ? ' — '.$version->label : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        <x-slot:footer>
            <button type="button" x-on:click="$dispatch('close-modal', 'create-version')" class="s-btn s-btn-ghost">{{ __('Cancel') }}</button>
            <button type="button" wire:click="createVersion" class="s-btn s-btn-primary">{{ __('Create version') }}</button>
        </x-slot:footer>
    </x-signals.modal>

    {{-- ============================================================ --}}
    {{--  DECLINE MODAL                                                --}}
    {{-- ============================================================ --}}
    <x-signals.modal name="decline-version" title="{{ __('Decline version') }}"
        x-on:version-modal-close.window="if ($event.detail?.name === 'decline-version') open = false">
        <div>
            <label for="decline-reason" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Reason (optional)') }}</label>
            <textarea id="decline-reason" wire:model="declineReason" rows="3" class="s-input w-full"></textarea>
        </div>

        <x-slot:footer>
            <button type="button" x-on:click="$dispatch('close-modal', 'decline-version')" class="s-btn s-btn-ghost">{{ __('Dismiss') }}</button>
            <button type="button" wire:click="submitDecline" class="s-btn s-btn-danger">{{ __('Decline version') }}</button>
        </x-slot:footer>
    </x-signals.modal>

    {{-- ============================================================ --}}
    {{--  RENAME MODAL                                                 --}}
    {{-- ============================================================ --}}
    <x-signals.modal name="rename-version" title="{{ __('Rename version') }}"
        x-on:version-modal-close.window="if ($event.detail?.name === 'rename-version') open = false">
        <div>
            <label for="rename-label" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Label') }}</label>
            <input id="rename-label" type="text" wire:model="labelDraft" class="s-input w-full" placeholder="{{ __('Leave blank to clear') }}" />
        </div>

        <x-slot:footer>
            <button type="button" x-on:click="$dispatch('close-modal', 'rename-version')" class="s-btn s-btn-ghost">{{ __('Cancel') }}</button>
            <button type="button" wire:click="submitRename" class="s-btn s-btn-primary">{{ __('Save label') }}</button>
        </x-slot:footer>
    </x-signals.modal>
    @include('livewire.opportunities.partials.opportunity-action-modals')
</section>
