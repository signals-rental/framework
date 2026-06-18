<?php

namespace App\Services;

use App\Models\Sequence;

/**
 * Foundational allocator for named, monotonically increasing integer sequences.
 *
 * Hands out small auto-increment-style integers (starting at 1) for any named
 * sequence, atomically and cross-database (Postgres + SQLite). Unlike a DB
 * auto-increment column, the value is allocated in application code, so callers
 * can bake it into an event payload and reproduce it verbatim on replay — giving
 * event-sourced projections a replay-stable primary key.
 *
 * This is deliberately generic: opportunities, line items, assets, and invoices
 * will all reuse it for their replay-stable identifiers.
 */
class SequenceAllocator
{
    /**
     * Atomically return the next integer for the named sequence.
     *
     * Sequences start at 1 and are created on first use. The stored
     * `next_value` is always the NEXT integer to hand out, so a freshly created
     * row records `2` after returning `1`.
     */
    public function next(string $sequence): int
    {
        return Sequence::query()->getConnection()->transaction(function () use ($sequence): int {
            $row = Sequence::query()
                ->where('name', $sequence)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                Sequence::query()->create([
                    'name' => $sequence,
                    'next_value' => 2,
                ]);

                return 1;
            }

            $current = $row->next_value;
            $row->update(['next_value' => $current + 1]);

            return $current;
        });
    }
}
