<?php

namespace App\Services;

use App\Models\AutoNumberSequence;

/**
 * Generates sequential auto-numbers for custom fields.
 *
 * Uses atomic database increment to prevent duplicate numbers under
 * concurrent access. Formats numbers with configurable prefix, suffix,
 * and zero-padded width.
 */
class AutoNumberService
{
    /**
     * Generate the next auto-number for a custom field.
     *
     * Uses a database-level atomic increment to guarantee uniqueness
     * even under concurrent requests.
     *
     * @param  int  $customFieldId  The custom field ID to generate for
     * @param  int  $padWidth  Minimum width of the numeric portion (zero-padded)
     */
    public function generate(int $customFieldId, int $padWidth = 5): string
    {
        [$currentValue, $sequence] = $this->consumeNext($customFieldId);

        $number = str_pad((string) $currentValue, $padWidth, '0', STR_PAD_LEFT);

        return ($sequence->prefix ?? '').$number.($sequence->suffix ?? '');
    }

    /**
     * Preview the next auto-number without consuming it.
     */
    public function preview(int $customFieldId, int $padWidth = 5): string
    {
        $sequence = AutoNumberSequence::query()
            ->where('custom_field_id', $customFieldId)
            ->firstOrFail();

        $number = str_pad((string) $sequence->next_value, $padWidth, '0', STR_PAD_LEFT);

        return ($sequence->prefix ?? '').$number.($sequence->suffix ?? '');
    }

    /**
     * Reset the sequence to a specific value.
     */
    public function reset(int $customFieldId, int $nextValue = 1): void
    {
        AutoNumberSequence::query()
            ->where('custom_field_id', $customFieldId)
            ->update(['next_value' => $nextValue]);
    }

    /**
     * Atomically consume the next value and increment the sequence.
     *
     * @return array{0: int, 1: AutoNumberSequence}
     */
    private function consumeNext(int $customFieldId): array
    {
        return AutoNumberSequence::query()->getConnection()->transaction(function () use ($customFieldId): array {
            $sequence = AutoNumberSequence::query()
                ->where('custom_field_id', $customFieldId)
                ->lockForUpdate()
                ->firstOrFail();

            $currentValue = $sequence->next_value;
            $sequence->update(['next_value' => $currentValue + 1]);

            return [$currentValue, $sequence];
        });
    }
}
