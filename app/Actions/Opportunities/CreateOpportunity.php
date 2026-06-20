<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\OpportunityData;
use App\Enums\MembershipType;
use App\Models\Address;
use App\Models\Member;
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

        // Authoritative customer-type guard: the opportunity customer must be an
        // Organisation member. The DTO scopes the exists rule too, but ::rules() is
        // called context-free on the manual validate() path, so this is the real gate.
        $this->assertMemberIsOrganisation($data->member_id);

        // Authoritative IDOR guard: a supplied delivery/collection address must
        // belong to this opportunity's member. The DTO scopes the exists rule too,
        // but a caller can spoof or omit member_id past it, so this is the real gate.
        $this->assertAddressesBelongToMember($data->member_id, [
            'delivery_address_id' => $data->delivery_address_id,
            'collection_address_id' => $data->collection_address_id,
        ]);

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
                charge_starts_at: $data->charge_starts_at,
                charge_ends_at: $data->charge_ends_at,
                prep_starts_at: $data->prep_starts_at,
                prep_ends_at: $data->prep_ends_at,
                load_starts_at: $data->load_starts_at,
                load_ends_at: $data->load_ends_at,
                deliver_starts_at: $data->deliver_starts_at,
                deliver_ends_at: $data->deliver_ends_at,
                setup_starts_at: $data->setup_starts_at,
                setup_ends_at: $data->setup_ends_at,
                show_starts_at: $data->show_starts_at,
                show_ends_at: $data->show_ends_at,
                takedown_starts_at: $data->takedown_starts_at,
                takedown_ends_at: $data->takedown_ends_at,
                collect_starts_at: $data->collect_starts_at,
                collect_ends_at: $data->collect_ends_at,
                unload_starts_at: $data->unload_starts_at,
                unload_ends_at: $data->unload_ends_at,
                deprep_starts_at: $data->deprep_starts_at,
                deprep_ends_at: $data->deprep_ends_at,
                ordered_at: $data->ordered_at,
                quote_invalid_at: $data->quote_invalid_at,
                use_chargeable_days: $data->use_chargeable_days,
                chargeable_days: $data->chargeable_days,
                open_ended_rental: $data->open_ended_rental,
                customer_collecting: $data->customer_collecting,
                customer_returning: $data->customer_returning,
                delivery_instructions: $data->delivery_instructions,
                collection_instructions: $data->collection_instructions,
                delivery_address_id: $data->delivery_address_id,
                collection_address_id: $data->collection_address_id,
                rating: $data->rating,
                charge_total: $data->charge_total,
                currency_code: $currency,
                exchange_rate: $this->resolveExchangeRate($currency),
                prices_include_tax: $data->prices_include_tax,
                tag_list: array_values($data->tag_list ?? []),
            );

            return $opportunityId;
        });

        $opportunity = Opportunity::query()->whereKey($opportunityId)->firstOrFail();

        // Persist any supplied custom field values (EAV) on the freshly projected
        // row. Custom fields live outside the event stream (the same as other
        // modules, e.g. Members), so they are synced here after the projection
        // exists rather than inside the genesis event.
        $opportunity->syncCustomFields($data->custom_fields ?? [], applyDefaults: true);

        return OpportunityData::fromModel($opportunity->load('customFieldValues'));
    }

    /**
     * Assert that every supplied address FK points at an {@see Address} owned by
     * the given member (polymorphic addressable_type = Member, addressable_id =
     * member_id). Closes the IDOR where an API caller targets another member's
     * address. A mismatch (including a non-Member-owned address, or any address when
     * no member is set) is surfaced as a 422 rather than silently dropped.
     *
     * @param  array<string, int|null>  $addressIds  field name => supplied id
     */
    private function assertAddressesBelongToMember(?int $memberId, array $addressIds): void
    {
        foreach ($addressIds as $field => $addressId) {
            if ($addressId === null) {
                continue;
            }

            $belongs = $memberId !== null && Address::query()
                ->whereKey($addressId)
                ->where('addressable_type', Member::class)
                ->where('addressable_id', $memberId)
                ->exists();

            if (! $belongs) {
                throw ValidationException::withMessages([
                    $field => ['The selected address does not belong to this opportunity\'s member.'],
                ]);
            }
        }
    }

    /**
     * Assert that the supplied opportunity customer (when one is set) is an
     * Organisation member. A Contact/User/Venue — or a missing member — is rejected
     * with a 422 so an opportunity cannot be created against a non-organisation
     * customer. A null member_id is allowed (the header customer is optional).
     */
    private function assertMemberIsOrganisation(?int $memberId): void
    {
        if ($memberId === null) {
            return;
        }

        $isOrganisation = Member::query()
            ->whereKey($memberId)
            ->where('membership_type', MembershipType::Organisation->value)
            ->exists();

        if (! $isOrganisation) {
            throw ValidationException::withMessages([
                'member_id' => ['The opportunity customer must be an organisation.'],
            ]);
        }
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
