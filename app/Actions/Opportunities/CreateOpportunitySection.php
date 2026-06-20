<?php

namespace App\Actions\Opportunities;

use App\Data\Opportunities\CreateOpportunitySectionData;
use App\Data\Opportunities\OpportunitySectionData;
use App\Events\AuditableEvent;
use App\Models\Opportunity;
use App\Models\OpportunitySection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Creates a custom line-item grouping (section) on an opportunity.
 *
 * Sections are PLAIN, non-event-sourced rows (M8-3 grouping decision) — they are
 * created via a normal Eloquent insert, never a Verbs event, so a Verbs replay of
 * the opportunity stream never touches them. An AuditableEvent is fired for parity
 * with the event-sourced mutations (live-auth actor, like other plain actions).
 *
 * An optional `parent_id` nests the new section under another section, which must
 * belong to the SAME opportunity (a foreign parent throws a validation error).
 */
class CreateOpportunitySection
{
    public function __invoke(Opportunity $opportunity, CreateOpportunitySectionData $data): OpportunitySectionData
    {
        Gate::authorize('opportunities.edit');

        return DB::transaction(function () use ($opportunity, $data): OpportunitySectionData {
            if ($data->parent_id !== null) {
                $parent = OpportunitySection::query()->whereKey($data->parent_id)->first();

                if ($parent === null || $parent->opportunity_id !== $opportunity->id) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'The parent section does not belong to this opportunity.',
                    ]);
                }
            }

            $section = OpportunitySection::create([
                'opportunity_id' => $opportunity->id,
                'parent_id' => $data->parent_id,
                'name' => $data->name,
                'sort_order' => $data->sort_order,
            ]);

            event(new AuditableEvent($section, 'opportunity_section.created'));

            return OpportunitySectionData::fromModel($section);
        });
    }
}
