<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\OpportunityData;
use App\Models\Opportunity;
use App\Services\CurrencyService;
use App\Services\Opportunities\OpportunityNumberGenerator;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Opportunities\OpportunityCreated;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Creates a new opportunity as a Draft via the OpportunityCreated event,
 * committing the event and its projection atomically.
 */
class CreateOpportunity
{
    use CommitsVerbsEvents;

    public function __invoke(CreateOpportunityData $data): OpportunityData
    {
        Gate::authorize('opportunities.create');

        $opportunityId = $this->commitVerbs(function () use ($data): int {
            // Allocate the replay-stable small PK and bake it into the event so a
            // truncate + Verbs::replay() rebuild reproduces the identical id.
            // Allocation lives only here — replay re-applies the stored event with
            // its baked-in opportunity_id and never calls this action.
            $opportunityId = app(SequenceAllocator::class)->next('opportunities');

            // Allocate the zero-padded RMS number at fire-time and bake it into
            // the event so replay reproduces the identical number (same
            // replay-stability principle as the projection id).
            $number = app(OpportunityNumberGenerator::class)->next($data->store_id);

            // Resolve the currency and its base-currency exchange rate HERE and bake
            // both into the event, so the genesis apply() stays a pure,
            // replay-deterministic state projection (no settings()/CurrencyService
            // read). The rate is snapshotted at creation and survives replay.
            $currency = $this->resolveCurrency($data->currency);

            OpportunityCreated::fire(
                opportunity_id: $opportunityId,
                number: $number,
                subject: $data->subject,
                member_id: $data->member_id,
                store_id: $data->store_id,
                owned_by: $data->owned_by,
                venue_id: $data->venue_id,
                reference: $data->reference,
                description: $data->description,
                external_description: $data->external_description,
                starts_at: $data->starts_at,
                ends_at: $data->ends_at,
                charge_total: $data->charge_total,
                currency_code: $currency,
                exchange_rate: $this->resolveExchangeRate($currency),
                prices_include_tax: $data->prices_include_tax,
            );

            return $opportunityId;
        });

        return OpportunityData::fromModel(
            Opportunity::query()->whereKey($opportunityId)->firstOrFail(),
        );
    }

    /**
     * Resolve the opportunity currency at fire-time: the requested currency when
     * one was supplied, else the company base-currency setting. Performed here (not
     * in OpportunityCreated::apply()) so the genesis apply() stays a pure,
     * replay-deterministic state projection with no settings() read.
     */
    private function resolveCurrency(?string $currency): string
    {
        if ($currency !== null && $currency !== '') {
            return $currency;
        }

        return settings('company.base_currency', 'GBP');
    }

    /**
     * Snapshot the exchange rate FROM the opportunity currency TO the company base
     * currency at creation time, baked into the event payload (replay-stable).
     *
     * Same-currency short-circuits to exactly '1' without a lookup. Otherwise the
     * rate is resolved via {@see CurrencyService} (which honours direct, inverse and
     * triangulated rates). The service throws when no rate exists; that is
     * surfaced as a 422 so a non-base opportunity cannot be created with a bogus
     * (defaulted) rate.
     */
    private function resolveExchangeRate(string $currency): string
    {
        $base = settings('company.base_currency', 'GBP');

        if (! is_string($base) || $base === '' || $currency === $base) {
            return '1';
        }

        try {
            return app(CurrencyService::class)->getRate($currency, $base);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'currency' => [
                    "No exchange rate is configured from {$currency} to the base currency {$base}.",
                ],
            ]);
        }
    }
}
