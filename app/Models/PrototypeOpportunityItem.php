<?php

namespace App\Models;

use App\Enums\PrototypeItemType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * THROWAWAY "Editor Lab" prototype line-item.
 *
 * A flat, Current-RMS-style grid row scoped to one prototype editor via the
 * `prototype` column. Tree nesting + order live entirely in the materialized
 * `path` (4-char zero-padded segment per level — lexical sort = pre-order,
 * depth = segment count). Not part of the event-sourced opportunity backend.
 *
 * @property int $id
 * @property int $opportunity_id
 * @property string $prototype
 * @property PrototypeItemType $item_type
 * @property string $path
 * @property int|null $revenue_group_id
 * @property string $name
 * @property string $quantity decimal(12,2) cast as string
 * @property int $days
 * @property int $unit_price minor units
 * @property string|null $discount_percent decimal(5,2) cast as string
 * @property int $charge_total minor units
 * @property string|null $type_label
 * @property string|null $status_label
 * @property bool $is_collapsed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PrototypeOpportunityItem extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'opportunity_id',
        'prototype',
        'item_type',
        'path',
        'revenue_group_id',
        'name',
        'quantity',
        'days',
        'unit_price',
        'discount_percent',
        'charge_total',
        'type_label',
        'status_label',
        'is_collapsed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_type' => PrototypeItemType::class,
            'unit_price' => 'integer',
            'charge_total' => 'integer',
            'days' => 'integer',
            'quantity' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'is_collapsed' => 'boolean',
        ];
    }

    /**
     * Tree depth of this row, derived from the path length (4 chars per level).
     * Top-level row (path "0001") is depth 1.
     */
    public function depth(): int
    {
        return (int) (strlen($this->path) / 4);
    }
}
