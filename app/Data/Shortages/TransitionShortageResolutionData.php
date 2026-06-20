<?php

namespace App\Data\Shortages;

use Spatie\LaravelData\Data;

/**
 * Input for the reason-carrying shortage-resolution transitions — cancel and fail
 * (shortage-resolution-sub-hires.md §8.3).
 *
 * The reason is optional: it is persisted to `cancellation_reason` and surfaced on
 * the matching `shortage.resolution.cancelled`/`failed` event payload.
 */
class TransitionShortageResolutionData extends Data
{
    public function __construct(
        public ?string $reason = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
