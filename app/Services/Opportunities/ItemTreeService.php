<?php

namespace App\Services\Opportunities;

use App\Models\OpportunityItem;

/**
 * DB-aware allocator for materialised-path slots in an opportunity's line-item
 * tree. Queries the `opportunity_items` projection to find the next available
 * sibling path, scoped by (opportunity, quote version).
 *
 * Distinct from the pure {@see ItemPathService}, which rebuilds a whole tree's
 * geometry in memory: this service answers "what is the next free path?" against
 * persisted rows, used by the add path to bake a replay-stable position into the
 * genesis event.
 */
class ItemTreeService
{
    private const SEGMENT_WIDTH = 4;

    /**
     * The next top-level path ("0001", "0002", …) for this opportunity + version
     * scope.
     */
    public function nextTopLevelPath(int $opportunityId, ?int $versionId): string
    {
        return $this->nextSiblingPath($opportunityId, $versionId, '');
    }

    /**
     * The next child path under a given parent path ("0001" + "0001" =
     * "00010001") for this opportunity + version scope.
     */
    public function nextChildPath(int $opportunityId, ?int $versionId, string $parentPath): string
    {
        return $this->nextSiblingPath($opportunityId, $versionId, $parentPath);
    }

    /**
     * The next available path one level deeper than the given parent prefix: the
     * highest existing sibling's trailing segment + 1, padded to the segment
     * width, or "0001" when the scope is empty at that level.
     */
    private function nextSiblingPath(int $opportunityId, ?int $versionId, string $parentPrefix): string
    {
        $childLen = strlen($parentPrefix) + self::SEGMENT_WIDTH;

        $max = OpportunityItem::query()
            ->where('opportunity_id', $opportunityId)
            ->when(
                $versionId !== null,
                fn ($query) => $query->where('version_id', $versionId),
                fn ($query) => $query->whereNull('version_id'),
            )
            ->when($parentPrefix !== '', fn ($query) => $query->where('path', 'like', $parentPrefix.'%'))
            ->whereRaw('LENGTH(path) = ?', [$childLen])
            ->orderByDesc('path')
            ->value('path');

        $next = $max === null ? 1 : ((int) substr($max, -self::SEGMENT_WIDTH)) + 1;

        return $parentPrefix.str_pad((string) $next, self::SEGMENT_WIDTH, '0', STR_PAD_LEFT);
    }
}
