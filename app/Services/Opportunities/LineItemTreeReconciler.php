<?php

namespace App\Services\Opportunities;

/**
 * Pure reconcile helpers for the local-first line-item editor.
 *
 * Encodes the net rule: local wins for un-synced edits on rows the client still
 * owns; the server tree is the merge authority for rows changed elsewhere since
 * the client's base revision.
 */
class LineItemTreeReconciler
{
    /**
     * Whether the client's base revision is stale relative to the server.
     */
    public function isStale(int $clientRevision, int $serverRevision): bool
    {
        return $clientRevision > 0 && $serverRevision > $clientRevision;
    }

    /**
     * Merge a fresh server tree into the local store while preserving rows that
     * still carry unsynced local edits (negative temp ids or ids listed in
     * {@see $pendingLocalIds}).
     *
     * @param  array<int, array<string, mixed>>  $localRows
     * @param  array<int, array<string, mixed>>  $serverRows
     * @param  array<int, int>  $pendingLocalIds  projection ids with queued unsynced edits
     * @return array{rows: array<int, array<string, mixed>>, conflicts: array<int, string>}
     */
    public function reconcile(array $localRows, array $serverRows, array $pendingLocalIds = []): array
    {
        $pending = array_fill_keys(array_map('intval', $pendingLocalIds), true);
        $localById = [];

        foreach ($localRows as $row) {
            $localById[(int) $row['id']] = $row;
        }

        $merged = [];
        $conflicts = [];

        foreach ($serverRows as $serverRow) {
            $id = (int) $serverRow['id'];
            $local = $localById[$id] ?? null;

            if ($local === null) {
                $merged[] = $serverRow;

                continue;
            }

            if ($id < 0 || isset($pending[$id])) {
                if ($this->hasHardFieldConflict($local, $serverRow)) {
                    $conflicts[$id] = 'This line was also changed elsewhere.';
                }

                $merged[] = $local;

                continue;
            }

            $merged[] = $serverRow;
        }

        foreach ($localRows as $row) {
            $id = (int) $row['id'];

            if ($id < 0) {
                $merged[] = $row;
            }
        }

        return [
            'rows' => $this->sortPreOrder($merged),
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Collect projection ids that still have unsynced queued field edits.
     *
     * @param  array<int, array<string, mixed>>  $queue
     * @return list<int>
     */
    public function pendingLocalIdsFromQueue(array $queue): array
    {
        $ids = [];

        foreach ($queue as $mutation) {
            if (($mutation['kind'] ?? '') === 'field' && isset($mutation['id'])) {
                $ids[] = (int) $mutation['id'];
            }
        }

        return array_values(array_unique(array_filter($ids, fn (int $id): bool => $id !== 0)));
    }

    /**
     * @param  array<string, mixed>  $local
     * @param  array<string, mixed>  $server
     */
    private function hasHardFieldConflict(array $local, array $server): bool
    {
        $fields = ['quantity', 'unit_price', 'discount_percent', 'name'];

        foreach ($fields as $field) {
            $localValue = $local[$field] ?? null;
            $serverValue = $server[$field] ?? null;

            if ($localValue !== $serverValue) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function sortPreOrder(array $rows): array
    {
        usort($rows, function (array $a, array $b): int {
            $pathCompare = strcmp((string) ($a['path'] ?? ''), (string) ($b['path'] ?? ''));

            if ($pathCompare !== 0) {
                return $pathCompare;
            }

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        return $rows;
    }
}
