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
 * new tree survives a Verbs replay. The supplied set must cover the opportunity's
 * entire active-version item set exactly once (enforced by the action).
 */
class RestructureOpportunityItemsData extends Data
{
    /**
     * @param  list<array{id: int, depth: int}>  $nodes
     */
    public function __construct(
        public array $nodes,
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
        ];
    }
}
