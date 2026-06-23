<?php

namespace App\Actions\Opportunities;

use App\Data\Opportunities\AssignSectionParentData;
use App\Data\Opportunities\OpportunitySectionData;
use App\Events\AuditableEvent;
use App\Models\OpportunitySection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Re-parents a custom line-item grouping (section) — the drag-to-nest path in the
 * editor (a section dragged onto another section becomes its child; dragged to the
 * top level it is promoted).
 *
 * Plain Eloquent update — sections are not event-sourced (M8-3 grouping decision).
 * The reparent respects the same {@see CreateOpportunitySection::MAX_DEPTH} nesting
 * limit, measured against the deepest descendant of the moved section so a deep
 * subtree can never be pushed beyond the limit. A section may not be nested under
 * itself or any of its own descendants (a cycle), and the new parent must belong to
 * the same opportunity.
 */
class AssignSectionParent
{
    public function __invoke(OpportunitySection $section, AssignSectionParentData $data): OpportunitySectionData
    {
        Gate::authorize('opportunities.edit');

        return DB::transaction(function () use ($section, $data): OpportunitySectionData {
            $newParentId = $data->parent_id;

            if ($newParentId !== null) {
                if ($newParentId === $section->id) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'A section cannot be nested under itself.',
                    ]);
                }

                $parent = OpportunitySection::query()->whereKey($newParentId)->first();

                if ($parent === null || $parent->opportunity_id !== $section->opportunity_id) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'The parent section does not belong to this opportunity.',
                    ]);
                }

                // All sections on this opportunity, keyed by id, so depth + descendant
                // walks need no further queries.
                $sections = OpportunitySection::query()
                    ->where('opportunity_id', $section->opportunity_id)
                    ->get()
                    ->keyBy('id');

                if ($this->isDescendantOf($sections->all(), $newParentId, $section->id)) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'A section cannot be nested under one of its own sub-sections.',
                    ]);
                }

                $parentDepth = $this->depthOf($sections->all(), $newParentId);
                $subtreeHeight = $this->subtreeHeight($sections->all(), $section->id);

                // The moved section sits at (parentDepth + 1); its deepest descendant
                // sits subtreeHeight levels below that. Reject if that exceeds the cap.
                if ($parentDepth + 1 + $subtreeHeight > CreateOpportunitySection::MAX_DEPTH) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'Sections cannot be nested more than '.CreateOpportunitySection::MAX_DEPTH.' levels deep.',
                    ]);
                }
            }

            $oldParentId = $section->parent_id;

            $section->update([
                'parent_id' => $newParentId,
                'sort_order' => $data->sort_order,
            ]);

            event(new AuditableEvent(
                $section,
                'opportunity_section.reparented',
                oldValues: ['parent_id' => $oldParentId],
                newValues: ['parent_id' => $newParentId],
            ));

            return OpportunitySectionData::fromModel($section);
        });
    }

    /**
     * The 1-based depth of a section (a top-level section is depth 1).
     *
     * @param  array<int, OpportunitySection>  $sections
     */
    private function depthOf(array $sections, int $sectionId): int
    {
        $depth = 1;
        $cursor = $sections[$sectionId] ?? null;

        while ($cursor !== null && $cursor->parent_id !== null) {
            $cursor = $sections[$cursor->parent_id] ?? null;
            $depth++;
        }

        return $depth;
    }

    /**
     * The number of levels of descendants beneath a section (a leaf has height 0).
     *
     * @param  array<int, OpportunitySection>  $sections
     */
    private function subtreeHeight(array $sections, int $sectionId): int
    {
        $height = 0;

        foreach ($sections as $candidate) {
            if ($candidate->parent_id === $sectionId) {
                $height = max($height, 1 + $this->subtreeHeight($sections, $candidate->id));
            }
        }

        return $height;
    }

    /**
     * Whether `$candidateId` is the section being moved (`$movingId`) or one of its
     * descendants — i.e. nesting under it would create a cycle.
     *
     * @param  array<int, OpportunitySection>  $sections
     */
    private function isDescendantOf(array $sections, int $candidateId, int $movingId): bool
    {
        $cursor = $sections[$candidateId] ?? null;

        while ($cursor !== null) {
            if ($cursor->id === $movingId) {
                return true;
            }

            $cursor = $cursor->parent_id !== null ? ($sections[$cursor->parent_id] ?? null) : null;
        }

        return false;
    }
}
