<?php

namespace App\Contracts\Opportunities;

/**
 * Server contract surface for the production local-first line-items editor Volt
 * component. Implemented by the anonymous Volt class so tests and static analysis
 * can type-hint against it.
 */
interface OpportunityLineItemsEditorContract
{
    public function treeRevision(): int;

    /**
     * @return array{tree: array<int, array<string, mixed>>, revision: int}
     */
    public function serverTree(): array;

    /**
     * @param  array<int, array{id: int, depth: int}>  $nodes
     * @return array{stale: bool, revision: int}
     */
    public function persistTree(array $nodes, int $baseRevision = 0): array;

    /**
     * @param  array<int, array<string, mixed>>  $localRows
     * @param  array<int, int>  $pendingLocalIds
     * @return array{stale: bool, revision: int, tree: array<int, array<string, mixed>>, conflicts: array<int, string>}
     */
    public function pullTree(int $baseRevision, array $localRows = [], array $pendingLocalIds = []): array;
}
