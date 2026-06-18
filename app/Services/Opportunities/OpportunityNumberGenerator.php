<?php

namespace App\Services\Opportunities;

use App\Services\SequenceAllocator;

/**
 * Allocates the next zero-padded opportunity `number` (e.g. "0000000042").
 *
 * The number is allocated at event-fire time (in CreateOpportunity) from a named
 * {@see SequenceAllocator} sequence and baked into the OpportunityCreated event
 * payload, so a truncate + Verbs::replay() rebuild reproduces the identical number
 * (same replay-stability principle as the projection id). Allocation therefore
 * lives ONLY in the action — replay re-applies the stored payload and never calls
 * this generator.
 *
 * The sequence is store-scoped by default (one running number per store, matching
 * RMS behaviour where an opportunity is scoped to a single store); a null store or
 * the 'global' scope falls back to a single shared sequence. The pad width comes
 * from `signals.opportunities.number_pad`.
 */
class OpportunityNumberGenerator
{
    public function __construct(
        private readonly SequenceAllocator $allocator,
    ) {}

    /**
     * Allocate and format the next number for the given store.
     */
    public function next(?int $storeId): string
    {
        $value = $this->allocator->next($this->sequenceKey($storeId));

        $pad = (int) config('signals.opportunities.number_pad', 10);

        return str_pad((string) $value, $pad, '0', STR_PAD_LEFT);
    }

    /**
     * Resolve the sequence key. Store-scoped installs key the running number to
     * the store; a global scope (or a null store) shares one sequence.
     */
    private function sequenceKey(?int $storeId): string
    {
        $scope = config('signals.opportunities.number_scope', 'store');

        if ($scope === 'store' && $storeId !== null) {
            return "opportunity_number:store:{$storeId}";
        }

        return 'opportunity_number:global';
    }
}
