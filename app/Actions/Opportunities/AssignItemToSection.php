<?php

namespace App\Actions\Opportunities;

use App\Data\Opportunities\AssignItemToSectionData;
use App\Data\Opportunities\OpportunityItemData;
use App\Events\AuditableEvent;
use App\Models\OpportunityItem;
use App\Models\OpportunitySection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Assigns an opportunity line item to a custom grouping (section), or clears its
 * assignment (`section_id = null`).
 *
 * REPLAY-SAFETY INVARIANT (the crux of M8-3c): this writes ONLY the plain,
 * NON-event-sourced `opportunity_items.section_id` column via a plain
 * `saveQuietly()`. It deliberately does NOT fire a Verbs event — `section_id` is
 * absent from every item event, state, apply(), and handle(), so a Verbs replay
 * of the opportunity stream rebuilds the line projection WITHOUT erasing the
 * section assignment. An AuditableEvent is fired for audit parity, but the
 * section_id write itself is a plain projection update, never a stream mutation.
 *
 * A non-null target section must belong to the same opportunity as the line.
 */
class AssignItemToSection
{
    public function __invoke(OpportunityItem $item, AssignItemToSectionData $data): OpportunityItemData
    {
        Gate::authorize('opportunities.edit');

        return DB::transaction(function () use ($item, $data): OpportunityItemData {
            if ($data->section_id !== null) {
                $section = OpportunitySection::query()->whereKey($data->section_id)->first();

                if ($section === null || $section->opportunity_id !== $item->opportunity_id) {
                    throw ValidationException::withMessages([
                        'section_id' => 'The selected section does not belong to this opportunity.',
                    ]);
                }
            }

            $oldSectionId = $item->section_id;

            // Plain projection write — NOT a Verbs event. saveQuietly() avoids
            // model events; section_id is fillable but is never part of the event
            // stream, so this assignment survives a Verbs replay untouched.
            $item->section_id = $data->section_id;
            $item->saveQuietly();

            event(new AuditableEvent(
                $item,
                'opportunity.item_section_assigned',
                oldValues: ['section_id' => $oldSectionId],
                newValues: ['section_id' => $item->section_id],
            ));

            return OpportunityItemData::fromModel($item->refresh());
        });
    }
}
