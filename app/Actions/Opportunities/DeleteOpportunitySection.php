<?php

namespace App\Actions\Opportunities;

use App\Events\AuditableEvent;
use App\Models\OpportunitySection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Deletes a custom line-item grouping (section).
 *
 * The `opportunity_items.section_id` FK is nullOnDelete, so any lines assigned to
 * this section are dropped back to automatic product-group grouping rather than
 * removed. The deletion is a plain Eloquent delete — sections are not
 * event-sourced (M8-3 grouping decision) — so the event-sourced line projection
 * is untouched beyond the database nulling section_id.
 */
class DeleteOpportunitySection
{
    public function __invoke(OpportunitySection $section): void
    {
        Gate::authorize('opportunities.edit');

        DB::transaction(function () use ($section): void {
            event(new AuditableEvent(
                $section,
                'opportunity_section.deleted',
                oldValues: [
                    'name' => $section->name,
                    'sort_order' => $section->sort_order,
                ],
            ));

            $section->delete();
        });
    }
}
