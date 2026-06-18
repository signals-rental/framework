<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\UpdateOpportunityCostData;
use App\Models\OpportunityCost;
use App\Verbs\Events\Opportunities\CostUpdated;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Optional;

/**
 * Updates an opportunity cost via the CostUpdated event.
 *
 * Each provided field is merged over the cost's CURRENT values, so an omitted
 * field is left untouched; the merged result is fired as a complete payload (the
 * event's apply() stays a pure single-state assignment, replay-stable).
 */
class UpdateOpportunityCost
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityCost $cost, UpdateOpportunityCostData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $opportunity = $cost->opportunity()->firstOrFail();

        $this->commitVerbs(function () use ($cost, $data): void {
            CostUpdated::fire(
                opportunity_cost_id: $cost->state_id,
                description: $this->resolve($data->description, $cost->description),
                cost_type: $this->resolve($data->cost_type, $cost->cost_type->value),
                transaction_type: $this->resolve($data->transaction_type, $cost->transaction_type->value),
                amount: $this->resolve($data->amount, $cost->amount),
                quantity: $this->resolve($data->quantity, (string) $cost->quantity),
                is_optional: $this->resolve($data->is_optional, $cost->is_optional),
                sort_order: $this->resolve($data->sort_order, $cost->sort_order),
                notes: $this->resolve($data->notes, $cost->notes),
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['costs']));
    }

    /**
     * Return the provided value, or the current value when the field was omitted
     * (left as {@see Optional} by the partial-update DTO).
     *
     * @template TValue
     *
     * @param  TValue|Optional  $provided
     * @param  TValue  $current
     * @return TValue
     */
    private function resolve(mixed $provided, mixed $current): mixed
    {
        return $provided instanceof Optional ? $current : $provided;
    }
}
