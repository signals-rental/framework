<?php

namespace App\Actions\Opportunities;

use App\Data\Opportunities\OpportunitySectionData;
use App\Data\Opportunities\ReorderOpportunitySectionsData;
use App\Events\AuditableEvent;
use App\Models\Opportunity;
use App\Models\OpportunitySection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Reorders an opportunity's custom line-item groupings (sections).
 *
 * Each section's `sort_order` is set to its 0-based index in the supplied id
 * list. All ids must belong to the target opportunity; an unknown or foreign id
 * throws a validation error. Plain Eloquent writes — sections are not
 * event-sourced (M8-3 grouping decision).
 *
 * @return array<int, OpportunitySectionData> the reordered sections, in new order
 */
class ReorderOpportunitySections
{
    /**
     * @return array<int, OpportunitySectionData>
     */
    public function __invoke(Opportunity $opportunity, ReorderOpportunitySectionsData $data): array
    {
        Gate::authorize('opportunities.edit');

        return DB::transaction(function () use ($opportunity, $data): array {
            /** @var Collection<int, OpportunitySection> $sections */
            $sections = OpportunitySection::query()
                ->where('opportunity_id', $opportunity->id)
                ->get()
                ->keyBy('id');

            foreach ($data->section_ids as $sectionId) {
                if (! $sections->has($sectionId)) {
                    throw ValidationException::withMessages([
                        'section_ids' => 'One or more sections do not belong to this opportunity.',
                    ]);
                }
            }

            $ordered = [];

            foreach ($data->section_ids as $index => $sectionId) {
                /** @var OpportunitySection $section */
                $section = $sections->get($sectionId);

                if ($section->sort_order !== $index) {
                    $section->update(['sort_order' => $index]);
                }

                $ordered[] = OpportunitySectionData::fromModel($section);
            }

            event(new AuditableEvent(
                $opportunity,
                'opportunity_section.reordered',
                newValues: ['section_ids' => $data->section_ids],
            ));

            return $ordered;
        });
    }
}
