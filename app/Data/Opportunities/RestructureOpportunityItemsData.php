<?php

namespace App\Data\Opportunities;

use App\Actions\Opportunities\RestructureOpportunityItems;
use Spatie\LaravelData\Data;

/**
 * Input DTO for restructuring an opportunity's entire line-item tree.
 *
 * `nodes` is the full item set in display pre-order (top-to-bottom), each carrying
 * its target tree `depth` (1-based). The {@see RestructureOpportunityItems} action
 * validates role-based placement, rebuilds every item's materialised `path` from
 * order + depth, and fires one event-sourced `ItemsRestructured` per item so the
 * new tree survives a Verbs replay. By default the supplied set must cover the
 * opportunity's entire active-version item set exactly once; pass
 * `prune_orphans: true` for local-first editor sync so omitted ids are removed.
 */
class RestructureOpportunityItemsData extends Data
{
    /**
     * @param  list<array{id: int, depth: int}>  $nodes
     */
    public function __construct(
        public array $nodes,
        public bool $prune_orphans = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'nodes' => ['required', 'array', 'min:1'],
            'nodes.*.id' => ['required', 'integer'],
            'nodes.*.depth' => ['required', 'integer', 'min:1'],
            'prune_orphans' => ['sometimes', 'boolean'],
        ];
    }
}
