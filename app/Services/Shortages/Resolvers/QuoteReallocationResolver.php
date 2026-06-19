<?php

namespace App\Services\Shortages\Resolvers;

use App\Enums\OpportunityState;
use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\Models\Demand;
use App\Services\Availability\OpportunityItemDemandResolver;
use App\ValueObjects\ResolutionOption;
use App\ValueObjects\ResolutionResult;
use App\ValueObjects\Shortage;

/**
 * Quote reallocation (shortage-resolution-sub-hires.md §4.1).
 *
 * Identifies stock held by unconfirmed quotes (opportunity state = Quotation)
 * overlapping the shortage's product/window so it can free up for a confirmed
 * order. The option is only offered when such competing quote-held demand
 * actually exists — there is nothing to reallocate from when no quote holds the
 * stock, so an always-present option would mislead the user.
 *
 * Generating concrete per-quote options and executing the release requires the
 * competing-quote line-item "unbooked" state transition and the owner-notification
 * event, which land with later milestones. Until then this resolver records the
 * reallocation INTENT as a pending resolution (noting the dependency) so the audit
 * trail and downstream consumers exist from the outset.
 */
class QuoteReallocationResolver extends AbstractShortageResolver
{
    /**
     * Per-shortage memo of whether a competing quote-held demand exists,
     * keyed by `productId:storeId:startIso:endIso`, so repeated
     * `canResolve()` / `getOptions()` calls within one detection pass perform at
     * most one existence query per distinct shortage window (avoids the N+1 the
     * resolver-registry's applicableTo()+getOptions() double-dispatch would
     * otherwise cause).
     *
     * @var array<string, bool>
     */
    private array $competingQuoteExists = [];

    public function key(): string
    {
        return 'reallocate';
    }

    public function name(): string
    {
        return 'Reallocate from quote';
    }

    public function priority(): int
    {
        return 10;
    }

    public function isAutoExecutable(): bool
    {
        return false;
    }

    public function canResolve(Shortage $shortage): bool
    {
        return $shortage->remainingShortfall() > 0 && $this->hasCompetingQuoteDemand($shortage);
    }

    /**
     * Whether an active demand held by an unconfirmed quote (opportunity state =
     * Quotation) overlaps the shortage's product/store window. Memoised per
     * distinct shortage window for the resolver instance's lifetime.
     *
     * The check matches on the demand's snapshotted `metadata.opportunity_state`
     * (written by {@see OpportunityItemDemandResolver::buildMetadata()})
     * so it is replay-stable and does not depend on the live opportunity row.
     * Both JSON drivers (Postgres JSONB, SQLite JSON-text) support the
     * `whereJsonContains`/`where('metadata->...')` predicate used here.
     */
    private function hasCompetingQuoteDemand(Shortage $shortage): bool
    {
        $key = implode(':', [
            $shortage->productId,
            $shortage->storeId,
            $shortage->startsAt->toIso8601String(),
            $shortage->endsAt->toIso8601String(),
        ]);

        return $this->competingQuoteExists[$key] ??= Demand::query()
            ->where('product_id', $shortage->productId)
            ->where('store_id', $shortage->storeId)
            ->active()
            ->overlapping($shortage->startsAt, $shortage->endsAt)
            ->where('metadata->opportunity_state', OpportunityState::Quotation->value)
            ->exists();
    }

    /**
     * @return list<ResolutionOption>
     */
    public function getOptions(Shortage $shortage): array
    {
        if (! $this->canResolve($shortage)) {
            return [];
        }

        return [
            new ResolutionOption(
                resolverKey: $this->key(),
                type: ShortageResolutionType::Reallocate,
                label: 'Release stock held by a competing quote',
                description: 'Free up to '.$shortage->remainingShortfall().' unit(s) from an overlapping unconfirmed quote. Awaits the quote-release transition (later milestone).',
                quantityResolved: $shortage->remainingShortfall(),
                isPartial: false,
                autoExecutable: false,
                metadata: ['pending_dependency' => 'quote_release'],
            ),
        ];
    }

    public function apply(Shortage $shortage, ResolutionOption $option): ResolutionResult
    {
        $resolution = $this->record(
            shortage: $shortage,
            quantityResolved: $shortage->remainingShortfall(),
            status: ShortageResolutionStatus::Pending,
            metadata: ['pending_dependency' => 'quote_release'],
        );

        return ResolutionResult::pending(
            $resolution,
            'Recorded reallocation intent; the quote-release transition is not yet available.',
            followupType: 'approval',
        );
    }

    protected function resolutionType(): ShortageResolutionType
    {
        return ShortageResolutionType::Reallocate;
    }
}
